<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Application\UseCase\Endpoint\DeleteEndpointUseCase;
use App\Domain\Exception\EndpointNotFoundException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeleteEndpointUseCaseTest extends TestCase
{
    private EndpointRepositoryPort&MockObject $repository;
    private DeleteEndpointUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EndpointRepositoryPort::class);
        $this->useCase    = new DeleteEndpointUseCase($this->repository);
    }

    public function testExecuteCallsDelete(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with('endpoint-id');

        $this->useCase->execute('endpoint-id');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecutePropagatesNotFoundException(): void
    {
        $this->repository
            ->method('delete')
            ->willThrowException(new EndpointNotFoundException('Endpoint not found.'));

        $this->expectException(EndpointNotFoundException::class);

        $this->useCase->execute('missing-id');
    }
}
