<?php
declare(strict_types=1);

namespace App\Http\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateOrUpdateExamRequest
{
    #[Assert\NotBlank(message: 'Title is required')]
    #[Assert\Type(type: 'string')]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Title cannot be empty',
        maxMessage: 'Title cannot exceed 255 characters'
    )]
    public string $title;

    #[Assert\NotNull(message: 'maxAttempts is required')]
    #[Assert\Type(type: 'integer')]
    #[Assert\Range(
        min: 1,
        max: 1000,
        notInRangeMessage: 'maxAttempts must be between {{ min }} and {{ max }}'
    )]
    public int $maxAttempts;

    #[Assert\NotNull(message: 'cooldownMinutes is required')]
    #[Assert\Type(type: 'integer')]
    #[Assert\Range(
        min: 0,
        max: 525600,
        notInRangeMessage: 'cooldownMinutes must be between {{ min }} and {{ max }}'
    )]
    public int $cooldownMinutes;
}
