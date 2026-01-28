<?php

declare(strict_types=1);

namespace App\Controller\Student;

use App\Infrastructure\Doctrine\ExamEntity;
use App\Infrastructure\Doctrine\AttemptEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/student')]
final class StudentExamController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    /**
     * STUDENT DASHBOARD
     * GET /student/exams/{examId}
     */
    #[Route('/exams/{examId}', methods: ['GET'])]
    public function exam(string $examId): JsonResponse
    {
        $exam = $this->em->find(ExamEntity::class, Uuid::fromString($examId));
        if (!$exam) {
            return $this->json(['error' => 'Exam not found'], 404);
        }

        $attempts = $this->em->getRepository(AttemptEntity::class)
            ->findBy(['exam' => $exam], ['attemptNumber' => 'ASC']);

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
            'cooldownUntil' => $cooldownUntil?->format(DATE_ATOM),
            'canStart' => $canStart,
        ]);
    }

    /**
     * START ATTEMPT
     * POST /student/exams/{examId}/start
     */
    #[Route('/exams/{examId}/start', methods: ['POST'])]
    public function start(string $examId): JsonResponse
    {
        $exam = $this->em->find(ExamEntity::class, Uuid::fromString($examId));
        if (!$exam) {
            return $this->json(['error' => 'Exam not found'], 404);
        }

        $attempts = $this->em->getRepository(AttemptEntity::class)
            ->findBy(['exam' => $exam], ['attemptNumber' => 'ASC']);

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

    /**
     * SUBMIT ATTEMPT
     * POST /student/attempts/{attemptId}/submit
     */
    #[Route('/attempts/{attemptId}/submit', methods: ['POST'])]
    public function submit(string $attemptId): JsonResponse
    {
        $attempt = $this->em->find(
            AttemptEntity::class,
            Uuid::fromString($attemptId)
        );

        if (!$attempt) {
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

    /**
     * STUDENT ATTEMPT HISTORY
     * GET /student/attempts
     */
    #[Route('/attempts', methods: ['GET'])]
    public function history(): JsonResponse
    {
        $attempts = $this->em->getRepository(AttemptEntity::class)
            ->findBy([], ['startedAt' => 'DESC']);

        return $this->json(array_map(
            static fn (AttemptEntity $a) => [
                'attemptNumber' => $a->attemptNumber,
                'status' => $a->status,
                'startedAt' => $a->startedAt->format(DATE_ATOM),
                'endedAt' => $a->endedAt?->format(DATE_ATOM),
            ],
            $attempts
        ));
    }
}
