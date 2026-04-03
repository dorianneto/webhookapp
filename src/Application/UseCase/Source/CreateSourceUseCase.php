<?php

declare(strict_types=1);

namespace App\Application\UseCase\Source;

use App\Application\Port\SourceRepositoryPort;
use App\Domain\Source;
use Symfony\Component\Uid\Uuid;

final class CreateSourceUseCase
{
    public function __construct(
        private readonly SourceRepositoryPort $sourceRepository,
    ) {}

    public function execute(string $id, string $userId, string $name): Source
    {
        $inboundUuid = Uuid::v7()->toRfc4122();

        $source = new Source(
            id: $id,
            userId: $userId,
            name: $name,
            inboundUuid: $inboundUuid,
            createdAt: new \DateTimeImmutable(),
        );

        $this->sourceRepository->save($source);

        return $source;
    }
}
