<?php

declare(strict_types=1);

namespace App\Domain;

class Endpoint
{
    public function __construct(
        private string $id,
        private string $sourceId,
        private string $url,
        private \DateTimeImmutable $createdAt,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
