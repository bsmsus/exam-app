<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\AttemptEntity;
use App\Infrastructure\Doctrine\Entity\ExamEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class AttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttemptEntity::class);
    }

    public function findByExamOrdered(ExamEntity $exam): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exam = :exam')
            ->setParameter('exam', $exam)
            ->orderBy('a.attemptNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function deleteByExam(ExamEntity $exam): void
    {
        $this->createQueryBuilder('a')
            ->delete()
            ->where('a.exam = :exam')
            ->setParameter('exam', $exam)
            ->getQuery()
            ->execute();
    }
}
