<?php

declare(strict_types=1);

namespace App\Application\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\Uid\Uuid;

final class JwtService
{
    private const ACCESS_TOKEN_EXPIRY = 900; // 15 minutes
    private string $secret;

    public function __construct()
    {
        $this->secret = $_ENV['JWT_SECRET'] ?? 'change-this-secret-in-production';
    }

    public function createAccessToken(Uuid $userId, string $userType, string $email): string
    {
        $now = time();
        $payload = [
            'iss' => 'exam-app',
            'sub' => $userId->toRfc4122(),
            'type' => $userType,
            'email' => $email,
            'iat' => $now,
            'exp' => $now + self::ACCESS_TOKEN_EXPIRY,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function validateAccessToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return (array) $decoded;
        } catch (\Exception) {
            return null;
        }
    }

    public function getUserIdFromToken(string $token): ?Uuid
    {
        $payload = $this->validateAccessToken($token);
        if (!$payload || !isset($payload['sub'])) {
            return null;
        }
        return Uuid::fromString($payload['sub']);
    }

    public function getUserTypeFromToken(string $token): ?string
    {
        $payload = $this->validateAccessToken($token);
        return $payload['type'] ?? null;
    }
}
