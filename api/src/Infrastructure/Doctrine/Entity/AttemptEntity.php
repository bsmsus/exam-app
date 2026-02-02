<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'attempts')]
#[ORM\UniqueConstraint(
    name: 'uniq_exam_student_attempt',
    columns: ['exam_id', 'student_id', 'attempt_number']
)]
#[ORM\Index(columns: ['student_id'], name: 'idx_attempt_student')]
class AttemptEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public Uuid $id;

    #[ORM\ManyToOne(targetEntity: ExamEntity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ExamEntity $exam;

    #[ORM\ManyToOne(targetEntity: StudentEntity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public StudentEntity $student;

    #[ORM\Column(type: 'integer')]
    public int $attemptNumber;

    #[ORM\Column(type: 'string')]
    public string $status;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    public ?\DateTimeImmutable $endedAt = null;

    private function __construct() {}

    public static function create(
        ExamEntity $exam,
        StudentEntity $student,
        int $attemptNumber,
        string $status
    ): self {
        $self = new self();

        $self->exam = $exam;
        $self->student = $student;
        $self->attemptNumber = $attemptNumber;
        $self->status = $status;
        $self->startedAt = new \DateTimeImmutable();

        return $self;
    }
}
