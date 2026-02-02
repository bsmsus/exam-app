<?php

declare(strict_types=1);

namespace App\Controller\Student;

use App\Application\Student\StudentExamService;
use App\Infrastructure\Doctrine\Entity\StudentEntity;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/student')]
final class StudentExamController extends AbstractController
{
    public function __construct(
        private StudentExamService $studentExamService
    ) {}

    private function getCurrentStudent(Request $request): ?StudentEntity
    {
        return $request->attributes->get('currentUser');
    }

    #[Route('/exams', methods: ['GET'])]
    #[OA\Get(
        path: '/student/exams',
        summary: 'List all available exams for the student',
        tags: ['Student Exams'],
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
                            new OA\Property(property: 'attemptsRemaining', type: 'integer'),
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
        $student = $this->getCurrentStudent($request);
        if (!$student) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $this->json(
            $this->studentExamService->listExams($student)
        );
    }

    #[Route('/exams/{examId}', methods: ['GET'])]
    #[OA\Get(
        path: '/student/exams/{examId}',
        summary: 'Get student dashboard for an exam',
        description: 'Returns exam details, attempt status, and whether the student can start a new attempt',
        tags: ['Student Exams'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'examId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Exam dashboard',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'maxAttempts', type: 'integer'),
                        new OA\Property(property: 'attemptsRemaining', type: 'integer'),
                        new OA\Property(property: 'cooldownMinutes', type: 'integer'),
                        new OA\Property(property: 'cooldownUntil', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'canStart', type: 'boolean')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Exam not found')
        ]
    )]
    public function exam(string $examId, Request $request): JsonResponse
    {
        $student = $this->getCurrentStudent($request);
        if (!$student) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $this->json(
            $this->studentExamService->examDashboard(
                $student,
                Uuid::fromString($examId)
            )
        );
    }

    #[Route('/exams/{examId}/start', methods: ['POST'])]
    #[OA\Post(
        path: '/student/exams/{examId}/start',
        summary: 'Start a new exam attempt',
        tags: ['Student Exams'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'examId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Attempt started',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'attemptId', type: 'string', format: 'uuid')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Exam not found'),
            new OA\Response(response: 409, description: 'Cannot start attempt (no attempts left, cooldown active, or attempt in progress)')
        ]
    )]
    public function start(string $examId, Request $request): JsonResponse
    {
        $student = $this->getCurrentStudent($request);
        if (!$student) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $attemptId = $this->studentExamService->startAttempt(
            $student,
            Uuid::fromString($examId)
        );

        return $this->json(['attemptId' => $attemptId->toRfc4122()], 201);
    }

    #[Route('/attempts/{attemptId}/submit', methods: ['POST'])]
    #[OA\Post(
        path: '/student/attempts/{attemptId}/submit',
        summary: 'Submit an exam attempt',
        tags: ['Student Exams'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'attemptId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Attempt submitted successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Attempt not found'),
            new OA\Response(response: 409, description: 'Invalid attempt state')
        ]
    )]
    public function submit(string $attemptId, Request $request): JsonResponse
    {
        $student = $this->getCurrentStudent($request);
        if (!$student) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $this->studentExamService->submitAttempt(
            $student,
            Uuid::fromString($attemptId)
        );

        return new JsonResponse(null, 204);
    }

    #[Route('/attempts', methods: ['GET'])]
    #[OA\Get(
        path: '/student/attempts',
        summary: 'Get student attempt history',
        tags: ['Student Exams'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of student attempts',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'examId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'examTitle', type: 'string'),
                            new OA\Property(property: 'attemptNumber', type: 'integer'),
                            new OA\Property(property: 'status', type: 'string', enum: ['IN_PROGRESS', 'COMPLETED']),
                            new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'endedAt', type: 'string', format: 'date-time', nullable: true)
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function history(Request $request): JsonResponse
    {
        $student = $this->getCurrentStudent($request);
        if (!$student) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $this->json(
            $this->studentExamService->history($student)
        );
    }

    #[Route('/exams/{examId}/current-attempt', methods: ['GET'])]
    #[OA\Get(
        path: '/student/exams/{examId}/current-attempt',
        summary: 'Get current in-progress attempt for an exam',
        tags: ['Student Exams'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'examId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current attempt or null',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'currentAttempt',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'attemptNumber', type: 'integer'),
                                new OA\Property(property: 'startedAt', type: 'string', format: 'date-time')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Exam not found')
        ]
    )]
    public function currentAttempt(string $examId, Request $request): JsonResponse
    {
        $student = $this->getCurrentStudent($request);
        if (!$student) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $this->json([
            'currentAttempt' => $this->studentExamService->currentAttempt(
                $student,
                Uuid::fromString($examId)
            )
        ]);
    }
}
