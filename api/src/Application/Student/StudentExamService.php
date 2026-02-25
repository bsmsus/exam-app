<?php

declare(strict_types=1);

namespace App\Application\Student;

use App\Infrastructure\Doctrine\Entity\AttemptEntity;
use App\Infrastructure\Doctrine\Entity\StudentEntity;
use App\Infrastructure\Doctrine\Repository\AttemptRepository;
use App\Infrastructure\Doctrine\Repository\ExamRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class StudentExamService
{
    public function __construct(
        private ExamRepository $examRepository,
        private AttemptRepository $attemptRepository,
        private EntityManagerInterface $entityManager
    ) {}

    public function listExams(StudentEntity $student): array
    {
        $results = $this->examRepository->findAllWithAttemptCounts($student);

        return array_map(function (array $row) {
            $exam = $row['exam'];
            $attemptsCount = (int) $row['attemptCount'];

            return [
                'id' => $exam->id->toRfc4122(),
                'title' => $exam->title,
                'maxAttempts' => $exam->maxAttempts,
                'attemptsRemaining' => max(0, $exam->maxAttempts - $attemptsCount),
                'cooldownMinutes' => $exam->cooldownMinutes,
            ];
        }, $results);
    }

    public function examDashboard(StudentEntity $student, Uuid $examId): array
    {
        $exam = $this->examRepository->get($examId);

        $attempts = $this->attemptRepository
            ->findByExamAndStudentOrdered($exam, $student);

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
        $exam = $this->examRepository->get($examId);

        try {
            return $this->entityManager->wrapInTransaction(function () use ($exam, $student): Uuid {
                $attempts = $this->attemptRepository
                    ->findByExamAndStudentForUpdate($exam, $student);

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

                $this->attemptRepository->save($attempt);

                return $attempt->id;
            });
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('No attempts left');
        }
    }

    public function submitAttempt(StudentEntity $student, Uuid $attemptId): void
    {
        $attempt = $this->attemptRepository->find($attemptId);

        if (!$attempt || !$attempt->student->id->equals($student->id)) {
            throw new NotFoundHttpException('Attempt not found');
        }

        if ($attempt->status !== 'IN_PROGRESS') {
            throw new ConflictHttpException('Invalid attempt state');
        }

        $attempt->status = 'COMPLETED';
        $attempt->endedAt = new \DateTimeImmutable();

        $this->attemptRepository->flush();
    }

    public function history(StudentEntity $student): array
    {
        $attempts = $this->attemptRepository->findByStudentOrdered($student);

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
        $exam = $this->examRepository->get($examId);

        $attempt = $this->attemptRepository
            ->findInProgressByExamAndStudent($exam, $student);

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
