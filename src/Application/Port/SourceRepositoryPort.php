<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Source;

interface SourceRepositoryPort
{
    public function save(Source $source): void;

    public function findById(string $id, string $userId): ?Source;

    /** @return Source[] */
    public function findAllByUser(string $userId): array;

    public function delete(string $id, string $userId): void;

    public function findByInboundUuid(string $inboundUuid): ?Source;
}
