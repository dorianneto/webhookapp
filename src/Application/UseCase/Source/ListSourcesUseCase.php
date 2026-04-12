<?php

declare(strict_types=1);

namespace App\Application\UseCase\Source;

use App\Application\Port\SourceRepositoryPort;
use App\Domain\Source;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class ListSourcesUseCase
{
    public function __construct(
        private readonly SourceRepositoryPort $sourceRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return Source[] */
    public function execute(string $requestId, string $userId): array
    {
        $this->logger->info('List sources attempt', [
            'request_id' => $requestId,
        ]);

        $sources = $this->sourceRepository->findAllByUser($userId);

        $this->logger->info('List sources returned', [
            'request_id' => $requestId,
            'count'      => \count($sources),
        ]);

        return $sources;
    }
}
