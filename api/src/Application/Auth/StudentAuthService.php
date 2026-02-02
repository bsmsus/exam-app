<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Infrastructure\Doctrine\Entity\StudentEntity;
use App\Infrastructure\Doctrine\Repository\StudentRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class StudentAuthService
{
    public function __construct(
        private StudentRepository $studentRepository,
        private JwtService $jwtService,
        private PasswordService $passwordService,
        private RefreshTokenService $refreshTokenService
    ) {}

    public function register(string $name, string $email, string $password): array
    {
        if (strlen($password) < 6) {
            throw new BadRequestHttpException('Password must be at least 6 characters');
        }

        if ($this->studentRepository->existsByEmail($email)) {
            throw new ConflictHttpException('Email already registered');
        }

        $student = StudentEntity::create(
            $name,
            $email,
            $this->passwordService->hash($password)
        );

        $this->studentRepository->save($student);

        return $this->authPayload($student);
    }

    public function login(string $email, string $password): array
    {
        try {
            $student = $this->studentRepository->getByEmail($email);
        } catch (\Throwable) {
            throw new UnauthorizedHttpException('', 'Invalid credentials');
        }

        if (!$this->passwordService->verify($password, $student->passwordHash)) {
            throw new UnauthorizedHttpException('', 'Invalid credentials');
        }

        return $this->authPayload($student);
    }

    public function refresh(string $refreshToken): array
    {
        $tokenEntity = $this->refreshTokenService->validateRefreshToken($refreshToken);

        if (!$tokenEntity || $tokenEntity->userType !== 'student') {
            throw new UnauthorizedHttpException('', 'Invalid or expired refresh token');
        }

        $student = $this->studentRepository->get($tokenEntity->userId);

        $this->refreshTokenService->revokeRefreshToken($refreshToken);

        return $this->authPayload($student);
    }

    public function logout(?string $refreshToken): void
    {
        if ($refreshToken) {
            $this->refreshTokenService->revokeRefreshToken($refreshToken);
        }
    }

    private function authPayload(StudentEntity $student): array
    {
        return [
            'user' => [
                'id' => $student->id->toRfc4122(),
                'name' => $student->name,
                'email' => $student->email,
                'type' => 'student',
            ],
            'accessToken' => $this->jwtService->createAccessToken(
                $student->id,
                'student',
                $student->email
            ),
            'refreshToken' => $this->refreshTokenService->createRefreshToken(
                $student->id,
                'student'
            ),
        ];
    }
}
