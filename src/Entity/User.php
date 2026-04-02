<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\User as DomainUser;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_users_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private string $id;

    #[ORM\Column(type: Types::STRING, unique: true)]
    private string $email;

    #[ORM\Column(name: 'password_hash', type: Types::STRING)]
    private string $passwordHash;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $email,
        string $passwordHash,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->createdAt = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    // --- UserInterface ---

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void {}

    // --- PasswordAuthenticatedUserInterface ---

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    // --- Session serialization: keep password hash out of the session ---

    public function __serialize(): array
    {
        return ['id' => $this->id, 'email' => $this->email];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
        $this->passwordHash = '';
        $this->createdAt = new \DateTimeImmutable('@0');
    }

    // --- Mapping helpers ---

    public static function fromDomain(DomainUser $user): self
    {
        return new self(
            $user->getId(),
            $user->getEmail(),
            $user->getPasswordHash(),
            $user->getCreatedAt(),
        );
    }

    public function toDomain(): DomainUser
    {
        return new DomainUser(
            $this->id,
            $this->email,
            $this->passwordHash,
            $this->createdAt,
        );
    }
}
