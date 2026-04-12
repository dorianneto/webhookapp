<?php

declare(strict_types=1);

namespace App\Application\UseCase\Source;

use App\Application\Port\SourceRepositoryPort;
use App\Domain\Source;

final class ListSourcesUseCase
{
    public function __construct(
        private readonly SourceRepositoryPort $sourceRepository,
    ) {}

    /** @return Source[] */
    public function execute(string $requestId, string $userId): array
    {
        return $this->sourceRepository->findAllByUser($userId);
    }
}
