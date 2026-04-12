<?php

declare(strict_types=1);

namespace App\Application\UseCase\Source;

use App\Application\Port\SourceRepositoryPort;

final class DeleteSourceUseCase
{
    public function __construct(
        private readonly SourceRepositoryPort $sourceRepository,
    ) {}

    public function execute(string $requestId, string $id, string $userId): void
    {
        $this->sourceRepository->delete($id, $userId);
    }
}
