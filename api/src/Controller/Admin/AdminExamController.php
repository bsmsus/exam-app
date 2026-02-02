<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Admin\AdminExamService;
use App\Http\Admin\CreateOrUpdateExamRequest;
use App\Infrastructure\Doctrine\Entity\AdminEntity;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[Route('/admin/exams')]
final class AdminExamController extends AbstractController
{
    public function __construct(
        private AdminExamService $adminExamService,
        private ValidatorInterface $validator
    ) {}

    private function getCurrentAdmin(Request $request): ?AdminEntity
    {
        return $request->attributes->get('currentUser');
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        path: '/admin/exams',
        summary: 'Create a new exam',
        tags: ['Admin Exams'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'maxAttempts', 'cooldownMinutes'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Mathematics Final Exam'),
                    new OA\Property(property: 'maxAttempts', type: 'integer', minimum: 1, example: 3),
                    new OA\Property(property: 'cooldownMinutes', type: 'integer', minimum: 0, example: 60)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Exam created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'examId', type: 'string', format: 'uuid')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        if (!$this->getCurrentAdmin($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $dto = CreateOrUpdateExamRequest::fromArray($request->toArray());
        $violations = $this->validator->validate($dto);

        if ($violations->count() > 0) {
            throw new ValidationFailedException($dto, $violations);
        }

        $examId = $this->adminExamService->create($dto);

        return $this->json(['examId' => $examId->toRfc4122()], 201);
    }

    #[Route('/{examId}', methods: ['PUT'])]
    #[OA\Put(
        path: '/admin/exams/{examId}',
        summary: 'Update an exam (resets all attempts)',
        tags: ['Admin Exams'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'examId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'maxAttempts', 'cooldownMinutes'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Updated Exam Title'),
                    new OA\Property(property: 'maxAttempts', type: 'integer', minimum: 1, example: 5),
                    new OA\Property(property: 'cooldownMinutes', type: 'integer', minimum: 0, example: 120)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Exam updated successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Exam not found')
        ]
    )]
    public function update(string $examId, Request $request): JsonResponse
    {
        if (!$this->getCurrentAdmin($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $dto = CreateOrUpdateExamRequest::fromArray($request->toArray());
        $violations = $this->validator->validate($dto);

        if ($violations->count() > 0) {
            throw new ValidationFailedException($dto, $violations);
        }

        $this->adminExamService->update(
            Uuid::fromString($examId),
            $dto
        );

        return new JsonResponse(null, 204);
    }

    #[Route('/{examId}', methods: ['GET'])]
    #[OA\Get(
        path: '/admin/exams/{examId}',
        summary: 'Get exam details',
        tags: ['Admin Exams'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'examId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Exam details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'maxAttempts', type: 'integer'),
                        new OA\Property(property: 'cooldownMinutes', type: 'integer')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Exam not found')
        ]
    )]
    public function get(string $examId, Request $request): JsonResponse
    {
        if (!$this->getCurrentAdmin($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $this->json(
            $this->adminExamService->get(Uuid::fromString($examId))
        );
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(
        path: '/admin/exams',
        summary: 'List all exams',
        tags: ['Admin Exams'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of exams',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'title', type: 'string'),
                            new OA\Property(property: 'maxAttempts', type: 'integer'),
                            new OA\Property(property: 'cooldownMinutes', type: 'integer')
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        if (!$this->getCurrentAdmin($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $this->json(
            $this->adminExamService->list()
        );
    }

    #[Route('/{examId}/attempts', methods: ['GET'])]
    #[OA\Get(
        path: '/admin/exams/{examId}/attempts',
        summary: 'Get attempt history for an exam',
        tags: ['Admin Exams'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'examId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of attempts',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'studentId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'studentName', type: 'string'),
                            new OA\Property(property: 'studentEmail', type: 'string'),
                            new OA\Property(property: 'attemptNumber', type: 'integer'),
                            new OA\Property(property: 'status', type: 'string', enum: ['IN_PROGRESS', 'COMPLETED']),
                            new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'endedAt', type: 'string', format: 'date-time', nullable: true)
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Exam not found')
        ]
    )]
    public function attempts(string $examId, Request $request): JsonResponse
    {
        if (!$this->getCurrentAdmin($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $this->json(
            $this->adminExamService->listAttempts(
                Uuid::fromString($examId)
            )
        );
    }
}
