<?php

declare(strict_types=1);

namespace App\Application\Student;

use App\Infrastructure\Doctrine\Entity\AttemptEntity;
use App\Infrastructure\Doctrine\Entity\ExamEntity;
use App\Infrastructure\Doctrine\Entity\StudentEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class StudentExamService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function listExams(StudentEntity $student): array
    {
        $exams = $this->em->getRepository(ExamEntity::class)->findAll();

        return array_map(function (ExamEntity $exam) use ($student) {
            $attemptsCount = $this->em->getRepository(AttemptEntity::class)
                ->count(['exam' => $exam, 'student' => $student]);

            return [
                'id' => $exam->id->toRfc4122(),
                'title' => $exam->title,
                'maxAttempts' => $exam->maxAttempts,
                'attemptsRemaining' => max(0, $exam->maxAttempts - $attemptsCount),
                'cooldownMinutes' => $exam->cooldownMinutes,
            ];
        }, $exams);
    }

    public function examDashboard(StudentEntity $student, Uuid $examId): array
    {
        $exam = $this->em->find(ExamEntity::class, $examId);
        if (!$exam) {
            throw new NotFoundHttpException('Exam not found');
        }

        $attempts = $this->em->getRepository(AttemptEntity::class)
            ->findBy(['exam' => $exam, 'student' => $student], ['attemptNumber' => 'ASC']);

        $attemptsCount = count($attempts);
        $remaining = max(0, $exam->maxAttempts - $attemptsCount);

        $now = new \DateTimeImmutable();
        $cooldownUntil = null;
        $canStart = true;

        if ($attemptsCount > 0) {
            $last = end($attempts);

            if ($last->status === 'IN_PROGRESS') {
                $canStart = false;
            } elseif ($last->endedAt) {
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

        return [
            'title' => $exam->title,
            'maxAttempts' => $exam->maxAttempts,
            'attemptsRemaining' => $remaining,
            'cooldownMinutes' => $exam->cooldownMinutes,
            'cooldownUntil' => $cooldownUntil?->format(DATE_ATOM),
            'canStart' => $canStart,
        ];
    }

    public function startAttempt(StudentEntity $student, Uuid $examId): Uuid
    {
        $exam = $this->em->find(ExamEntity::class, $examId);
        if (!$exam) {
            throw new NotFoundHttpException('Exam not found');
        }

        $attempts = $this->em->getRepository(AttemptEntity::class)
            ->findBy(['exam' => $exam, 'student' => $student], ['attemptNumber' => 'ASC']);

        if (count($attempts) >= $exam->maxAttempts) {
            throw new ConflictHttpException('No attempts left');
        }

        if ($attempts) {
            $last = end($attempts);

            if ($last->status === 'IN_PROGRESS') {
                throw new ConflictHttpException('Attempt already in progress');
            }

            if ($last->endedAt) {
                $availableAt = $last->endedAt
                    ->modify("+{$exam->cooldownMinutes} minutes");

                if (new \DateTimeImmutable() < $availableAt) {
                    throw new ConflictHttpException(
                        sprintf('Next attempt available at %s', $availableAt->format(DATE_ATOM))
                    );
                }
            }
        }

        $attempt = AttemptEntity::create(
            $exam,
            $student,
            count($attempts) + 1,
            'IN_PROGRESS'
        );

        $this->em->persist($attempt);
        $this->em->flush();

        return $attempt->id;
    }

    public function submitAttempt(StudentEntity $student, Uuid $attemptId): void
    {
        $attempt = $this->em->find(AttemptEntity::class, $attemptId);
        if (!$attempt || $attempt->student->id !== $student->id) {
            throw new NotFoundHttpException('Attempt not found');
        }

        if ($attempt->status !== 'IN_PROGRESS') {
            throw new ConflictHttpException('Invalid attempt state');
        }

        $attempt->status = 'COMPLETED';
        $attempt->endedAt = new \DateTimeImmutable();

        $this->em->flush();
    }

    public function history(StudentEntity $student): array
    {
        $attempts = $this->em->getRepository(AttemptEntity::class)
            ->findBy(['student' => $student], ['startedAt' => 'DESC']);

        return array_map(
            static fn(AttemptEntity $a) => [
                'id' => $a->id->toRfc4122(),
                'examId' => $a->exam->id->toRfc4122(),
                'examTitle' => $a->exam->title,
                'attemptNumber' => $a->attemptNumber,
                'status' => $a->status,
                'startedAt' => $a->startedAt->format(DATE_ATOM),
                'endedAt' => $a->endedAt?->format(DATE_ATOM),
            ],
            $attempts
        );
    }

    public function currentAttempt(StudentEntity $student, Uuid $examId): ?array
    {
        $exam = $this->em->find(ExamEntity::class, $examId);
        if (!$exam) {
            throw new NotFoundHttpException('Exam not found');
        }

        $attempt = $this->em->getRepository(AttemptEntity::class)->findOneBy([
            'exam' => $exam,
            'student' => $student,
            'status' => 'IN_PROGRESS'
        ]);

        if (!$attempt) {
            return null;
        }

        return [
            'id' => $attempt->id->toRfc4122(),
            'attemptNumber' => $attempt->attemptNumber,
            'startedAt' => $attempt->startedAt->format(DATE_ATOM),
        ];
    }
}
