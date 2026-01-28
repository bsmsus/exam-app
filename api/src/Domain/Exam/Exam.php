<?php
declare(strict_types=1);

namespace App\Domain\Exam;

use Ramsey\Uuid\UuidInterface;

final class Exam
{
    public function __construct(
        public readonly UuidInterface $id,
        public readonly string $title,
        public readonly int $maxAttempts,
        public readonly int $cooldownMinutes
    ) {
        if ($maxAttempts < 1 || $maxAttempts > 1000) {
            throw new \InvalidArgumentException('Invalid max attempts');
        }

        if ($cooldownMinutes < 0 || $cooldownMinutes > 525_600) {
            throw new \InvalidArgumentException('Invalid cooldown');
        }
    }
}
