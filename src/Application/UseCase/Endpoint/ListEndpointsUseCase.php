<?php

declare(strict_types=1);

namespace App\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Domain\Endpoint;
use App\Domain\Exception\SourceNotFoundException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class ListEndpointsUseCase
{
    public function __construct(
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly SourceRepositoryPort $sourceRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return Endpoint[] */
    public function execute(string $requestId, string $sourceId, string $userId): array
    {
        $this->logger->info('List endpoints attempt', [
            'request_id' => $requestId,
            'source_id'  => $sourceId,
        ]);

        if ($this->sourceRepository->findById($sourceId, $userId) === null) {
            $this->logger->info('List endpoints source not found', [
                'request_id' => $requestId,
                'source_id'  => $sourceId,
            ]);

            throw new SourceNotFoundException('Source not found.');
        }

        $endpoints = $this->endpointRepository->findAllBySource($sourceId);

        $this->logger->info('List endpoints returned', [
            'request_id' => $requestId,
            'source_id'  => $sourceId,
            'count'      => \count($endpoints),
        ]);

        return $endpoints;
    }
}
