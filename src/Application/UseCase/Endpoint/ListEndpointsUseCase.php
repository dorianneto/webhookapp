<?php

declare(strict_types=1);

namespace App\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Domain\Endpoint;

final class ListEndpointsUseCase
{
    public function __construct(
        private readonly EndpointRepositoryPort $endpointRepository,
    ) {}

    /** @return Endpoint[] */
    public function execute(string $sourceId): array
    {
        return $this->endpointRepository->findAllBySource($sourceId);
    }
}
