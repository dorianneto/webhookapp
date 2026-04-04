<?php

declare(strict_types=1);

namespace App\Infrastructure\Transaction;

use App\Application\Port\TransactionPort;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTransactionAdapter implements TransactionPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function execute(callable $operation): void
    {
        $this->entityManager->wrapInTransaction($operation);
    }
}
