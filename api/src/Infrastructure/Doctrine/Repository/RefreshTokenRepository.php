<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\RefreshTokenEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

final class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshTokenEntity::class);
    }

    public function save(RefreshTokenEntity $token): void
    {
        $em = $this->getEntityManager();
        $em->persist($token);
        $em->flush();
    }

    public function findByToken(string $token): ?RefreshTokenEntity
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function deleteByToken(string $token): void
    {
        $em = $this->getEntityManager();

        $entity = $this->findOneBy(['token' => $token]);
        if ($entity) {
            $em->remove($entity);
            $em->flush();
        }
    }

    public function deleteByUser(Uuid $userId, string $userType): void
    {
        $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.userId = :userId')
            ->andWhere('rt.userType = :userType')
            ->setParameter('userId', $userId)
            ->setParameter('userType', $userType)
            ->getQuery()
            ->execute();
    }
}
