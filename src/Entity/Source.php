<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Source as DomainSource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sources')]
class Source
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\Column(name: 'user_id', type: Types::STRING)]
    private string $userId;

    #[ORM\Column(type: Types::STRING)]
    private string $name;

    #[ORM\Column(name: 'inbound_uuid', type: Types::GUID, unique: true)]
    private string $inboundUuid;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $userId,
        string $name,
        string $inboundUuid,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->name = $name;
        $this->inboundUuid = $inboundUuid;
        $this->createdAt = $createdAt;
    }

    public static function fromDomain(DomainSource $source): self
    {
        return new self(
            $source->getId(),
            $source->getUserId(),
            $source->getName(),
            $source->getInboundUuid(),
            $source->getCreatedAt(),
        );
    }

    public function toDomain(): DomainSource
    {
        return new DomainSource(
            $this->id,
            $this->userId,
            $this->name,
            $this->inboundUuid,
            $this->createdAt,
        );
    }
}
