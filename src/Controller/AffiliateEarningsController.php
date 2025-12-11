<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AffiliateLinkRepository;
use App\Repository\AffiliateProgramRepository;
use App\Repository\ClickTrackingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/affiliate/earnings')]
class AffiliateEarningsController extends AbstractController
{
    public function __construct(
        private readonly AffiliateLinkRepository $linkRepository,
        private readonly AffiliateProgramRepository $programRepository,
        private readonly ClickTrackingRepository $clickRepository,
        private readonly Security $security,
    ) {
    }

    #[Route('', name: 'app_affiliate_earnings', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->security->getUser();

        // Get overall stats
        $stats = $this->linkRepository->getStatsByOwner($user);

        // Get top earning links
        $topEarningLinks = $this->linkRepository->findTopEarningByOwner($user, 10);

        // Get clicks by day for chart
        $clicksByDay = $this->clickRepository->getClicksByDayForOwner($user, 30);

        // Get recent clicks
        $recentClicks = $this->clickRepository->findRecentByOwner($user, 20);

        // Get program stats
        $programs = $this->programRepository->findByOwner($user);
        $programStats = [];

        foreach ($programs as $program) {
            $programLinks = $this->linkRepository->findByProgram($program);
            $programEarnings = '0.00';
            $programClicks = 0;
            $programConversions = 0;

            foreach ($programLinks as $link) {
                $programEarnings = bcadd($programEarnings, $link->getTotalEarnings(), 2);
                $programClicks += $link->getTotalClicks();
                $programConversions += $link->getConversions();
            }

            $programStats[] = [
                'program' => $program,
                'earnings' => $programEarnings,
                'clicks' => $programClicks,
                'conversions' => $programConversions,
                'linkCount' => count($programLinks),
            ];
        }

        // Sort by earnings
        usort($programStats, fn($a, $b) => bccomp($b['earnings'], $a['earnings'], 2));

        // Calculate period stats
        $todayClicks = $this->clickRepository->countTodayByOwner($user);
        $weekClicks = $this->clickRepository->countThisWeekByOwner($user);
        $monthClicks = $this->clickRepository->countThisMonthByOwner($user);

        return $this->render('affiliate/earnings/index.html.twig', [
            'stats' => $stats,
            'topEarningLinks' => $topEarningLinks,
            'clicksByDay' => $clicksByDay,
            'recentClicks' => $recentClicks,
            'programStats' => $programStats,
            'periodStats' => [
                'today' => $todayClicks,
                'week' => $weekClicks,
                'month' => $monthClicks,
            ],
        ]);
    }
}
