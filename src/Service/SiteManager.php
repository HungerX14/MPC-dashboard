<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Site;
use App\Exception\WordpressApiException;
use App\Repository\SiteRepository;
use Psr\Log\LoggerInterface;

/**
 * Service for managing WordPress sites
 */
class SiteManager
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly WordpressApiClient $apiClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get all sites
     *
     * @return Site[]
     */
    public function getAllSites(): array
    {
        return $this->siteRepository->findAllOrderedByName();
    }

    /**
     * Get site by ID
     */
    public function getSite(int $id): ?Site
    {
        return $this->siteRepository->find($id);
    }

    /**
     * Create a new site
     */
    public function createSite(Site $site): Site
    {
        $this->siteRepository->save($site);

        $this->logger->info('New site created', [
            'id' => $site->getId(),
            'name' => $site->getName(),
            'url' => $site->getUrl(),
        ]);

        return $site;
    }

    /**
     * Update a site
     */
    public function updateSite(Site $site): Site
    {
        $this->siteRepository->save($site);

        $this->logger->info('Site updated', [
            'id' => $site->getId(),
            'name' => $site->getName(),
        ]);

        return $site;
    }

    /**
     * Delete a site
     */
    public function deleteSite(Site $site): void
    {
        $this->logger->info('Site deleted', [
            'id' => $site->getId(),
            'name' => $site->getName(),
        ]);

        $this->siteRepository->remove($site);
    }

    /**
     * Test connection to a site and update its status
     */
    public function testSiteConnection(Site $site): bool
    {
        try {
            $isConnected = $this->apiClient->testConnection($site);

            $site->setStatus($isConnected ? 'online' : 'offline');
            $site->setLastCheckedAt(new \DateTimeImmutable());
            $this->siteRepository->save($site);

            $this->logger->info('Site connection test', [
                'id' => $site->getId(),
                'name' => $site->getName(),
                'status' => $site->getStatus(),
            ]);

            return $isConnected;
        } catch (WordpressApiException $e) {
            $site->setStatus('error');
            $site->setLastCheckedAt(new \DateTimeImmutable());
            $this->siteRepository->save($site);

            $this->logger->warning('Site connection test failed', [
                'id' => $site->getId(),
                'name' => $site->getName(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get dashboard statistics
     *
     * @return array<string, int>
     */
    public function getDashboardStats(): array
    {
        return [
            'total' => $this->siteRepository->countAll(),
            'online' => $this->siteRepository->countByStatus('online'),
            'offline' => $this->siteRepository->countByStatus('offline'),
            'error' => $this->siteRepository->countByStatus('error'),
            'unknown' => $this->siteRepository->countByStatus('unknown'),
        ];
    }

    /**
     * Refresh status of all sites
     *
     * @return array<string, int> Results summary
     */
    public function refreshAllSitesStatus(): array
    {
        $sites = $this->getAllSites();
        $results = ['success' => 0, 'failed' => 0];

        foreach ($sites as $site) {
            if ($this->testSiteConnection($site)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}
