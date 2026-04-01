<?php

declare(strict_types=1);

namespace App\Domain;

class Event
{
    public function __construct(
        private string $id,
        private string $sourceId,
        private string $method,
        private array $headers,
        private string $body,
        private EventStatus $status,
        private \DateTimeImmutable $receivedAt,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getStatus(): EventStatus
    {
        return $this->status;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setStatus(EventStatus $status): void
    {
        $this->status = $status;
    }
}
