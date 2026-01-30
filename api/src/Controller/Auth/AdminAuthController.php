<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Application\Auth\JwtService;
use App\Application\Auth\PasswordService;
use App\Application\Auth\RefreshTokenService;
use App\Infrastructure\Doctrine\AdminEntity;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/auth/admin')]
final class AdminAuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private JwtService $jwtService,
        private PasswordService $passwordService,
        private RefreshTokenService $refreshTokenService
    ) {
    }

    #[Route('/register', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/admin/register',
        summary: 'Register a new admin',
        tags: ['Admin Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Admin'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                    new OA\Property(property: 'password', type: 'string', minLength: 6, example: 'secret123')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Admin registered successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'email', type: 'string'),
                            new OA\Property(property: 'type', type: 'string', example: 'admin')
                        ], type: 'object'),
                        new OA\Property(property: 'accessToken', type: 'string'),
                        new OA\Property(property: 'refreshToken', type: 'string')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 409, description: 'Email already registered')
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$name || !$email || !$password) {
            return $this->json(['error' => 'Name, email and password are required'], 400);
        }

        if (strlen($password) < 6) {
            return $this->json(['error' => 'Password must be at least 6 characters'], 400);
        }

        $existing = $this->em->getRepository(AdminEntity::class)
            ->findOneBy(['email' => $email]);

        if ($existing) {
            return $this->json(['error' => 'Email already registered'], 409);
        }

        $admin = new AdminEntity(
            Uuid::v4(),
            $name,
            $email,
            $this->passwordService->hash($password)
        );

        $this->em->persist($admin);
        $this->em->flush();

        $accessToken = $this->jwtService->createAccessToken($admin->id, 'admin', $admin->email);
        $refreshToken = $this->refreshTokenService->createRefreshToken($admin->id, 'admin');

        return $this->json([
            'user' => [
                'id' => $admin->id->toRfc4122(),
                'name' => $admin->name,
                'email' => $admin->email,
                'type' => 'admin',
            ],
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
        ], 201);
    }

    #[Route('/login', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/admin/login',
        summary: 'Admin login',
        tags: ['Admin Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'secret123')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'email', type: 'string'),
                            new OA\Property(property: 'type', type: 'string', example: 'admin')
                        ], type: 'object'),
                        new OA\Property(property: 'accessToken', type: 'string'),
                        new OA\Property(property: 'refreshToken', type: 'string')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Invalid credentials')
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            return $this->json(['error' => 'Email and password are required'], 400);
        }

        $admin = $this->em->getRepository(AdminEntity::class)
            ->findOneBy(['email' => $email]);

        if (!$admin || !$this->passwordService->verify($password, $admin->passwordHash)) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }

        $accessToken = $this->jwtService->createAccessToken($admin->id, 'admin', $admin->email);
        $refreshToken = $this->refreshTokenService->createRefreshToken($admin->id, 'admin');

        return $this->json([
            'user' => [
                'id' => $admin->id->toRfc4122(),
                'name' => $admin->name,
                'email' => $admin->email,
                'type' => 'admin',
            ],
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
        ]);
    }

    #[Route('/refresh', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/admin/refresh',
        summary: 'Refresh admin access token',
        tags: ['Admin Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['refreshToken'],
                properties: [
                    new OA\Property(property: 'refreshToken', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token refreshed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'accessToken', type: 'string'),
                        new OA\Property(property: 'refreshToken', type: 'string')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Refresh token is required'),
            new OA\Response(response: 401, description: 'Invalid or expired refresh token')
        ]
    )]
    public function refresh(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $refreshToken = $data['refreshToken'] ?? '';

        if (!$refreshToken) {
            return $this->json(['error' => 'Refresh token is required'], 400);
        }

        $tokenEntity = $this->refreshTokenService->validateRefreshToken($refreshToken);

        if (!$tokenEntity || $tokenEntity->userType !== 'admin') {
            return $this->json(['error' => 'Invalid or expired refresh token'], 401);
        }

        $admin = $this->em->find(AdminEntity::class, $tokenEntity->userId);

        if (!$admin) {
            return $this->json(['error' => 'User not found'], 401);
        }

        $this->refreshTokenService->revokeRefreshToken($refreshToken);

        $newAccessToken = $this->jwtService->createAccessToken($admin->id, 'admin', $admin->email);
        $newRefreshToken = $this->refreshTokenService->createRefreshToken($admin->id, 'admin');

        return $this->json([
            'user' => [
                'id' => $admin->id->toRfc4122(),
                'name' => $admin->name,
                'email' => $admin->email,
                'type' => 'admin',
            ],
            'accessToken' => $newAccessToken,
            'refreshToken' => $newRefreshToken,
        ]);
    }

    #[Route('/logout', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/admin/logout',
        summary: 'Admin logout',
        tags: ['Admin Auth'],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'refreshToken', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Logged out successfully')
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $refreshToken = $data['refreshToken'] ?? '';

        if ($refreshToken) {
            $this->refreshTokenService->revokeRefreshToken($refreshToken);
        }

        return new JsonResponse(null, 204);
    }
}
