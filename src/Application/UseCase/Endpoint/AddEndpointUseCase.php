<?php

declare(strict_types=1);

namespace App\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Domain\Endpoint;
use App\Domain\Exception\SourceNotFoundException;

final class AddEndpointUseCase
{
    public function __construct(
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly SourceRepositoryPort $sourceRepository,
    ) {}

    public function execute(string $requestId, string $id, string $sourceId, string $url, string $userId): Endpoint
    {
        if ($this->sourceRepository->findById($sourceId, $userId) === null) {
            throw new SourceNotFoundException('Source not found.');
        }

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
