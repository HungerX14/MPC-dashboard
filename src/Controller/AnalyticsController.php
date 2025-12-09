<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PublicationRepository;
use App\Repository\SiteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/analytics')]
#[IsGranted('ROLE_USER')]
class AnalyticsController extends AbstractController
{
    #[Route('', name: 'app_analytics')]
    public function index(SiteRepository $siteRepository, PublicationRepository $publicationRepository): Response
    {
        $user = $this->getUser();
        $sites = $siteRepository->findAll();

        // Get publication stats
        $publicationStats = $publicationRepository->getStatsByUser($user);
        $publicationsPerDay = $publicationRepository->getPublicationsPerDay($user, 30);

        // Site status breakdown
        $siteStats = [
            'total' => count($sites),
            'online' => 0,
            'offline' => 0,
            'error' => 0,
        ];

        foreach ($sites as $site) {
            match ($site->getStatus()) {
                'online' => $siteStats['online']++,
                'offline' => $siteStats['offline']++,
                'error' => $siteStats['error']++,
                default => null,
            };
        }

        return $this->render('analytics/index.html.twig', [
            'sites' => $sites,
            'siteStats' => $siteStats,
            'publicationStats' => $publicationStats,
            'publicationsPerDay' => $publicationsPerDay,
        ]);
    }
}
