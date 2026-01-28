<?php

declare(strict_types=1);

namespace App\Http\Student;

use Symfony\Component\Validator\Constraints as Assert;

final class StartAttemptRequest
{
    #[Assert\NotBlank]
    public string $examId;
}
