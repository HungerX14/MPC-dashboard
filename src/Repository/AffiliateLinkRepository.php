<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AffiliateLink;
use App\Entity\AffiliateProgram;
use App\Entity\Site;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AffiliateLink>
 */
class AffiliateLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AffiliateLink::class);
    }

    public function save(AffiliateLink $link, bool $flush = true): void
    {
        $this->getEntityManager()->persist($link);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AffiliateLink $link, bool $flush = true): void
    {
        $this->getEntityManager()->remove($link);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByShortCode(string $shortCode): ?AffiliateLink
    {
        return $this->findOneBy(['shortCode' => $shortCode]);
    }

    /**
     * @return AffiliateLink[]
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AffiliateLink[]
     */
    public function findActiveByOwner(User $owner): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.owner = :owner')
            ->andWhere('l.status = :status')
            ->setParameter('owner', $owner)
            ->setParameter('status', 'active')
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AffiliateLink[]
     */
    public function findByProgram(AffiliateProgram $program): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.program = :program')
            ->setParameter('program', $program)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AffiliateLink[]
     */
    public function findBySite(Site $site): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.site = :site')
            ->setParameter('site', $site)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalClicksByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('SUM(l.totalClicks)')
            ->where('l.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function getTotalEarningsByOwner(User $owner): string
    {
        return (string) ($this->createQueryBuilder('l')
            ->select('SUM(l.totalEarnings)')
            ->where('l.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult() ?? '0.00');
    }

    public function getTotalConversionsByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('SUM(l.conversions)')
            ->where('l.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    /**
     * @return AffiliateLink[]
     */
    public function findTopPerformingByOwner(User $owner, int $limit = 5): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('l.totalClicks', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AffiliateLink[]
     */
    public function findTopEarningByOwner(User $owner, int $limit = 5): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('l.totalEarnings', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AffiliateLink[]
     */
    public function findRecentlyClickedByOwner(User $owner, int $limit = 5): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.owner = :owner')
            ->andWhere('l.lastClickAt IS NOT NULL')
            ->setParameter('owner', $owner)
            ->orderBy('l.lastClickAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{totalClicks: int, uniqueClicks: int, conversions: int, earnings: string}
     */
    public function getStatsByOwner(User $owner): array
    {
        $result = $this->createQueryBuilder('l')
            ->select('SUM(l.totalClicks) as totalClicks, SUM(l.uniqueClicks) as uniqueClicks, SUM(l.conversions) as conversions, SUM(l.totalEarnings) as earnings')
            ->where('l.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleResult();

        return [
            'totalClicks' => (int) ($result['totalClicks'] ?? 0),
            'uniqueClicks' => (int) ($result['uniqueClicks'] ?? 0),
            'conversions' => (int) ($result['conversions'] ?? 0),
            'earnings' => (string) ($result['earnings'] ?? '0.00'),
        ];
    }
}
