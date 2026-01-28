<?php

declare(strict_types=1);

namespace App\Domain\Rules;

use App\Domain\Exam\Exam;
use App\Domain\Attempt\Attempt;
use App\Domain\Attempt\AttemptStatus;

final class ExamRules
{
    /**
     * @param Attempt[] $attempts
     */
    public function canStartAttempt(
        Exam $exam,
        array $attempts,
        \DateTimeImmutable $now
    ): CanStartResult {
        $result = CanStartResult::allowed();

        if (count($attempts) >= $exam->maxAttempts) {
            $result = CanStartResult::noAttemptsLeft();
        } else {
            $last = end($attempts);

            if ($last !== false) {
                if ($last->status() === AttemptStatus::IN_PROGRESS) {
                    $result = CanStartResult::blocked('Attempt already in progress');
                } else {
                    $availableAt = $last->endedAt()
                        ->modify("+{$exam->cooldownMinutes} minutes");

                    if ($now < $availableAt) {
                        $result = CanStartResult::cooldown($availableAt);
                    }
                }
            }
        }

        return $result;
    }

}
