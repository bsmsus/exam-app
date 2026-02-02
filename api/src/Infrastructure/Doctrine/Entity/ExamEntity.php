<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'exams')]
class ExamEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public Uuid $id;

    #[ORM\Column(type: 'string')]
    public string $title;

    #[ORM\Column(type: 'integer')]
    public int $maxAttempts;

    #[ORM\Column(type: 'integer')]
    public int $cooldownMinutes;

    public static function create(
        string $title,
        int $maxAttempts,
        int $cooldownMinutes
    ): self {
        $self = new self();

        $self->title = $title;
        $self->maxAttempts = $maxAttempts;
        $self->cooldownMinutes = $cooldownMinutes;

        return $self;
    }

    public function __construct(?Uuid $id = null, string $title = '', int $maxAttempts = 1, int $cooldownMinutes = 0)
    {
        if ($id !== null) {
            $this->id = $id;
        }

        if ($title !== '') {
            $this->title = $title;
            $this->maxAttempts = $maxAttempts;
            $this->cooldownMinutes = $cooldownMinutes;
        }
    }

    public function update(
        string $title,
        int $maxAttempts,
        int $cooldownMinutes
    ): void {
        $this->title = $title;
        $this->maxAttempts = $maxAttempts;
        $this->cooldownMinutes = $cooldownMinutes;
    }
}
