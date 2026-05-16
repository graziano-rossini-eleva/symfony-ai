<?php

namespace App\Repository;

use App\Entity\LessonProgress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LessonProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LessonProgress::class);
    }

    public function findByEnrollment(int $enrollmentId): array
    {
        return $this->createQueryBuilder('lp')
            ->where('lp.deleted = false')
            ->andWhere('lp.enrollment = :enrollmentId')
            ->setParameter('enrollmentId', $enrollmentId)
            ->getQuery()
            ->getResult();
    }

    public function countCompletedByEnrollment(int $enrollmentId): int
    {
        return (int) $this->createQueryBuilder('lp')
            ->select('COUNT(lp.id)')
            ->where('lp.deleted = false')
            ->andWhere('lp.enrollment = :enrollmentId')
            ->setParameter('enrollmentId', $enrollmentId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
