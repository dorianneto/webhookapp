<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Endpoint as DomainEndpoint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'endpoints')]
class Endpoint
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\Column(name: 'source_id', type: Types::STRING)]
    private string $sourceId;

    #[ORM\Column(type: Types::STRING)]
    private string $url;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $sourceId,
        string $url,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->sourceId = $sourceId;
        $this->url = $url;
        $this->createdAt = $createdAt;
    }

    public static function fromDomain(DomainEndpoint $endpoint): self
    {
        return new self(
            $endpoint->getId(),
            $endpoint->getSourceId(),
            $endpoint->getUrl(),
            $endpoint->getCreatedAt(),
        );
    }

    public function toDomain(): DomainEndpoint
    {
        return new DomainEndpoint(
            $this->id,
            $this->sourceId,
            $this->url,
            $this->createdAt,
        );
    }
}
