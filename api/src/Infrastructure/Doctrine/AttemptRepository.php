<?php
declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;

final class AttemptRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function deleteByExam(ExamEntity $exam): void
    {
        $this->em->createQuery(
            'DELETE FROM App\Infrastructure\Doctrine\AttemptEntity a WHERE a.exam = :exam'
        )
        ->setParameter('exam', $exam)
        ->execute();
    }
}
