<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\EventEndpointDelivery as DomainEventEndpointDelivery;
use App\Domain\EventStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_endpoint_deliveries')]
#[ORM\UniqueConstraint(name: 'uq_eed_event_endpoint', columns: ['event_id', 'endpoint_id'])]
#[ORM\Index(name: 'idx_eed_event_id', columns: ['event_id'])]
class EventEndpointDelivery
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\Column(name: 'event_id', type: Types::STRING)]
    private string $eventId;

    #[ORM\Column(name: 'endpoint_id', type: Types::STRING)]
    private string $endpointId;

    #[ORM\Column(type: Types::STRING, enumType: EventStatus::class)]
    private EventStatus $status;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $eventId,
        string $endpointId,
        EventStatus $status,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->eventId = $eventId;
        $this->endpointId = $endpointId;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(DomainEventEndpointDelivery $delivery): self
    {
        return new self(
            $delivery->getId(),
            $delivery->getEventId(),
            $delivery->getEndpointId(),
            $delivery->getStatus(),
            $delivery->getCreatedAt(),
            $delivery->getUpdatedAt(),
        );
    }

    public function toDomain(): DomainEventEndpointDelivery
    {
        return new DomainEventEndpointDelivery(
            $this->id,
            $this->eventId,
            $this->endpointId,
            $this->status,
            $this->createdAt,
            $this->updatedAt,
        );
    }
}
