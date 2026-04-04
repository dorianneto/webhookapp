<?php

declare(strict_types=1);

namespace App\Application\Value;

final readonly class DeliveryResult
{
    public function __construct(
        public ?int $statusCode,
        public string $responseBody,
        public int $durationMs,
        public bool $success,
    ) {}
}
