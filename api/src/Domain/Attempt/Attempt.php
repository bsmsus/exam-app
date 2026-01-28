<?php
declare(strict_types=1);

namespace App\Domain\Attempt;

use Ramsey\Uuid\UuidInterface;

final class Attempt
{
    private ?\DateTimeImmutable $endedAt = null;

    public function __construct(
        public readonly UuidInterface $id,
        public readonly int $attemptNumber,
        private AttemptStatus $status,
        public readonly \DateTimeImmutable $startedAt
    ) {}

    public function submit(\DateTimeImmutable $endedAt): void
    {
        if ($this->status !== AttemptStatus::IN_PROGRESS) {
            throw new \LogicException('Only in-progress attempts can be submitted');
        }

        $this->status = AttemptStatus::COMPLETED;
        $this->endedAt = $endedAt;
    }

    public function status(): AttemptStatus
    {
        return $this->status;
    }

    public function endedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }
}
