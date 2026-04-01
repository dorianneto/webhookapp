<?php

declare(strict_types=1);

namespace App\Domain;

class DeliveryAttempt
{
    public function __construct(
        private string $id,
        private string $eventId,
        private string $endpointId,
        private int $attemptNumber,
        private ?int $statusCode,
        private string $responseBody,
        private int $durationMs,
        private \DateTimeImmutable $attemptedAt,
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

    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function getAttemptedAt(): \DateTimeImmutable
    {
        return $this->attemptedAt;
    }
}
