<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\StudentEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class StudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentEntity::class);
    }

    public function save(StudentEntity $student): void
    {
        $em = $this->getEntityManager();
        $em->persist($student);
        $em->flush();
    }

    public function existsByEmail(string $email): bool
    {
        return (bool) $this->findOneBy(['email' => $email]);
    }

    public function getByEmail(string $email): StudentEntity
    {
        $student = $this->findOneBy(['email' => $email]);

        if (!$student) {
            throw new NotFoundHttpException('User not found');
        }

        return $student;
    }

    public function get(Uuid $id): StudentEntity
    {
        $student = $this->find($id);

        if (!$student) {
            throw new NotFoundHttpException('User not found');
        }

        return $student;
    }
}
