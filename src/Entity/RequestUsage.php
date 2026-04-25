<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'request_usage')]
#[ORM\UniqueConstraint(name: 'UNIQ_request_usage_user_date', columns: ['user_id', 'bucket_date'])]
class RequestUsage
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    private int $id;

    #[ORM\Column(name: 'user_id', type: Types::STRING)]
    private string $userId;

    #[ORM\Column(name: 'bucket_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $bucketDate;

    #[ORM\Column(type: Types::INTEGER)]
    private int $count;

    public function __construct(
        string $userId,
        \DateTimeImmutable $bucketDate,
        int $count = 0,
    ) {
        $this->userId = $userId;
        $this->bucketDate = $bucketDate;
        $this->count = $count;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getBucketDate(): \DateTimeImmutable
    {
        return $this->bucketDate;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
