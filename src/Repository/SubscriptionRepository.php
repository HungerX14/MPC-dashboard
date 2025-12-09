<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function save(Subscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUser(User $user): ?Subscription
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function getOrCreateForUser(User $user): Subscription
    {
        $subscription = $this->findByUser($user);

        if ($subscription === null) {
            $subscription = new Subscription();
            $subscription->setUser($user);
            $this->save($subscription, true);
        }

        return $subscription;
    }

    /**
     * Reset monthly publication counts for all subscriptions
     */
    public function resetMonthlyPublications(): int
    {
        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.publicationsThisMonth', 0)
            ->set('s.currentPeriodStart', ':start')
            ->set('s.currentPeriodEnd', ':end')
            ->setParameter('start', new \DateTimeImmutable('first day of this month'))
            ->setParameter('end', new \DateTimeImmutable('last day of this month 23:59:59'))
            ->getQuery()
            ->execute();
    }
}
