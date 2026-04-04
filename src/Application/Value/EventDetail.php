<?php

declare(strict_types=1);

namespace App\Application\Value;

use App\Domain\Event;

final readonly class EventDetail
{
    /**
     * @param EndpointDeliveryDetail[] $deliveries
     */
    public function __construct(
        public Event $event,
        public array $deliveries,
    ) {}
}
