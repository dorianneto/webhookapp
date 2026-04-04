<?php

declare(strict_types=1);

namespace App\Application\Port;

interface TransactionPort
{
    public function execute(callable $operation): void;
}
