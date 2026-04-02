<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\UserRepositoryPort;
use App\Domain\User as DomainUser;
use App\Entity\User as UserEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserRepository implements UserRepositoryPort
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(DomainUser $user): void
    {
        $entity = UserEntity::fromDomain($user);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findByEmail(string $email): ?DomainUser
    {
        $entity = $this->entityManager
            ->getRepository(UserEntity::class)
            ->findOneBy(['email' => $email]);

        return $entity?->toDomain();
    }
}
