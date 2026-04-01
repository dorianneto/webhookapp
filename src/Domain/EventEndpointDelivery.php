<?php

declare(strict_types=1);

namespace App\Domain;

class EventEndpointDelivery
{
    public function __construct(
        private string $id,
        private string $eventId,
        private string $endpointId,
        private EventStatus $status,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getEndpointId(): string
    {
        return $this->endpointId;
    }

    public function getStatus(): EventStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setStatus(EventStatus $status): void
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
