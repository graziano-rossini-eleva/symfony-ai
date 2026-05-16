<?php

namespace App\Repository;

use App\Entity\Enrollment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enrollment::class);
    }

    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.deleted = false')
            ->andWhere('e.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('e.enrolledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByCourse(int $courseId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.deleted = false')
            ->andWhere('e.course = :courseId')
            ->setParameter('courseId', $courseId)
            ->orderBy('e.enrolledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndCourse(int $userId, int $courseId): ?Enrollment
    {
        return $this->createQueryBuilder('e')
            ->where('e.deleted = false')
            ->andWhere('e.user = :userId')
            ->andWhere('e.course = :courseId')
            ->setParameter('userId', $userId)
            ->setParameter('courseId', $courseId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
