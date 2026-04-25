<?php

declare(strict_types=1);

namespace App\Application\Event;

final readonly class IngestCompletedEvent
{
    public function __construct(
        public string $userId,
        public string $sourceId,
        public string $eventId,
        public string $requestId,
    ) {}
}
