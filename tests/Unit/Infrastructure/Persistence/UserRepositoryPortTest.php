<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence;

use App\Application\Port\UserRepositoryPort;
use App\Domain\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// Note: MockObject is kept for the testSaveAcceptsDomainUser test

final class UserRepositoryPortTest extends TestCase
{
    public function testSaveAcceptsDomainUser(): void
    {
        /** @var UserRepositoryPort&MockObject $port */
        $port = $this->createMock(UserRepositoryPort::class);
        $user = new User('id-1', 'a@b.com', 'h', new \DateTimeImmutable());

        $port->expects($this->once())->method('save')->with($user);
        $port->save($user);
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $port = $this->createStub(UserRepositoryPort::class);
        $port->method('findByEmail')->willReturn(null);

        $this->assertNull($port->findByEmail('missing@example.com'));
    }

    public function testFindByEmailReturnsDomainUserWhenFound(): void
    {
        $port     = $this->createStub(UserRepositoryPort::class);
        $expected = new User('id-2', 'found@example.com', 'h', new \DateTimeImmutable());
        $port->method('findByEmail')->willReturn($expected);

        $result = $port->findByEmail('found@example.com');
        $this->assertSame($expected, $result);
    }
}
