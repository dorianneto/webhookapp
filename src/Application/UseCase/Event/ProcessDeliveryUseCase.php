<?php

declare(strict_types=1);

namespace App\Application\UseCase\Event;

use App\Application\Message\DeliverEventMessage;
use App\Application\Port\DeliveryAttemptRepositoryPort;
use App\Application\Port\DeliveryQueuePort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\EventEndpointDeliveryRepositoryPort;
use App\Application\Port\EventRepositoryPort;
use App\Application\Port\HttpDeliveryPort;
use App\Application\Port\TransactionPort;
use App\Domain\DeliveryAttempt;
use App\Domain\EventEndpointDelivery;
use App\Domain\EventStatus;
use Symfony\Component\Uid\Uuid;

final class ProcessDeliveryUseCase
{
    private const RETRY_DELAYS_MS = [30_000, 300_000, 1_800_000, 7_200_000];

    public function __construct(
        private readonly EventRepositoryPort $eventRepository,
        private readonly EventEndpointDeliveryRepositoryPort $deliveryRepository,
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly DeliveryAttemptRepositoryPort $attemptRepository,
        private readonly HttpDeliveryPort $httpDelivery,
        private readonly DeliveryQueuePort $queue,
        private readonly TransactionPort $transaction,
    ) {}

    public function execute(DeliverEventMessage $message): void
    {
        $endpoint = $this->endpointRepository->findById($message->endpointId);
        $event    = $this->eventRepository->findById($message->eventId);
        $delivery = $this->deliveryRepository->findByEventAndEndpoint($message->eventId, $message->endpointId);

        $outgoingHeaders = array_merge($event->getHeaders(), [
            'X-Webhook-Event-Id' => [$message->eventId],
        ]);

        $result = $this->httpDelivery->deliver(
            $endpoint->getUrl(),
            $outgoingHeaders,
            $event->getBody(),
            10,
        );

        $attempt = new DeliveryAttempt(
            id: Uuid::v7()->toRfc4122(),
            eventId: $message->eventId,
            endpointId: $message->endpointId,
            attemptNumber: $message->attemptNumber,
            statusCode: $result->statusCode,
            responseBody: $result->responseBody,
            durationMs: $result->durationMs,
            attemptedAt: new \DateTimeImmutable(),
        );

        $this->attemptRepository->save($attempt);

        $newDeliveryStatus = match (true) {
            $result->success                       => EventStatus::Delivered,
            $message->attemptNumber >= 5           => EventStatus::Failed,
            default                                => EventStatus::Pending,
        };

        $this->transaction->execute(function () use ($delivery, $newDeliveryStatus, $message): void {
            $this->deliveryRepository->updateStatus($delivery->getId(), $newDeliveryStatus);
            $allDeliveries = $this->deliveryRepository->findAllByEvent($message->eventId);
            $newEventStatus = $this->computeEventStatus($allDeliveries);
            $this->eventRepository->updateStatus($message->eventId, $newEventStatus);
        });

        if (!$result->success && $message->attemptNumber < 5) {
            $delayMs = self::RETRY_DELAYS_MS[$message->attemptNumber - 1];
            $this->queue->enqueue(
                new DeliverEventMessage($message->eventId, $message->endpointId, $message->attemptNumber + 1, $message->requestId),
                $delayMs,
            );
        }
    }

    /** @param EventEndpointDelivery[] $deliveries */
    private function computeEventStatus(array $deliveries): EventStatus
    {
        foreach ($deliveries as $delivery) {
            if ($delivery->getStatus() === EventStatus::Failed) {
                return EventStatus::Failed;
            }
        }

        foreach ($deliveries as $delivery) {
            if ($delivery->getStatus() !== EventStatus::Delivered) {
                return EventStatus::Pending;
            }
        }

        return EventStatus::Delivered;
    }
}
