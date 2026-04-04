<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\SourceRepositoryPort;
use App\Domain\Exception\SourceNotFoundException;
use App\Domain\Source as DomainSource;
use App\Entity\Source as SourceEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSourceRepository implements SourceRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(DomainSource $source): void
    {
        $entity = SourceEntity::fromDomain($source);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findById(string $id, string $userId): ?DomainSource
    {
        $entity = $this->entityManager
            ->getRepository(SourceEntity::class)
            ->findOneBy(['id' => $id, 'userId' => $userId]);

        return $entity?->toDomain();
    }

    /** @return DomainSource[] */
    public function findAllByUser(string $userId): array
    {
        $entities = $this->entityManager
            ->getRepository(SourceEntity::class)
            ->findBy(['userId' => $userId]);

        return array_map(static fn(SourceEntity $e) => $e->toDomain(), $entities);
    }

    public function findByInboundUuid(string $inboundUuid): ?DomainSource
    {
        $entity = $this->entityManager
            ->getRepository(SourceEntity::class)
            ->findOneBy(['inboundUuid' => $inboundUuid]);

        return $entity?->toDomain();
    }

    public function delete(string $id, string $userId): void
    {
        $entity = $this->entityManager
            ->getRepository(SourceEntity::class)
            ->findOneBy(['id' => $id, 'userId' => $userId]);

        if ($entity === null) {
            throw new SourceNotFoundException('Source not found.');
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
