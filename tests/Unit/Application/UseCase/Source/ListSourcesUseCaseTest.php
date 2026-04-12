<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Source;

use App\Application\Port\SourceRepositoryPort;
use App\Application\UseCase\Source\ListSourcesUseCase;
use App\Domain\Source;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ListSourcesUseCaseTest extends TestCase
{
    private SourceRepositoryPort&MockObject $repository;
    private ListSourcesUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SourceRepositoryPort::class);
        $this->useCase    = new ListSourcesUseCase($this->repository, new NullLogger());
    }

    public function testExecuteReturnsSources(): void
    {
        $sources = [
            new Source('id-1', 'user-id', 'Source 1', 'uuid-1', new \DateTimeImmutable()),
            new Source('id-2', 'user-id', 'Source 2', 'uuid-2', new \DateTimeImmutable()),
        ];

        $this->repository
            ->expects($this->once())
            ->method('findAllByUser')
            ->with('user-id')
            ->willReturn($sources);

        $result = $this->useCase->execute('request-id', 'user-id');

        $this->assertSame($sources, $result);
    }

    public function testExecuteReturnsEmptyArrayWhenNoSources(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findAllByUser')
            ->with('user-id')
            ->willReturn([]);

        $result = $this->useCase->execute('request-id', 'user-id');

        $this->assertSame([], $result);
    }
}
