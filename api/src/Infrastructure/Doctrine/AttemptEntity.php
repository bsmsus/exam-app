<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

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
    #[ORM\Column(type: 'uuid')]
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
}
