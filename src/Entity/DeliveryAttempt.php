<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\DeliveryAttempt as DomainDeliveryAttempt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'delivery_attempts')]
class DeliveryAttempt
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\Column(name: 'event_id', type: Types::STRING)]
    private string $eventId;

    #[ORM\Column(name: 'endpoint_id', type: Types::STRING)]
    private string $endpointId;

    #[ORM\Column(name: 'attempt_number', type: Types::INTEGER)]
    private int $attemptNumber;

    #[ORM\Column(name: 'status_code', type: Types::INTEGER, nullable: true)]
    private ?int $statusCode;

    #[ORM\Column(name: 'response_body', type: Types::STRING, length: 500)]
    private string $responseBody;

    #[ORM\Column(name: 'duration_ms', type: Types::INTEGER)]
    private int $durationMs;

    #[ORM\Column(name: 'attempted_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $attemptedAt;

    public function __construct(
        string $id,
        string $eventId,
        string $endpointId,
        int $attemptNumber,
        ?int $statusCode,
        string $responseBody,
        int $durationMs,
        \DateTimeImmutable $attemptedAt,
    ) {
        $this->id = $id;
        $this->eventId = $eventId;
        $this->endpointId = $endpointId;
        $this->attemptNumber = $attemptNumber;
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
        $this->durationMs = $durationMs;
        $this->attemptedAt = $attemptedAt;
    }

    public static function fromDomain(DomainDeliveryAttempt $attempt): self
    {
        return new self(
            $attempt->getId(),
            $attempt->getEventId(),
            $attempt->getEndpointId(),
            $attempt->getAttemptNumber(),
            $attempt->getStatusCode(),
            $attempt->getResponseBody(),
            $attempt->getDurationMs(),
            $attempt->getAttemptedAt(),
        );
    }

    public function toDomain(): DomainDeliveryAttempt
    {
        return new DomainDeliveryAttempt(
            $this->id,
            $this->eventId,
            $this->endpointId,
            $this->attemptNumber,
            $this->statusCode,
            $this->responseBody,
            $this->durationMs,
            $this->attemptedAt,
        );
    }
}
