<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Publication;
use App\Entity\Site;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Publication>
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    public function save(Publication $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Publication[]
     */
    public function findRecentByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.author = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Publication[]
     */
    public function findBySite(Site $site, int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.site = :site')
            ->setParameter('site', $site)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Publication[]
     */
    public function findScheduledToPublish(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.scheduledAt <= :now')
            ->setParameter('status', Publication::STATUS_SCHEDULED)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByUserThisMonth(User $user): int
    {
        $startOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.author = :user')
            ->andWhere('p.createdAt >= :start')
            ->setParameter('user', $user)
            ->setParameter('start', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getStatsByUser(User $user): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as count')
            ->where('p.author = :user')
            ->setParameter('user', $user)
            ->groupBy('p.status')
            ->getQuery()
            ->getResult();

        $stats = [
            'total' => 0,
            'published' => 0,
            'failed' => 0,
            'scheduled' => 0,
            'pending' => 0,
        ];

        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get publications per day for the last N days
     */
    public function getPublicationsPerDay(User $user, int $days = 30): array
    {
        $startDate = new \DateTimeImmutable("-{$days} days");
        $end = new \DateTimeImmutable();

        // Get all publications in the date range
        $publications = $this->createQueryBuilder('p')
            ->select('p.createdAt')
            ->where('p.author = :user')
            ->andWhere('p.createdAt >= :start')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->getQuery()
            ->getResult();

        // Initialize all dates with 0
        $data = [];
        $current = $startDate;

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $data[$dateStr] = 0;
            $current = $current->modify('+1 day');
        }

        // Count publications per day
        foreach ($publications as $pub) {
            $dateStr = $pub['createdAt']->format('Y-m-d');
            if (isset($data[$dateStr])) {
                $data[$dateStr]++;
            }
        }

        return $data;
    }
}
