<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\Message\DeliverEventMessage;
use App\Application\Port\DeliveryQueuePort;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerDeliveryQueue implements DeliveryQueuePort
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {}

    public function enqueue(DeliverEventMessage $message): void
    {
        $this->bus->dispatch($message);
    }
}
