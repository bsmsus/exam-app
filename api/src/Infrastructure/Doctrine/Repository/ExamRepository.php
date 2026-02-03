<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\AttemptEntity;
use App\Infrastructure\Doctrine\Entity\ExamEntity;
use App\Infrastructure\Doctrine\Entity\StudentEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class ExamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExamEntity::class);
    }

    public function findAllWithAttemptCounts(StudentEntity $student): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin(
                AttemptEntity::class,
                'a',
                'WITH',
                'a.exam = e AND a.student = :student'
            )
            ->select('e as exam, COUNT(a.id) as attemptCount')
            ->setParameter('student', $student)
            ->groupBy('e.id')
            ->getQuery()
            ->getResult();
    }

    public function save(ExamEntity $exam): void
    {
        $em = $this->getEntityManager();

        $em->persist($exam);
        $em->flush();
    }

    public function get(Uuid $id): ExamEntity
    {
        $exam = $this->find($id);

        if (!$exam) {
            throw new NotFoundHttpException('Exam not found');
        }

        return $exam;
    }

    public function flush(): void
    {
        $em = $this->getEntityManager();
        $em->flush();
    }
}
