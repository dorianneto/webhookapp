<?php

declare(strict_types=1);

namespace App\Application\Message;

final readonly class DeliverEventMessage
{
    public function __construct(
        public string $eventId,
        public string $endpointId,
        public int $attemptNumber,
    ) {}
}
