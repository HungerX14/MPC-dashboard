<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogger
{
    public function __construct(
        private readonly ActivityLogRepository $repository,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function log(string $action, string $description, ?array $metadata = null, ?User $user = null): void
    {
        $log = new ActivityLog();
        $log->setAction($action);
        $log->setDescription($description);
        $log->setMetadata($metadata);

        // Get user from security if not provided
        if ($user === null) {
            $currentUser = $this->security->getUser();
            if ($currentUser instanceof User) {
                $log->setUser($currentUser);
            }
        } else {
            $log->setUser($user);
        }

        // Get IP address
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $log->setIpAddress($request->getClientIp());
        }

        $this->repository->save($log, true);
    }

    public function logSiteCreated(string $siteName, int $siteId): void
    {
        $this->log(
            ActivityLog::ACTION_SITE_CREATED,
            sprintf('Site "%s" créé', $siteName),
            ['site_id' => $siteId, 'site_name' => $siteName]
        );
    }

    public function logSiteUpdated(string $siteName, int $siteId): void
    {
        $this->log(
            ActivityLog::ACTION_SITE_UPDATED,
            sprintf('Site "%s" mis à jour', $siteName),
            ['site_id' => $siteId, 'site_name' => $siteName]
        );
    }

    public function logSiteDeleted(string $siteName): void
    {
        $this->log(
            ActivityLog::ACTION_SITE_DELETED,
            sprintf('Site "%s" supprimé', $siteName),
            ['site_name' => $siteName]
        );
    }

    public function logSiteOnline(string $siteName, int $siteId): void
    {
        $this->log(
            ActivityLog::ACTION_SITE_ONLINE,
            sprintf('Site "%s" est en ligne', $siteName),
            ['site_id' => $siteId, 'site_name' => $siteName]
        );
    }

    public function logSiteOffline(string $siteName, int $siteId): void
    {
        $this->log(
            ActivityLog::ACTION_SITE_OFFLINE,
            sprintf('Site "%s" est hors ligne', $siteName),
            ['site_id' => $siteId, 'site_name' => $siteName]
        );
    }

    public function logArticlePublished(string $articleTitle, string $siteName, int $siteId): void
    {
        $this->log(
            ActivityLog::ACTION_ARTICLE_PUBLISHED,
            sprintf('Article "%s" publié sur %s', $articleTitle, $siteName),
            ['article_title' => $articleTitle, 'site_id' => $siteId, 'site_name' => $siteName]
        );
    }

    public function logArticleFailed(string $articleTitle, string $siteName, string $error): void
    {
        $this->log(
            ActivityLog::ACTION_ARTICLE_FAILED,
            sprintf('Échec de publication "%s" sur %s', $articleTitle, $siteName),
            ['article_title' => $articleTitle, 'site_name' => $siteName, 'error' => $error]
        );
    }

    public function logArticleScheduled(string $articleTitle, string $siteName, \DateTimeInterface $scheduledAt): void
    {
        $this->log(
            ActivityLog::ACTION_ARTICLE_SCHEDULED,
            sprintf('Article "%s" planifié pour %s sur %s', $articleTitle, $scheduledAt->format('d/m/Y H:i'), $siteName),
            ['article_title' => $articleTitle, 'site_name' => $siteName, 'scheduled_at' => $scheduledAt->format('c')]
        );
    }

    public function logUserLogin(User $user): void
    {
        $this->log(
            ActivityLog::ACTION_USER_LOGIN,
            'Connexion réussie',
            null,
            $user
        );
    }
}
