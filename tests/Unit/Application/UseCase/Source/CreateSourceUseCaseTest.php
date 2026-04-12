<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Source;

use App\Application\Port\SourceRepositoryPort;
use App\Application\UseCase\Source\CreateSourceUseCase;
use App\Domain\Source;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CreateSourceUseCaseTest extends TestCase
{
    private SourceRepositoryPort&MockObject $repository;
    private CreateSourceUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SourceRepositoryPort::class);
        $this->useCase    = new CreateSourceUseCase($this->repository, new NullLogger());
    }

    public function testExecuteSavesSourceWithCorrectData(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Source $source): bool {
                return $source->getId() === 'test-id'
                    && $source->getUserId() === 'user-id'
                    && $source->getName() === 'My Source'
                    && $source->getInboundUuid() !== '';
            }));

        $result = $this->useCase->execute('request-id', 'test-id', 'user-id', 'My Source');

        $this->assertInstanceOf(Source::class, $result);
        $this->assertSame('test-id', $result->getId());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteGeneratesUniqueInboundUuids(): void
    {
        $inboundUuids = [];
        $stub = $this->createStub(SourceRepositoryPort::class);
        $stub->method('save')->willReturnCallback(function (Source $source) use (&$inboundUuids): void {
            $inboundUuids[] = $source->getInboundUuid();
        });

        $useCase = new CreateSourceUseCase($stub, new NullLogger());
        $useCase->execute('request-id', 'id-1', 'user-id', 'Source 1');
        $useCase->execute('request-id', 'id-2', 'user-id', 'Source 2');

        $this->assertCount(2, array_unique($inboundUuids));
    }
}
