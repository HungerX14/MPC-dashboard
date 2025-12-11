<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AffiliateLink;
use App\Entity\ClickTracking;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClickTracking>
 */
class ClickTrackingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClickTracking::class);
    }

    public function save(ClickTracking $click, bool $flush = true): void
    {
        $this->getEntityManager()->persist($click);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return ClickTracking[]
     */
    public function findByLink(AffiliateLink $link, int $limit = 100): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.link = :link')
            ->setParameter('link', $link)
            ->orderBy('c.clickedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function hasRecentClickFromIp(AffiliateLink $link, string $ipHash, int $minutes = 60): bool
    {
        $since = new \DateTimeImmutable("-{$minutes} minutes");

        $count = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.link = :link')
            ->andWhere('c.ipHash = :ipHash')
            ->andWhere('c.clickedAt > :since')
            ->setParameter('link', $link)
            ->setParameter('ipHash', $ipHash)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function countByLinkToday(AffiliateLink $link): int
    {
        $today = new \DateTimeImmutable('today');

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.link = :link')
            ->andWhere('c.clickedAt >= :today')
            ->setParameter('link', $link)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function getClicksByDayForLink(AffiliateLink $link, int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT DATE(clicked_at) as date, COUNT(id) as clicks
                FROM click_tracking
                WHERE link_id = :linkId AND clicked_at >= :since
                GROUP BY DATE(clicked_at)
                ORDER BY date ASC';

        $results = $conn->executeQuery($sql, [
            'linkId' => $link->getId(),
            'since' => $since->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        $stats = [];
        foreach ($results as $row) {
            $stats[$row['date']] = (int) $row['clicks'];
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function getClicksByDayForOwner(User $owner, int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT DATE(c.clicked_at) as date, COUNT(c.id) as clicks
                FROM click_tracking c
                JOIN affiliate_link l ON c.link_id = l.id
                WHERE l.owner_id = :ownerId AND c.clicked_at >= :since
                GROUP BY DATE(c.clicked_at)
                ORDER BY date ASC';

        $results = $conn->executeQuery($sql, [
            'ownerId' => $owner->getId(),
            'since' => $since->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        $stats = [];
        foreach ($results as $row) {
            $stats[$row['date']] = (int) $row['clicks'];
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function getDeviceStats(AffiliateLink $link): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.device, COUNT(c.id) as count')
            ->where('c.link = :link')
            ->andWhere('c.device IS NOT NULL')
            ->setParameter('link', $link)
            ->groupBy('c.device')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $row) {
            $stats[$row['device']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function getCountryStats(AffiliateLink $link, int $limit = 10): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.country, COUNT(c.id) as count')
            ->where('c.link = :link')
            ->andWhere('c.country IS NOT NULL')
            ->setParameter('link', $link)
            ->groupBy('c.country')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $row) {
            $stats[$row['country']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function getRefererStats(AffiliateLink $link, int $limit = 10): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.referer, COUNT(c.id) as count')
            ->where('c.link = :link')
            ->andWhere('c.referer IS NOT NULL')
            ->setParameter('link', $link)
            ->groupBy('c.referer')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $row) {
            $host = parse_url($row['referer'], PHP_URL_HOST) ?? $row['referer'];
            if (isset($stats[$host])) {
                $stats[$host] += (int) $row['count'];
            } else {
                $stats[$host] = (int) $row['count'];
            }
        }

        arsort($stats);
        return array_slice($stats, 0, $limit, true);
    }

    public function countTodayByOwner(User $owner): int
    {
        $today = new \DateTimeImmutable('today');

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->join('c.link', 'l')
            ->where('l.owner = :owner')
            ->andWhere('c.clickedAt >= :today')
            ->setParameter('owner', $owner)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countThisWeekByOwner(User $owner): int
    {
        $weekStart = new \DateTimeImmutable('monday this week');

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->join('c.link', 'l')
            ->where('l.owner = :owner')
            ->andWhere('c.clickedAt >= :weekStart')
            ->setParameter('owner', $owner)
            ->setParameter('weekStart', $weekStart)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countThisMonthByOwner(User $owner): int
    {
        $monthStart = new \DateTimeImmutable('first day of this month midnight');

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->join('c.link', 'l')
            ->where('l.owner = :owner')
            ->andWhere('c.clickedAt >= :monthStart')
            ->setParameter('owner', $owner)
            ->setParameter('monthStart', $monthStart)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return ClickTracking[]
     */
    public function findRecentByOwner(User $owner, int $limit = 20): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.link', 'l')
            ->where('l.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('c.clickedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
