<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Endpoint;

use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Application\UseCase\Endpoint\DeleteEndpointUseCase;
use App\Domain\Endpoint;
use App\Domain\Exception\EndpointNotFoundException;
use App\Domain\Exception\SourceNotFoundException;
use App\Domain\Source;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeleteEndpointUseCaseTest extends TestCase
{
    private EndpointRepositoryPort&MockObject $repository;
    private SourceRepositoryPort&MockObject $sourceRepository;
    private DeleteEndpointUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository       = $this->createMock(EndpointRepositoryPort::class);
        $this->sourceRepository = $this->createMock(SourceRepositoryPort::class);
        $this->useCase          = new DeleteEndpointUseCase($this->repository, $this->sourceRepository);
    }

    public function testExecuteCallsDelete(): void
    {
        $endpoint = new Endpoint('endpoint-id', 'source-id', 'https://example.com', new \DateTimeImmutable());

        $this->repository
            ->method('findById')
            ->with('endpoint-id')
            ->willReturn($endpoint);

        $this->sourceRepository
            ->method('findById')
            ->with('source-id', 'user-id')
            ->willReturn($this->createStub(Source::class));

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with('endpoint-id');

        $this->useCase->execute('endpoint-id', 'user-id');
    }

    public function testExecuteThrowsWhenEndpointNotFound(): void
    {
        $this->repository
            ->method('findById')
            ->willReturn(null);

        $this->expectException(EndpointNotFoundException::class);

        $this->useCase->execute('missing-id', 'user-id');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteThrowsWhenSourceNotOwned(): void
    {
        $endpoint = new Endpoint('endpoint-id', 'source-id', 'https://example.com', new \DateTimeImmutable());

        $this->repository
            ->method('findById')
            ->willReturn($endpoint);

        $this->sourceRepository
            ->method('findById')
            ->willReturn(null);

        $this->expectException(SourceNotFoundException::class);

        $this->useCase->execute('endpoint-id', 'other-user-id');
    }
}
