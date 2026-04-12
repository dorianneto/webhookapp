<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Port\UserRepositoryPort;
use App\Application\UseCase\RegisterUserUseCase;
use App\Domain\Exception\EmailAlreadyTakenException;
use App\Domain\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegistrationUseCaseTest extends TestCase
{
    private UserRepositoryPort&MockObject $repository;
    private RegisterUserUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryPort::class);
        $this->useCase    = new RegisterUserUseCase($this->repository);
    }

    public function testExecuteThrowsWhenEmailAlreadyTaken(): void
    {
        $existing = new User('existing-id', 'taken@example.com', 'hash', new \DateTimeImmutable());

        $this->repository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('taken@example.com')
            ->willReturn($existing);

        $this->repository
            ->expects($this->never())
            ->method('save');

        $this->expectException(EmailAlreadyTakenException::class);

        $this->useCase->execute('request-id', 'new-id', 'taken@example.com', 'anyhash');
    }

    public function testExecuteSavesUserWhenEmailIsAvailable(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('new@example.com')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user): bool {
                return $user->getId() === 'test-id'
                    && $user->getEmail() === 'new@example.com'
                    && $user->getPasswordHash() === 'hashvalue';
            }));

        $this->useCase->execute('request-id', 'test-id', 'new@example.com', 'hashvalue');
    }
}
