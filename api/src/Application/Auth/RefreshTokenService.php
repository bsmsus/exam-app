<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Infrastructure\Doctrine\Entity\RefreshTokenEntity;
use App\Infrastructure\Doctrine\Repository\RefreshTokenRepository;
use Symfony\Component\Uid\Uuid;

final class RefreshTokenService
{
    private const REFRESH_TOKEN_EXPIRY_DAYS = 7;

    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository
    ) {}

    public function createRefreshToken(Uuid $userId, string $userType): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable(
            sprintf('+%d days', self::REFRESH_TOKEN_EXPIRY_DAYS)
        );

        $refreshToken = RefreshTokenEntity::create(
            $token,
            $userId,
            $userType,
            $expiresAt
        );

        $this->refreshTokenRepository->save($refreshToken);

        return $token;
    }

    public function validateRefreshToken(string $token): ?RefreshTokenEntity
    {
        $refreshToken = $this->refreshTokenRepository->findByToken($token);

        if (!$refreshToken || $refreshToken->isExpired()) {
            return null;
        }

        return $refreshToken;
    }

    public function revokeRefreshToken(string $token): void
    {
        $this->refreshTokenRepository->deleteByToken($token);
    }

    public function revokeAllUserTokens(Uuid $userId, string $userType): void
    {
        $this->refreshTokenRepository->deleteByUser($userId, $userType);
    }
}
