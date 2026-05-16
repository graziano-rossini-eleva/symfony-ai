<?php

namespace App\Repository;

use App\Entity\Course;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.deleted = false')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPublished(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.deleted = false')
            ->andWhere('c.published = true')
            ->orderBy('c.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByInstructor(int $instructorId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.deleted = false')
            ->andWhere('c.instructor = :instructorId')
            ->setParameter('instructorId', $instructorId)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
