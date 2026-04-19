<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Port\UserRepositoryPort;
use App\Domain\Exception\EmailAlreadyTakenException;
use App\Domain\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class RegisterUserUseCase
{
    public function __construct(
        private readonly UserRepositoryPort $userRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $id, string $email, string $passwordHash, ?string $name = null): void
    {
        $this->logger->info('Register user attempt', [
            'request_id' => $requestId,
        ]);

        if (null !== $this->userRepository->findByEmail($email)) {
            $this->logger->info('Register user email already taken', [
                'request_id' => $requestId,
            ]);

            throw new EmailAlreadyTakenException('Email is already registered.');
        }

        $user = new User(
            id: $id,
            email: $email,
            passwordHash: $passwordHash,
            createdAt: new \DateTimeImmutable(),
            name: $name ?? null,
        );

        $this->userRepository->save($user);

        $this->logger->info('Register user registered', [
            'request_id' => $requestId,
            'user_id'    => $id,
        ]);
    }
}
