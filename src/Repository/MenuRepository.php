<?php

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    public function findRootItems(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.deleted = false')
            ->andWhere('m.visible = true')
            ->andWhere('m.parent IS NULL')
            ->orderBy('m.positionOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findVisibleTree(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.children', 'c')
            ->addSelect('c')
            ->where('m.deleted = false')
            ->andWhere('m.visible = true')
            ->andWhere('m.parent IS NULL')
            ->orderBy('m.positionOrder', 'ASC')
            ->addOrderBy('c.positionOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
