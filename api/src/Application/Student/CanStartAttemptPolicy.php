<?php

declare(strict_types=1);

namespace App\Application\Student;

use App\Infrastructure\Doctrine\ExamEntity;
use App\Infrastructure\Doctrine\AttemptEntity;

final class CanStartAttemptPolicy
{
    /**
     * @param AttemptEntity[] $attempts
     */
    public function check(
        ExamEntity $exam,
        array $attempts,
        \DateTimeImmutable $now
    ): CanStartResult {
        if (count($attempts) >= $exam->maxAttempts) {
            return CanStartResult::noAttemptsLeft();
        }

        $last = end($attempts);
        if (!$last || !$last->endedAt) {
            return CanStartResult::allowed();
        }

        $availableAt = $last->endedAt
            ->modify(sprintf('+%d minutes', $exam->cooldownMinutes));

        if ($now < $availableAt) {
            return CanStartResult::cooldown($availableAt);
        }

        return CanStartResult::allowed();
    }
}
