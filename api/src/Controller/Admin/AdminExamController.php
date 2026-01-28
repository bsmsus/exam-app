<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Admin\UpdateExamService;
use App\Infrastructure\Doctrine\ExamEntity;
use App\Infrastructure\Doctrine\AttemptEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/exams')]
final class AdminExamController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
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
        $data = $request->toArray();

        $exam = new ExamEntity(
            Uuid::v4(),
            (string) $data['title'],
            (int) $data['maxAttempts'],
            (int) $data['cooldownMinutes']
        );

        $this->em->persist($exam);
        $this->em->flush();

        return $this->json(
            ['id' => $exam->id->toRfc4122()],
            201
        );
    }

    /**
     * UPDATE EXAM (RESETS ATTEMPTS)
     * PUT /admin/exams/{examId}
     */
    #[Route('/{examId}', methods: ['PUT'])]
    public function update(
        string $examId,
        Request $request
    ): JsonResponse {
        $data = $request->toArray();

        $this->updateExamService->updateExam(
            Uuid::fromString($examId),
            (string) $data['title'],
            (int) $data['maxAttempts'],
            (int) $data['cooldownMinutes']
        );

        return new JsonResponse(null, 204);
    }

    /**
     * ADMIN VIEW — ATTEMPT HISTORY
     * GET /admin/exams/{examId}/attempts
     * Times in UTC
     */
    #[Route('/{examId}/attempts', methods: ['GET'])]
    public function attempts(string $examId): JsonResponse
    {
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
