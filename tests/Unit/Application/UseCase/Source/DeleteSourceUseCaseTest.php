<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Source;

use App\Application\Port\SourceRepositoryPort;
use App\Application\UseCase\Source\DeleteSourceUseCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class DeleteSourceUseCaseTest extends TestCase
{
    private SourceRepositoryPort&MockObject $repository;
    private DeleteSourceUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SourceRepositoryPort::class);
        $this->useCase    = new DeleteSourceUseCase($this->repository, new NullLogger());
    }

    public function testExecuteCallsDeleteWithCorrectArguments(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with('source-id', 'user-id');

        $this->useCase->execute('request-id', 'source-id', 'user-id');
    }
}
