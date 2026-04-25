<?php

declare(strict_types=1);

namespace App\Application\UseCase\Event;

use App\Application\Event\IngestCompletedEvent;
use App\Application\Message\DeliverEventMessage;
use App\Application\Port\DeliveryQueuePort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\EventEndpointDeliveryRepositoryPort;
use App\Application\Port\EventRepositoryPort;
use App\Application\Port\IngestEventDispatcherPort;
use App\Application\Port\PlanRepositoryPort;
use App\Application\Port\RequestUsageRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Domain\Event;
use App\Domain\EventEndpointDelivery;
use App\Domain\EventStatus;
use App\Domain\Exception\QuotaExceededException;
use App\Domain\Exception\SourceNotFoundException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

#[WithMonologChannel('hookyard')]
final class IngestEventUseCase
{
    public function __construct(
        private readonly SourceRepositoryPort $sourceRepository,
        private readonly EventRepositoryPort $eventRepository,
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly EventEndpointDeliveryRepositoryPort $deliveryRepository,
        private readonly DeliveryQueuePort $queue,
        private readonly PlanRepositoryPort $planRepository,
        private readonly RequestUsageRepositoryPort $usageRepository,
        private readonly IngestEventDispatcherPort $ingestEventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(
        string $requestId,
        string $eventId,
        string $inboundUuid,
        string $method,
        array $headers,
        string $body,
    ): void {
        $this->logger->debug('Ingest source lookup', [
            'request_id'  => $requestId,
            'source_uuid' => $inboundUuid,
        ]);

        $source = $this->sourceRepository->findByInboundUuid($inboundUuid);

        if ($source === null) {
            $this->logger->info('Ingest source not found', [
                'request_id'  => $requestId,
                'source_uuid' => $inboundUuid,
            ]);

            throw new SourceNotFoundException('Source not found.');
        }

        $plan = $this->planRepository->findByUserId($source->getUserId());

        if ($plan === null) {
            $this->logger->info('Ingest rejected: no plan assigned', [
                'request_id' => $requestId,
                'user_id'    => $source->getUserId(),
            ]);

            throw new QuotaExceededException('No plan assigned.');
        }

        $used = $this->usageRepository->sumRolling30Days($source->getUserId());

        if ($used >= $plan->getMonthlyRequestLimit()) {
            $this->logger->info('Ingest rejected: quota exceeded', [
                'request_id' => $requestId,
                'user_id'    => $source->getUserId(),
                'used'       => $used,
                'limit'      => $plan->getMonthlyRequestLimit(),
            ]);

            throw new QuotaExceededException('Quota exceeded.');
        }

        $event = new Event(
            id: $eventId,
            sourceId: $source->getId(),
            method: $method,
            headers: $headers,
            body: $body,
            status: EventStatus::Pending,
            receivedAt: new \DateTimeImmutable(),
        );

        $this->eventRepository->save($event);

        $this->logger->debug('Ingest event created and saved', [
            'request_id' => $requestId,
            'event_id'   => $eventId,
            'source_id'  => $source->getId(),
        ]);

        $endpoints = $this->endpointRepository->findActiveBySource($source->getId());

        if ($endpoints === []) {
            $this->logger->info('Ingest no active endpoints found', [
                'request_id' => $requestId,
                'event_id'   => $eventId,
                'source_id'  => $source->getId(),
            ]);
        }

        $enqueuedCount = 0;

        foreach ($endpoints as $endpoint) {
            $delivery = new EventEndpointDelivery(
                id: Uuid::v7()->toRfc4122(),
                eventId: $eventId,
                endpointId: $endpoint->getId(),
                status: EventStatus::Pending,
                createdAt: new \DateTimeImmutable(),
                updatedAt: new \DateTimeImmutable(),
            );

            $this->deliveryRepository->save($delivery);
            $this->queue->enqueue(new DeliverEventMessage($eventId, $endpoint->getId(), 1, $requestId));

            $this->logger->info('Ingest message enqueued', [
                'request_id'  => $requestId,
                'event_id'    => $eventId,
                'endpoint_id' => $endpoint->getId(),
            ]);

            ++$enqueuedCount;
        }

        $this->ingestEventDispatcher->dispatch(new IngestCompletedEvent(
            userId: $source->getUserId(),
            sourceId: $source->getId(),
            eventId: $eventId,
            requestId: $requestId,
        ));

        $this->logger->info('Ingest complete', [
            'request_id'     => $requestId,
            'event_id'       => $eventId,
            'source_id'      => $source->getId(),
            'enqueued_count' => $enqueuedCount,
        ]);
    }
}
