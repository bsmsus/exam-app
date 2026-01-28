<?php

declare(strict_types=1);

namespace App\Application\Student;

final class CanStartResult
{
    private function __construct(
        public bool $allowed,
        public ?\DateTimeImmutable $availableAt = null
    ) {
    }

    public static function allowed(): self
    {
        return new self(true);
    }

    public static function noAttemptsLeft(): self
    {
        return new self(false);
    }

    public static function cooldown(\DateTimeImmutable $at): self
    {
        return new self(false, $at);
    }
}
