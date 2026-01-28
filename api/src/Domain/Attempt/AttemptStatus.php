<?php
declare(strict_types=1);

namespace App\Domain\Attempt;

enum AttemptStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
}
