<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AffiliateProgram;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AffiliateProgram>
 */
class AffiliateProgramRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AffiliateProgram::class);
    }

    public function save(AffiliateProgram $program, bool $flush = true): void
    {
        $this->getEntityManager()->persist($program);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AffiliateProgram $program, bool $flush = true): void
    {
        $this->getEntityManager()->remove($program);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return AffiliateProgram[]
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AffiliateProgram[]
     */
    public function findActiveByOwner(User $owner): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('p.status = :status')
            ->setParameter('owner', $owner)
            ->setParameter('status', 'active')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AffiliateProgram[]
     */
    public function findByOwnerAndStatus(User $owner, string $status): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('p.status = :status')
            ->setParameter('owner', $owner)
            ->setParameter('status', $status)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.owner = :owner')
            ->andWhere('p.status = :status')
            ->setParameter('owner', $owner)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function getNetworkStats(User $owner): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('p.network, COUNT(p.id) as count')
            ->where('p.owner = :owner')
            ->andWhere('p.network IS NOT NULL')
            ->setParameter('owner', $owner)
            ->groupBy('p.network')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $row) {
            $stats[$row['network']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function getCategoryStats(User $owner): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('p.category, COUNT(p.id) as count')
            ->where('p.owner = :owner')
            ->andWhere('p.category IS NOT NULL')
            ->setParameter('owner', $owner)
            ->groupBy('p.category')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $row) {
            $stats[$row['category']] = (int) $row['count'];
        }

        return $stats;
    }
}
