<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Application\UseCase\Endpoint\AddEndpointUseCase;
use App\Domain\Endpoint;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddEndpointUseCaseTest extends TestCase
{
    private EndpointRepositoryPort&MockObject $repository;
    private AddEndpointUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EndpointRepositoryPort::class);
        $this->useCase    = new AddEndpointUseCase($this->repository);
    }

    public function testExecuteSavesEndpointWithCorrectData(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Endpoint $endpoint): bool {
                return $endpoint->getId() === 'test-id'
                    && $endpoint->getSourceId() === 'source-id'
                    && $endpoint->getUrl() === 'https://example.com/hook';
            }));

        $this->useCase->execute('test-id', 'source-id', 'https://example.com/hook');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReturnsEndpoint(): void
    {
        $result = $this->useCase->execute('test-id', 'source-id', 'https://example.com/hook');

        $this->assertInstanceOf(Endpoint::class, $result);
        $this->assertSame('test-id', $result->getId());
        $this->assertSame('https://example.com/hook', $result->getUrl());
    }

    public function testExecuteThrowsOnInvalidUrl(): void
    {
        $this->repository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URL format.');

        $this->useCase->execute('test-id', 'source-id', 'not-a-valid-url');
    }
}
