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

final class AttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttemptEntity::class);
    }

    public function save(AttemptEntity $attempt): void
    {
        $em = $this->getEntityManager();
        $em->persist($attempt);
        $em->flush();
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function get(Uuid $id): AttemptEntity
    {
        $attempt = $this->find($id);

        if (!$attempt) {
            throw new NotFoundHttpException('Attempt not found');
        }

        return $attempt;
    }

    public function countByExamAndStudent(ExamEntity $exam, StudentEntity $student): int
    {
        return $this->count(['exam' => $exam, 'student' => $student]);
    }

    public function findByExamAndStudentOrdered(ExamEntity $exam, StudentEntity $student): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exam = :exam')
            ->andWhere('a.student = :student')
            ->setParameter('exam', $exam)
            ->setParameter('student', $student)
            ->orderBy('a.attemptNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByStudentOrdered(StudentEntity $student): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.exam', 'e')
            ->addSelect('e')
            ->andWhere('a.student = :student')
            ->setParameter('student', $student)
            ->orderBy('a.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findInProgressByExamAndStudent(
        ExamEntity $exam,
        StudentEntity $student
    ): ?AttemptEntity {
        return $this->findOneBy([
            'exam' => $exam,
            'student' => $student,
            'status' => 'IN_PROGRESS',
        ]);
    }

    public function findByExamOrdered(ExamEntity $exam): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.student', 's')
            ->addSelect('s')
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
