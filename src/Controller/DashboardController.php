<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SiteManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly SiteManager $siteManager,
    ) {
    }

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        $stats = $this->siteManager->getDashboardStats();
        $sites = $this->siteManager->getAllSites();

        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
            'sites' => $sites,
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
