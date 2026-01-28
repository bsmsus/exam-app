<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Admin\UpdateExamService;
use App\Http\Admin\CreateOrUpdateExamRequest;
use App\Infrastructure\Doctrine\ExamEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/admin/exams',
    summary: 'Create exam',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'maxAttempts', 'cooldownMinutes'],
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'maxAttempts', type: 'integer'),
                new OA\Property(property: 'cooldownMinutes', type: 'integer'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Exam created'),
        new OA\Response(response: 400, description: 'Validation error')
    ]
)]
#[OA\Info(
    title: 'Exam Management API',
    version: '1.0.0'
)]
#[Route('/admin/exams', methods: ['POST'])]
final class CreateOrUpdateExamController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private UpdateExamService $updateExamService
    ) {
    }

    /**
     * CREATE EXAM
     * POST /admin/exams
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $dto = $this->mapAndValidate($request);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $exam = new ExamEntity(
            Uuid::v4(),
            $dto->title,
            $dto->maxAttempts,
            $dto->cooldownMinutes
        );

        $this->em->persist($exam);
        $this->em->flush();

        return new JsonResponse(
            ['id' => $exam->id->toRfc4122()],
            201
        );
    }

    /**
     * UPDATE EXAM (RESETS ATTEMPTS)
     * PUT /admin/exams/{examId}
     */
    #[Route('/{examId}', methods: ['PUT'])]
    public function update(string $examId, Request $request): JsonResponse
    {
        $dto = $this->mapAndValidate($request);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $this->updateExamService->updateExam(
            Uuid::fromString($examId),
            $dto->title,
            $dto->maxAttempts,
            $dto->cooldownMinutes
        );

        return new JsonResponse(null, 204);
    }

    /**
     * Maps request → DTO and validates
     */
    private function mapAndValidate(Request $request): CreateOrUpdateExamRequest|JsonResponse
    {
        $data = $request->toArray();

        $dto = new CreateOrUpdateExamRequest();
        $dto->title = $data['title'] ?? null;
        $dto->maxAttempts = $data['maxAttempts'] ?? null;
        $dto->cooldownMinutes = $data['cooldownMinutes'] ?? null;

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(
                ['errors' => (string) $errors],
                400
            );
        }

        return $dto;
    }
}
