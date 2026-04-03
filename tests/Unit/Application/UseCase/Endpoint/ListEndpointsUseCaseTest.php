<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Application\UseCase\Endpoint\ListEndpointsUseCase;
use App\Domain\Endpoint;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListEndpointsUseCaseTest extends TestCase
{
    private EndpointRepositoryPort&MockObject $repository;
    private ListEndpointsUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EndpointRepositoryPort::class);
        $this->useCase    = new ListEndpointsUseCase($this->repository);
    }

    public function testExecuteReturnsList(): void
    {
        $endpoints = [
            new Endpoint('id-1', 'source-id', 'https://example.com/a', new \DateTimeImmutable()),
            new Endpoint('id-2', 'source-id', 'https://example.com/b', new \DateTimeImmutable()),
        ];

        $this->repository
            ->expects($this->once())
            ->method('findAllBySource')
            ->with('source-id')
            ->willReturn($endpoints);

        $result = $this->useCase->execute('source-id');

        $this->assertSame($endpoints, $result);
        $this->assertCount(2, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReturnsEmptyArrayWhenNoEndpoints(): void
    {
        $this->repository
            ->method('findAllBySource')
            ->willReturn([]);

        $result = $this->useCase->execute('source-id');

        $this->assertSame([], $result);
    }
}
