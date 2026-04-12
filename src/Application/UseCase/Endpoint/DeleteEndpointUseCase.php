<?php

declare(strict_types=1);

namespace App\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Domain\Exception\EndpointNotFoundException;
use App\Domain\Exception\SourceNotFoundException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class DeleteEndpointUseCase
{
    public function __construct(
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly SourceRepositoryPort $sourceRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $id, string $userId): void
    {
        $this->logger->info('Delete endpoint attempt', [
            'request_id'  => $requestId,
            'endpoint_id' => $id,
        ]);

        $endpoint = $this->endpointRepository->findById($id);

        if ($endpoint === null) {
            $this->logger->info('Delete endpoint not found', [
                'request_id'  => $requestId,
                'endpoint_id' => $id,
            ]);

            throw new EndpointNotFoundException('Endpoint not found.');
        }

        if ($this->sourceRepository->findById($endpoint->getSourceId(), $userId) === null) {
            $this->logger->info('Delete endpoint source not found', [
                'request_id'  => $requestId,
                'endpoint_id' => $id,
                'source_id'   => $endpoint->getSourceId(),
            ]);

            throw new SourceNotFoundException('Source not found.');
        }

        $this->endpointRepository->delete($id);

        $this->logger->info('Delete endpoint deleted', [
            'request_id'  => $requestId,
            'endpoint_id' => $id,
        ]);
    }
}
