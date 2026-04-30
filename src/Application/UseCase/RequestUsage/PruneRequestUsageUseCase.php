<?php

declare(strict_types=1);

namespace App\Application\UseCase\RequestUsage;

use App\Application\Port\RequestUsageRepositoryPort;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

class PruneRequestUsageUseCase
{
    public function __construct(
        private readonly RequestUsageRepositoryPort $repository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, DateTimeImmutable $before): int
    {
        $this->logger->info('Prune request usage started', [
            'request_id' => $requestId,
            'before'     => $before->format('Y-m-d'),
        ]);

        $deleted = $this->repository->deleteOlderThan($before);

        $this->logger->info('Prune request usage complete', [
            'request_id'   => $requestId,
            'deleted_rows' => $deleted,
        ]);

        return $deleted;
    }
}
