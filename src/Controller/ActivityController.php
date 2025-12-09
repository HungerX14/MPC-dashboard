<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activity')]
#[IsGranted('ROLE_USER')]
class ActivityController extends AbstractController
{
    #[Route('', name: 'app_activity')]
    public function index(ActivityLogRepository $activityLogRepository): Response
    {
        $activities = $activityLogRepository->findRecentByUser($this->getUser(), 50);

        return $this->render('activity/index.html.twig', [
            'activities' => $activities,
        ]);
    }
}
