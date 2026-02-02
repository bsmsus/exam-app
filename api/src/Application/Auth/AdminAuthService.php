<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Infrastructure\Doctrine\Entity\AdminEntity;
use App\Infrastructure\Doctrine\Repository\AdminRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class AdminAuthService
{
    public function __construct(
        private AdminRepository $adminRepository,
        private JwtService $jwtService,
        private PasswordService $passwordService,
        private RefreshTokenService $refreshTokenService
    ) {}

    public function register(string $name, string $email, string $password): array
    {
        if (strlen($password) < 6) {
            throw new BadRequestHttpException('Password must be at least 6 characters');
        }

        if ($this->adminRepository->existsByEmail($email)) {
            throw new ConflictHttpException('Email already registered');
        }

        $admin = AdminEntity::create(
            $name,
            $email,
            $this->passwordService->hash($password)
        );

        $this->adminRepository->save($admin);

        return $this->authPayload($admin);
    }

    public function login(string $email, string $password): array
    {
        try {
            $admin = $this->adminRepository->getByEmail($email);
        } catch (\Throwable) {
            throw new UnauthorizedHttpException('', 'Invalid credentials');
        }

        if (!$this->passwordService->verify($password, $admin->passwordHash)) {
            throw new UnauthorizedHttpException('', 'Invalid credentials');
        }

        return $this->authPayload($admin);
    }

    public function refresh(string $refreshToken): array
    {
        $tokenEntity = $this->refreshTokenService->validateRefreshToken($refreshToken);

        if (!$tokenEntity || $tokenEntity->userType !== 'admin') {
            throw new UnauthorizedHttpException('', 'Invalid or expired refresh token');
        }

        $admin = $this->adminRepository->get($tokenEntity->userId);

        $this->refreshTokenService->revokeRefreshToken($refreshToken);

        return $this->authPayload($admin);
    }

    public function logout(?string $refreshToken): void
    {
        if ($refreshToken) {
            $this->refreshTokenService->revokeRefreshToken($refreshToken);
        }
    }

    private function authPayload(AdminEntity $admin): array
    {
        return [
            'user' => [
                'id' => $admin->id->toRfc4122(),
                'name' => $admin->name,
                'email' => $admin->email,
                'type' => 'admin',
            ],
            'accessToken' => $this->jwtService->createAccessToken(
                $admin->id,
                'admin',
                $admin->email
            ),
            'refreshToken' => $this->refreshTokenService->createRefreshToken(
                $admin->id,
                'admin'
            ),
        ];
    }
}
