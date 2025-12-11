<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AffiliateProgram;
use App\Repository\AffiliateLinkRepository;
use App\Repository\AffiliateProgramRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/affiliate/programs')]
class AffiliateProgramController extends AbstractController
{
    public function __construct(
        private readonly AffiliateProgramRepository $programRepository,
        private readonly AffiliateLinkRepository $linkRepository,
        private readonly Security $security,
    ) {
    }

    #[Route('', name: 'app_affiliate_programs', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->security->getUser();
        $status = $request->query->get('status');

        if ($status) {
            $programs = $this->programRepository->findByOwnerAndStatus($user, $status);
        } else {
            $programs = $this->programRepository->findByOwner($user);
        }

        $networkStats = $this->programRepository->getNetworkStats($user);
        $categoryStats = $this->programRepository->getCategoryStats($user);

        return $this->render('affiliate/program/index.html.twig', [
            'programs' => $programs,
            'networkStats' => $networkStats,
            'categoryStats' => $categoryStats,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/new', name: 'app_affiliate_program_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $program = new AffiliateProgram();
            $program->setOwner($this->security->getUser());
            $program->setName($request->request->get('name'));
            $program->setDescription($request->request->get('description'));
            $program->setWebsite($request->request->get('website') ?: null);
            $program->setDashboardUrl($request->request->get('dashboard_url') ?: null);
            $program->setNetwork($request->request->get('network') ?: null);
            $program->setCommissionRate($request->request->get('commission_rate') ?: null);
            $program->setCommissionType($request->request->get('commission_type') ?: null);
            $program->setCurrency($request->request->get('currency') ?: 'EUR');
            $program->setCookieDuration($request->request->getInt('cookie_duration') ?: null);
            $program->setCategory($request->request->get('category') ?: null);
            $program->setNotes($request->request->get('notes') ?: null);
            $program->setStatus($request->request->get('status') ?: 'active');

            $this->programRepository->save($program);

            $this->addFlash('success', sprintf('Le programme "%s" a ete cree.', $program->getName()));

            return $this->redirectToRoute('app_affiliate_program_show', ['id' => $program->getId()]);
        }

        return $this->render('affiliate/program/new.html.twig');
    }

    #[Route('/{id}', name: 'app_affiliate_program_show', methods: ['GET'])]
    public function show(AffiliateProgram $program): Response
    {
        $this->denyAccessUnlessGranted('view', $program);

        $links = $this->linkRepository->findByProgram($program);

        // Calculate stats
        $totalClicks = 0;
        $totalEarnings = '0.00';
        $totalConversions = 0;

        foreach ($links as $link) {
            $totalClicks += $link->getTotalClicks();
            $totalEarnings = bcadd($totalEarnings, $link->getTotalEarnings(), 2);
            $totalConversions += $link->getConversions();
        }

        return $this->render('affiliate/program/show.html.twig', [
            'program' => $program,
            'links' => $links,
            'stats' => [
                'totalClicks' => $totalClicks,
                'totalEarnings' => $totalEarnings,
                'totalConversions' => $totalConversions,
                'linkCount' => count($links),
            ],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_affiliate_program_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, AffiliateProgram $program): Response
    {
        $this->denyAccessUnlessGranted('edit', $program);

        if ($request->isMethod('POST')) {
            $program->setName($request->request->get('name'));
            $program->setDescription($request->request->get('description'));
            $program->setWebsite($request->request->get('website') ?: null);
            $program->setDashboardUrl($request->request->get('dashboard_url') ?: null);
            $program->setNetwork($request->request->get('network') ?: null);
            $program->setCommissionRate($request->request->get('commission_rate') ?: null);
            $program->setCommissionType($request->request->get('commission_type') ?: null);
            $program->setCurrency($request->request->get('currency') ?: 'EUR');
            $program->setCookieDuration($request->request->getInt('cookie_duration') ?: null);
            $program->setCategory($request->request->get('category') ?: null);
            $program->setNotes($request->request->get('notes') ?: null);
            $program->setStatus($request->request->get('status') ?: 'active');

            $this->programRepository->save($program);

            $this->addFlash('success', sprintf('Le programme "%s" a ete modifie.', $program->getName()));

            return $this->redirectToRoute('app_affiliate_program_show', ['id' => $program->getId()]);
        }

        return $this->render('affiliate/program/edit.html.twig', [
            'program' => $program,
        ]);
    }

    #[Route('/{id}', name: 'app_affiliate_program_delete', methods: ['POST'])]
    public function delete(Request $request, AffiliateProgram $program): Response
    {
        $this->denyAccessUnlessGranted('delete', $program);

        if ($this->isCsrfTokenValid('delete' . $program->getId(), $request->getPayload()->getString('_token'))) {
            $programName = $program->getName();
            $this->programRepository->remove($program);

            $this->addFlash('success', sprintf('Le programme "%s" a ete supprime.', $programName));
        }

        return $this->redirectToRoute('app_affiliate_programs');
    }
}
