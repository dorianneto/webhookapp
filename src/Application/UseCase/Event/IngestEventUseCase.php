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
use Symfony\Component\Uid\Uuid;

final class IngestEventUseCase
{
    public function __construct(
        private readonly SourceRepositoryPort $sourceRepository,
        private readonly EventRepositoryPort $eventRepository,
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly EventEndpointDeliveryRepositoryPort $deliveryRepository,
        private readonly DeliveryQueuePort $queue,
    ) {}

    public function execute(
        string $requestId,
        string $eventId,
        string $inboundUuid,
        string $method,
        array $headers,
        string $body,
    ): void {
        $source = $this->sourceRepository->findByInboundUuid($inboundUuid);

        if ($source === null) {
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

        $endpoints = $this->endpointRepository->findActiveBySource($source->getId());

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
        }
    }
}
