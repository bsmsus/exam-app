<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Application\Auth\JwtService;
use App\Application\Auth\PasswordService;
use App\Application\Auth\RefreshTokenService;
use App\Infrastructure\Doctrine\StudentEntity;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/auth/student')]
final class StudentAuthController extends AbstractController
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
        path: '/auth/student/register',
        summary: 'Register a new student',
        tags: ['Student Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Jane Student'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'student@example.com'),
                    new OA\Property(property: 'password', type: 'string', minLength: 6, example: 'secret123')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Student registered successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'email', type: 'string'),
                            new OA\Property(property: 'type', type: 'string', example: 'student')
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

        $existing = $this->em->getRepository(StudentEntity::class)
            ->findOneBy(['email' => $email]);

        if ($existing) {
            return $this->json(['error' => 'Email already registered'], 409);
        }

        $student = new StudentEntity(
            Uuid::v4(),
            $name,
            $email,
            $this->passwordService->hash($password)
        );

        $this->em->persist($student);
        $this->em->flush();

        $accessToken = $this->jwtService->createAccessToken($student->id, 'student', $student->email);
        $refreshToken = $this->refreshTokenService->createRefreshToken($student->id, 'student');

        return $this->json([
            'user' => [
                'id' => $student->id->toRfc4122(),
                'name' => $student->name,
                'email' => $student->email,
                'type' => 'student',
            ],
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
        ], 201);
    }

    #[Route('/login', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/student/login',
        summary: 'Student login',
        tags: ['Student Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'student@example.com'),
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
                            new OA\Property(property: 'type', type: 'string', example: 'student')
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

        $student = $this->em->getRepository(StudentEntity::class)
            ->findOneBy(['email' => $email]);

        if (!$student || !$this->passwordService->verify($password, $student->passwordHash)) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }

        $accessToken = $this->jwtService->createAccessToken($student->id, 'student', $student->email);
        $refreshToken = $this->refreshTokenService->createRefreshToken($student->id, 'student');

        return $this->json([
            'user' => [
                'id' => $student->id->toRfc4122(),
                'name' => $student->name,
                'email' => $student->email,
                'type' => 'student',
            ],
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
        ]);
    }

    #[Route('/refresh', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/student/refresh',
        summary: 'Refresh student access token',
        tags: ['Student Auth'],
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

        if (!$tokenEntity || $tokenEntity->userType !== 'student') {
            return $this->json(['error' => 'Invalid or expired refresh token'], 401);
        }

        $student = $this->em->find(StudentEntity::class, $tokenEntity->userId);

        if (!$student) {
            return $this->json(['error' => 'User not found'], 401);
        }

        $this->refreshTokenService->revokeRefreshToken($refreshToken);

        $newAccessToken = $this->jwtService->createAccessToken($student->id, 'student', $student->email);
        $newRefreshToken = $this->refreshTokenService->createRefreshToken($student->id, 'student');

        return $this->json([
            'user' => [
                'id' => $student->id->toRfc4122(),
                'name' => $student->name,
                'email' => $student->email,
                'type' => 'student',
            ],
            'accessToken' => $newAccessToken,
            'refreshToken' => $newRefreshToken,
        ]);
    }

    #[Route('/logout', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/student/logout',
        summary: 'Student logout',
        tags: ['Student Auth'],
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
