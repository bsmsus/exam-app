<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Application\Auth\StudentAuthService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/student')]
final class StudentAuthController extends AbstractController
{
    public function __construct(
        private StudentAuthService $studentAuthService
    ) {}

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

        if (
            empty($data['name']) ||
            empty($data['email']) ||
            empty($data['password'])
        ) {
            return $this->json(['error' => 'Name, email and password are required'], 400);
        }

        $result = $this->studentAuthService->register(
            trim($data['name']),
            trim($data['email']),
            $data['password']
        );

        return $this->json($result, 201);
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

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Email and password are required'], 400);
        }

        return $this->json(
            $this->studentAuthService->login(
                trim($data['email']),
                $data['password']
            )
        );
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
        $refreshToken = $request->toArray()['refreshToken'] ?? null;

        if (!$refreshToken) {
            return $this->json(['error' => 'Refresh token is required'], 400);
        }

        return $this->json(
            $this->studentAuthService->refresh($refreshToken)
        );
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
        $refreshToken = $request->toArray()['refreshToken'] ?? null;

        $this->studentAuthService->logout($refreshToken);

        return new JsonResponse(null, 204);
    }
}
