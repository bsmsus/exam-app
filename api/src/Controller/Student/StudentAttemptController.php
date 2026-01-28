<?php

declare(strict_types=1);

namespace App\Controller\Student;

use App\Infrastructure\Doctrine\AttemptEntity;
use App\Infrastructure\Doctrine\ExamEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/student')]
final class StudentAttemptController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

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

        if ($attempts && end($attempts)->status === 'IN_PROGRESS') {
            return $this->json(['error' => 'Attempt already in progress'], 409);
        }
        $attempts = $this->em->getRepository(AttemptEntity::class)->findBy(
            ['exam' => $exam],
            ['attemptNumber' => 'ASC']
        );

        $last = end($attempts);

        if ($last && $last->endedAt !== null) {
            $availableAt = $last->endedAt->modify(
                '+' . $exam->cooldownMinutes . ' minutes'
            );

            if (new \DateTimeImmutable() < $availableAt) {
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

        $this->em->persist($attempt);
        $this->em->flush();

        return $this->json(
            ['attemptId' => $attempt->id->toRfc4122()],
            201
        );
    }
}
