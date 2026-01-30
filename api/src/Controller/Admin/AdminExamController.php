<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Admin\UpdateExamService;
use App\Http\Admin\CreateOrUpdateExamRequest;
use App\Infrastructure\Doctrine\ExamEntity;
use App\Infrastructure\Doctrine\AttemptEntity;
use App\Infrastructure\Doctrine\AdminEntity;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $em,
        private UpdateExamService $updateExamService,
        private ValidatorInterface $validator
    ) {
    }

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
        $admin = $this->getCurrentAdmin($request);
        if (!$admin) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->toArray();
        $dto = CreateOrUpdateExamRequest::fromArray($data);
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            return new JsonResponse(
                ['error' => $violations[0]->getMessage()],
                400
            );
        }

        $exam = new ExamEntity(
            Uuid::v4(),
            $dto->title,
            $dto->maxAttempts,
            $dto->cooldownMinutes
        );

        $this->em->persist($exam);
        $this->em->flush();

        return $this->json(
            ['examId' => $exam->id->toRfc4122()],
            201
        );
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
    public function update(
        string $examId,
        Request $request
    ): JsonResponse {
        $admin = $this->getCurrentAdmin($request);
        if (!$admin) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->toArray();
        $dto = CreateOrUpdateExamRequest::fromArray($data);
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($dto, $violations);
        }

        $this->updateExamService->updateExam(
            Uuid::fromString($examId),
            $dto->title,
            $dto->maxAttempts,
            $dto->cooldownMinutes
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
        $admin = $this->getCurrentAdmin($request);
        if (!$admin) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $exam = $this->em->find(
            ExamEntity::class,
            Uuid::fromString($examId)
        );

        if (!$exam) {
            return $this->json(['error' => 'Exam not found'], 404);
        }

        return $this->json([
            'id' => $exam->id->toRfc4122(),
            'title' => $exam->title,
            'maxAttempts' => $exam->maxAttempts,
            'cooldownMinutes' => $exam->cooldownMinutes,
        ]);
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
        $admin = $this->getCurrentAdmin($request);
        if (!$admin) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $exams = $this->em->getRepository(ExamEntity::class)->findAll();

        return $this->json(array_map(
            static fn (ExamEntity $e) => [
                'id' => $e->id->toRfc4122(),
                'title' => $e->title,
                'maxAttempts' => $e->maxAttempts,
                'cooldownMinutes' => $e->cooldownMinutes,
            ],
            $exams
        ));
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
        $admin = $this->getCurrentAdmin($request);
        if (!$admin) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $exam = $this->em->find(
            ExamEntity::class,
            Uuid::fromString($examId)
        );

        if (!$exam) {
            return $this->json(['error' => 'Exam not found'], 404);
        }

        $attempts = $this->em->createQuery(
            'SELECT a
             FROM App\Infrastructure\Doctrine\AttemptEntity a
             WHERE a.exam = :exam
             ORDER BY a.attemptNumber ASC'
        )
        ->setParameter('exam', $exam)
        ->getResult();

        $response = array_map(
            static fn (AttemptEntity $a) => [
                'id' => $a->id->toRfc4122(),
                'studentId' => $a->student->id->toRfc4122(),
                'studentName' => $a->student->name,
                'studentEmail' => $a->student->email,
                'attemptNumber' => $a->attemptNumber,
                'status' => $a->status,
                'startedAt' => $a->startedAt->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
                'endedAt' => $a->endedAt
                    ? $a->endedAt->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM)
                    : null,
            ],
            $attempts
        );

        return $this->json($response);
    }
}
