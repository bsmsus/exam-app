<?php

declare(strict_types=1);

namespace App\Application\Admin;

use App\Infrastructure\Doctrine\ExamEntity;
use App\Infrastructure\Doctrine\AttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class UpdateExamService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttemptRepository $attempts
    ) {
    }

    public function updateExam(
        Uuid $examId,
        string $title,
        int $maxAttempts,
        int $cooldownMinutes
    ): void {
        $this->em->wrapInTransaction(function () use (
            $examId,
            $title,
            $maxAttempts,
            $cooldownMinutes
        ) {
            /** @var ExamEntity|null $exam */
            $exam = $this->em->find(ExamEntity::class, $examId);

            if ($exam === null) {
                throw new \RuntimeException('Exam not found');
            }

            $exam->title = $title;
            $exam->maxAttempts = $maxAttempts;
            $exam->cooldownMinutes = $cooldownMinutes;

            $this->attempts->deleteByExam($exam);

            $this->em->flush();
        });
    }
}
