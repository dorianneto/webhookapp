<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\EventRepositoryPort;
use App\Domain\Event as DomainEvent;
use App\Domain\EventStatus;
use App\Entity\Event as EventEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineEventRepository implements EventRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(DomainEvent $event): void
    {
        $entity = EventEntity::fromDomain($event);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?DomainEvent
    {
        $entity = $this->entityManager
            ->getRepository(EventEntity::class)
            ->findOneBy(['id' => $id]);

        return $entity?->toDomain();
    }

    /** @return DomainEvent[] */
    public function findRecentBySource(string $sourceId, int $limit): array
    {
        $entities = $this->entityManager
            ->getRepository(EventEntity::class)
            ->findBy(['sourceId' => $sourceId], ['receivedAt' => 'DESC'], $limit);

        return array_map(static fn(EventEntity $e) => $e->toDomain(), $entities);
    }

    public function updateStatus(string $id, EventStatus $status): void
    {
        $entity = $this->entityManager
            ->getRepository(EventEntity::class)
            ->findOneBy(['id' => $id]);

        if ($entity === null) {
            return;
        }

        $entity->setStatus($status);
        $this->entityManager->flush();
    }
}
