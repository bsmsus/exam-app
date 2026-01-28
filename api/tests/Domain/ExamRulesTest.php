<?php
declare(strict_types=1);

namespace App\Tests\Domain;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use App\Domain\Exam\Exam;
use App\Domain\Attempt\Attempt;
use App\Domain\Attempt\AttemptStatus;
use App\Domain\Rules\ExamRules;

final class ExamRulesTest extends TestCase
{
    public function test_blocks_when_cooldown_not_passed(): void
    {
        $exam = new Exam(
            Uuid::uuid4(),
            'Math',
            3,
            60
        );

        $attempt = new Attempt(
            Uuid::uuid4(),
            1,
            AttemptStatus::IN_PROGRESS,
            new \DateTimeImmutable('2026-01-01 10:00 UTC')
        );

        $attempt->submit(new \DateTimeImmutable('2026-01-01 11:00 UTC'));

        $rules = new ExamRules();

        $result = $rules->canStartAttempt(
            $exam,
            [$attempt],
            new \DateTimeImmutable('2026-01-01 11:30 UTC')
        );

        $this->assertFalse($result->allowed);
        $this->assertNotNull($result->availableAt);
    }
}
