<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AffiliateLinkRepository;
use App\Repository\AffiliateProgramRepository;
use App\Repository\ClickTrackingRepository;
use App\Service\SiteManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly SiteManager $siteManager,
        private readonly AffiliateLinkRepository $linkRepository,
        private readonly AffiliateProgramRepository $programRepository,
        private readonly ClickTrackingRepository $clickRepository,
        private readonly Security $security,
    ) {
    }

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        $user = $this->security->getUser();
        $stats = $this->siteManager->getDashboardStats();
        $sites = $this->siteManager->getAllSites();

        // Affiliate stats
        $affiliateStats = $this->linkRepository->getStatsByOwner($user);
        $programCount = $this->programRepository->countByOwner($user);
        $linkCount = $this->linkRepository->countByOwner($user);
        $topLinks = $this->linkRepository->findTopPerformingByOwner($user, 5);
        $recentClicks = $this->clickRepository->findRecentByOwner($user, 10);
        $clicksToday = $this->clickRepository->countTodayByOwner($user);

        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
            'sites' => $sites,
            'affiliateStats' => $affiliateStats,
            'programCount' => $programCount,
            'linkCount' => $linkCount,
            'topLinks' => $topLinks,
            'recentClicks' => $recentClicks,
            'clicksToday' => $clicksToday,
        ]);
    }

    #[Route('/refresh-sites', name: 'app_dashboard_refresh', methods: ['POST'])]
    public function refreshSites(): Response
    {
        $results = $this->siteManager->refreshAllSitesStatus();

        $this->addFlash(
            'success',
            sprintf(
                'Verification terminee : %d site(s) en ligne, %d site(s) hors ligne.',
                $results['success'],
                $results['failed']
            )
        );

        return $this->redirectToRoute('app_dashboard');
    }
}
