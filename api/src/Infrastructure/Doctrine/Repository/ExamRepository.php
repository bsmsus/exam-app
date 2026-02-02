<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\ExamEntity;
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
