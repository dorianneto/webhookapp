<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\EventEndpointDeliveryRepositoryPort;
use App\Domain\EventEndpointDelivery as DomainDelivery;
use App\Entity\EventEndpointDelivery as DeliveryEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineEventEndpointDeliveryRepository implements EventEndpointDeliveryRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(DomainDelivery $delivery): void
    {
        $entity = DeliveryEntity::fromDomain($delivery);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
