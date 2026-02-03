<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\AdminEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class AdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminEntity::class);
    }

    public function save(AdminEntity $admin): void
    {
        $em = $this->getEntityManager();
        $em->persist($admin);
        $em->flush();
    }

    public function existsByEmail(string $email): bool
    {
        return (bool) $this->findOneBy(['email' => $email]);
    }

    public function getByEmail(string $email): AdminEntity
    {
        $admin = $this->findOneBy(['email' => $email]);

        if (!$admin) {
            throw new NotFoundHttpException('User not found');
        }

        return $admin;
    }

    public function get(Uuid $id): AdminEntity
    {
        $admin = $this->find($id);

        if (!$admin) {
            throw new NotFoundHttpException('User not found');
        }

        return $admin;
    }
}
