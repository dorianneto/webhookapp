<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Port\UserRepositoryPort;
use App\Domain\Exception\EmailAlreadyTakenException;
use App\Domain\User;

final class RegisterUserUseCase
{
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
    ) {}

    public function execute(string $requestId, string $id, string $email, string $passwordHash): void
    {
        if (null !== $this->userRepository->findByEmail($email)) {
            throw new EmailAlreadyTakenException('Email is already registered.');
        }

        $user = new User(
            id: $id,
            email: $email,
            passwordHash: $passwordHash,
            createdAt: new \DateTimeImmutable(),
        );

        $this->userRepository->save($user);
    }
}
