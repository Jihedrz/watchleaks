<?php

namespace App\Repository;

use App\Entity\SitesScoresAudit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

class SitesScoresAuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SitesScoresAudit::class);
    }

    public function findForUser(?int $userId, bool $isAdmin): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.owner', 'o')
            ->addSelect('o')
            ->orderBy('a.createdAt', SortDirection::Descending);

        if (!$isAdmin && $userId !== null) {
            $qb->andWhere('a.owner = :owner')
                ->setParameter('owner', $userId);
        }

        return $qb->getQuery()->getResult();
    }
}
