<?php

declare(strict_types=1);

namespace App\Application\UseCase\Source;

use App\Application\Port\SourceRepositoryPort;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class DeleteSourceUseCase
{
    public function __construct(
        private readonly SourceRepositoryPort $sourceRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $id, string $userId): void
    {
        $this->logger->info('Delete source attempt', [
            'request_id' => $requestId,
            'source_id'  => $id,
        ]);

        $this->sourceRepository->delete($id, $userId);

        $this->logger->info('Delete source deleted', [
            'request_id' => $requestId,
            'source_id'  => $id,
        ]);
    }
}
