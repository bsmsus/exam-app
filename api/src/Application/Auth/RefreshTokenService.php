<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Infrastructure\Doctrine\RefreshTokenEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class RefreshTokenService
{
    private const REFRESH_TOKEN_EXPIRY_DAYS = 7;

    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function createRefreshToken(Uuid $userId, string $userType): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable(sprintf('+%d days', self::REFRESH_TOKEN_EXPIRY_DAYS));

        $refreshToken = new RefreshTokenEntity(
            Uuid::v4(),
            $token,
            $userId,
            $userType,
            $expiresAt
        );

        $this->em->persist($refreshToken);
        $this->em->flush();

        return $token;
    }

    public function validateRefreshToken(string $token): ?RefreshTokenEntity
    {
        $refreshToken = $this->em->getRepository(RefreshTokenEntity::class)
            ->findOneBy(['token' => $token]);

        if (!$refreshToken || $refreshToken->isExpired()) {
            return null;
        }

        return $refreshToken;
    }

    public function revokeRefreshToken(string $token): void
    {
        $refreshToken = $this->em->getRepository(RefreshTokenEntity::class)
            ->findOneBy(['token' => $token]);

        if ($refreshToken) {
            $this->em->remove($refreshToken);
            $this->em->flush();
        }
    }

    public function revokeAllUserTokens(Uuid $userId, string $userType): void
    {
        $this->em->createQuery(
            'DELETE FROM App\Infrastructure\Doctrine\RefreshTokenEntity rt
             WHERE rt.userId = :userId AND rt.userType = :userType'
        )
        ->setParameter('userId', $userId)
        ->setParameter('userType', $userType)
        ->execute();
    }
}
