<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\DeliveryAttemptRepositoryPort;
use App\Domain\DeliveryAttempt as DomainDeliveryAttempt;
use App\Entity\DeliveryAttempt as DeliveryAttemptEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineDeliveryAttemptRepository implements DeliveryAttemptRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(DomainDeliveryAttempt $attempt): void
    {
        $entity = DeliveryAttemptEntity::fromDomain($attempt);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function countByEventAndEndpoint(string $eventId, string $endpointId): int
    {
        return count(
            $this->entityManager
                ->getRepository(DeliveryAttemptEntity::class)
                ->findBy(['eventId' => $eventId, 'endpointId' => $endpointId])
        );
    }
}
