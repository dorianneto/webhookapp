<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\Message\DeliverEventMessage;
use App\Application\Port\DeliveryQueuePort;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class MessengerDeliveryQueue implements DeliveryQueuePort
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {}

    public function enqueue(DeliverEventMessage $message, int $delayMs = 0): void
    {
        $stamps = $delayMs > 0 ? [new DelayStamp($delayMs)] : [];
        $this->bus->dispatch($message, $stamps);
    }
}
