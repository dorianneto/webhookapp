<?php

declare(strict_types=1);

namespace App\Domain;

class Source
{
    public function __construct(
        private string $id,
        private string $userId,
        private string $name,
        private string $inboundUuid,
        private \DateTimeImmutable $createdAt,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getInboundUuid(): string
    {
        return $this->inboundUuid;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
