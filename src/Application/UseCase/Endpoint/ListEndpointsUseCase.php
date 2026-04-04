<?php

declare(strict_types=1);

namespace App\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Domain\Endpoint;
use App\Domain\Exception\SourceNotFoundException;

final class ListEndpointsUseCase
{
    public function __construct(
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly SourceRepositoryPort $sourceRepository,
    ) {}

    /** @return Endpoint[] */
    public function execute(string $sourceId, string $userId): array
    {
        if ($this->sourceRepository->findById($sourceId, $userId) === null) {
            throw new SourceNotFoundException('Source not found.');
        }

        return $this->endpointRepository->findAllBySource($sourceId);
    }
}
