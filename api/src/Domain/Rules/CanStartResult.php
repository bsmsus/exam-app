<?php
declare(strict_types=1);

namespace App\Domain\Rules;

final class CanStartResult
{
    private function __construct(
        public readonly bool $allowed,
        public readonly ?\DateTimeImmutable $availableAt,
        public readonly ?string $reason
    ) {}

    public static function allowed(): self
    {
        return new self(true, null, null);
    }

    public static function noAttemptsLeft(): self
    {
        return new self(false, null, 'No attempts left');
    }

    public static function cooldown(\DateTimeImmutable $at): self
    {
        return new self(false, $at, 'Cooldown active');
    }

    public static function blocked(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
