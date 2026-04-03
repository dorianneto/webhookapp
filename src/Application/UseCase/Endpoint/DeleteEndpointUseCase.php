<?php

declare(strict_types=1);

namespace App\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;

final class DeleteEndpointUseCase
{
    public function __construct(
        private readonly EndpointRepositoryPort $endpointRepository,
    ) {}

    public function execute(string $id): void
    {
        $this->endpointRepository->delete($id);
    }
}
