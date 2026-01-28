<?php
declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'exams')]
class ExamEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public Uuid $id;

    #[ORM\Column(type: 'string')]
    public string $title;

    #[ORM\Column(type: 'integer')]
    public int $maxAttempts;

    #[ORM\Column(type: 'integer')]
    public int $cooldownMinutes;

    public function __construct(
        Uuid $id,
        string $title,
        int $maxAttempts,
        int $cooldownMinutes
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->maxAttempts = $maxAttempts;
        $this->cooldownMinutes = $cooldownMinutes;
    }
}
