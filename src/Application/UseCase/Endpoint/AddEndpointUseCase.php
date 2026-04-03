<?php

declare(strict_types=1);

namespace App\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Domain\Endpoint;

final class AddEndpointUseCase
{
    public function __construct(
        private readonly EndpointRepositoryPort $endpointRepository,
    ) {}

    public function execute(string $id, string $sourceId, string $url): Endpoint
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid URL format.');
        }

        $endpoint = new Endpoint(
            id: $id,
            sourceId: $sourceId,
            url: $url,
            createdAt: new \DateTimeImmutable(),
        );

        $this->endpointRepository->save($endpoint);

        return $endpoint;
    }
}
