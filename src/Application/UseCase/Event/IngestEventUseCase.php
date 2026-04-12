<?php

declare(strict_types=1);

namespace App\Application\UseCase\Event;

use App\Application\Message\DeliverEventMessage;
use App\Application\Port\DeliveryQueuePort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\EventEndpointDeliveryRepositoryPort;
use App\Application\Port\EventRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Domain\Event;
use App\Domain\EventEndpointDelivery;
use App\Domain\EventStatus;
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

        $this->logger->info('Ingest complete', [
            'request_id'     => $requestId,
            'event_id'       => $eventId,
            'source_id'      => $source->getId(),
            'enqueued_count' => $enqueuedCount,
        ]);
    }
}
