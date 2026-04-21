<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\User;

interface UserRepositoryPort
{
    public function save(User $user): void;

    public function findByEmail(string $email): ?User;

    public function findById(string $id): ?User;
}
