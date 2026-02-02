<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\Index(columns: ['user_id', 'user_type'], name: 'idx_refresh_token_user')]
class RefreshTokenEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public Uuid $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    public string $token;

    #[ORM\Column(type: 'uuid')]
    public Uuid $userId;

    #[ORM\Column(type: 'string', length: 20)]
    public string $userType;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $createdAt;

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    private function __construct() {}

    public static function create(
        string $token,
        Uuid $userId,
        string $userType,
        \DateTimeImmutable $expiresAt
    ): self {
        $self = new self();

        $self->token = $token;
        $self->userId = $userId;
        $self->userType = $userType;
        $self->expiresAt = $expiresAt;
        $self->createdAt = new \DateTimeImmutable();

        return $self;
    }
}
