<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Endpoint;

interface EndpointRepositoryPort
{
    public function save(Endpoint $endpoint): void;

    public function findById(string $id): ?Endpoint;

    /** @return Endpoint[] */
    public function findAllBySource(string $sourceId): array;

    public function delete(string $id): void;

    /** @return Endpoint[] */
    public function findActiveBySource(string $sourceId): array;
}
