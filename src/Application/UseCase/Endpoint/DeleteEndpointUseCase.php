<?php

declare(strict_types=1);

namespace App\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Domain\Exception\EndpointNotFoundException;
use App\Domain\Exception\SourceNotFoundException;

final class DeleteEndpointUseCase
{
    public function __construct(
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly SourceRepositoryPort $sourceRepository,
    ) {}

    public function execute(string $requestId, string $id, string $userId): void
    {
        $endpoint = $this->endpointRepository->findById($id);

        if ($endpoint === null) {
            throw new EndpointNotFoundException('Endpoint not found.');
        }

        if ($this->sourceRepository->findById($endpoint->getSourceId(), $userId) === null) {
            throw new SourceNotFoundException('Source not found.');
        }

        $this->endpointRepository->delete($id);
    }
}
