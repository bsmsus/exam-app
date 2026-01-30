<?php

declare(strict_types=1);

namespace App\Controller\Student;

use App\Infrastructure\Doctrine\ExamEntity;
use App\Infrastructure\Doctrine\AttemptEntity;
use App\Infrastructure\Doctrine\StudentEntity;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $em
    ) {
    }

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

        $exams = $this->em->getRepository(ExamEntity::class)->findAll();

        $result = [];
        foreach ($exams as $exam) {
            $attemptsCount = $this->em->getRepository(AttemptEntity::class)
                ->count(['exam' => $exam, 'student' => $student]);

            $result[] = [
                'id' => $exam->id->toRfc4122(),
                'title' => $exam->title,
                'maxAttempts' => $exam->maxAttempts,
                'attemptsRemaining' => max(0, $exam->maxAttempts - $attemptsCount),
                'cooldownMinutes' => $exam->cooldownMinutes,
            ];
        }

        return $this->json($result);
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

        $exam = $this->em->find(ExamEntity::class, Uuid::fromString($examId));
        if (!$exam) {
            return $this->json(['error' => 'Exam not found'], 404);
        }

        $attempts = $this->em->getRepository(AttemptEntity::class)
            ->findBy(['exam' => $exam, 'student' => $student], ['attemptNumber' => 'ASC']);

        $now = new \DateTimeImmutable();
        $attemptsCount = count($attempts);
        $remaining = max(0, $exam->maxAttempts - $attemptsCount);

        $cooldownUntil = null;
        $canStart = true;

        if ($attemptsCount > 0) {
            $last = end($attempts);

            if ($last->status === 'IN_PROGRESS') {
                $canStart = false;
            } elseif ($last->endedAt !== null) {
                $cooldownUntil = $last->endedAt
                    ->modify("+{$exam->cooldownMinutes} minutes");

                if ($now < $cooldownUntil) {
                    $canStart = false;
                }
            }
        }

        if ($remaining === 0) {
            $canStart = false;
        }

        return $this->json([
            'title' => $exam->title,
            'maxAttempts' => $exam->maxAttempts,
            'attemptsRemaining' => $remaining,
            'cooldownMinutes' => $exam->cooldownMinutes,
            'cooldownUntil' => $cooldownUntil?->format(DATE_ATOM),
            'canStart' => $canStart,
        ]);
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

        $exam = $this->em->find(ExamEntity::class, Uuid::fromString($examId));
        if (!$exam) {
            return $this->json(['error' => 'Exam not found'], 404);
        }

        $attempts = $this->em->getRepository(AttemptEntity::class)
            ->findBy(['exam' => $exam, 'student' => $student], ['attemptNumber' => 'ASC']);

        if (count($attempts) >= $exam->maxAttempts) {
            return $this->json(['error' => 'No attempts left'], 409);
        }

        if ($attempts) {
            $last = end($attempts);

            if ($last->status === 'IN_PROGRESS') {
                return $this->json(['error' => 'Attempt already in progress'], 409);
            }

            $availableAt = $last->endedAt
                ? $last->endedAt->modify("+{$exam->cooldownMinutes} minutes")
                : null;

            if ($availableAt && new \DateTimeImmutable() < $availableAt) {
                return new JsonResponse(
                    [
                        'error' => sprintf(
                            'Your next attempt will be available at %s',
                            $availableAt->format('d M Y, h:i A')
                        )
                    ],
                    409
                );
            }
        }

        $attempt = new AttemptEntity();
        $attempt->id = Uuid::v4();
        $attempt->exam = $exam;
        $attempt->student = $student;
        $attempt->attemptNumber = count($attempts) + 1;
        $attempt->status = 'IN_PROGRESS';
        $attempt->startedAt = new \DateTimeImmutable();
        $attempt->endedAt = null;

        $this->em->persist($attempt);
        $this->em->flush();

        return $this->json([
            'attemptId' => $attempt->id->toRfc4122()
        ], 201);
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

        $attempt = $this->em->find(
            AttemptEntity::class,
            Uuid::fromString($attemptId)
        );

        if (!$attempt) {
            return $this->json(['error' => 'Attempt not found'], 404);
        }

        if ($attempt->student->id->toRfc4122() !== $student->id->toRfc4122()) {
            return $this->json(['error' => 'Attempt not found'], 404);
        }

        if ($attempt->status !== 'IN_PROGRESS') {
            return $this->json(['error' => 'Invalid attempt state'], 409);
        }

        $attempt->status = 'COMPLETED';
        $attempt->endedAt = new \DateTimeImmutable();

        $this->em->flush();

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

        $attempts = $this->em->getRepository(AttemptEntity::class)
            ->findBy(['student' => $student], ['startedAt' => 'DESC']);

        return $this->json(array_map(
            static fn (AttemptEntity $a) => [
                'id' => $a->id->toRfc4122(),
                'examId' => $a->exam->id->toRfc4122(),
                'examTitle' => $a->exam->title,
                'attemptNumber' => $a->attemptNumber,
                'status' => $a->status,
                'startedAt' => $a->startedAt->format(DATE_ATOM),
                'endedAt' => $a->endedAt?->format(DATE_ATOM),
            ],
            $attempts
        ));
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

        $exam = $this->em->find(ExamEntity::class, Uuid::fromString($examId));
        if (!$exam) {
            return $this->json(['error' => 'Exam not found'], 404);
        }

        $attempt = $this->em->getRepository(AttemptEntity::class)
            ->findOneBy([
                'exam' => $exam,
                'student' => $student,
                'status' => 'IN_PROGRESS'
            ]);

        if (!$attempt) {
            return $this->json(['currentAttempt' => null]);
        }

        return $this->json([
            'currentAttempt' => [
                'id' => $attempt->id->toRfc4122(),
                'attemptNumber' => $attempt->attemptNumber,
                'startedAt' => $attempt->startedAt->format(DATE_ATOM),
            ]
        ]);
    }
}
