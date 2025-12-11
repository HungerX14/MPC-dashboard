<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AffiliateLink;
use App\Repository\AffiliateLinkRepository;
use App\Repository\AffiliateProgramRepository;
use App\Repository\ClickTrackingRepository;
use App\Repository\SiteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/affiliate/links')]
class AffiliateLinkController extends AbstractController
{
    public function __construct(
        private readonly AffiliateLinkRepository $linkRepository,
        private readonly AffiliateProgramRepository $programRepository,
        private readonly ClickTrackingRepository $clickRepository,
        private readonly SiteRepository $siteRepository,
        private readonly Security $security,
    ) {
    }

    #[Route('', name: 'app_affiliate_links', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->security->getUser();

        $links = $this->linkRepository->findByOwner($user);
        $stats = $this->linkRepository->getStatsByOwner($user);
        $topPerforming = $this->linkRepository->findTopPerformingByOwner($user, 5);

        return $this->render('affiliate/link/index.html.twig', [
            'links' => $links,
            'stats' => $stats,
            'topPerforming' => $topPerforming,
        ]);
    }

    #[Route('/new', name: 'app_affiliate_link_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->security->getUser();

        if ($request->isMethod('POST')) {
            $link = new AffiliateLink();
            $link->setOwner($user);
            $link->setName($request->request->get('name'));
            $link->setUrl($request->request->get('url'));
            $link->setDestinationUrl($request->request->get('destination_url') ?: null);
            $link->setDescription($request->request->get('description') ?: null);
            $link->setStatus($request->request->get('status') ?: 'active');

            // Handle tags
            $tagsInput = $request->request->get('tags');
            if ($tagsInput) {
                $tags = array_map('trim', explode(',', $tagsInput));
                $link->setTags($tags);
            }

            // Handle program
            $programId = $request->request->getInt('program_id');
            if ($programId) {
                $program = $this->programRepository->find($programId);
                if ($program && $program->getOwner() === $user) {
                    $link->setProgram($program);
                }
            }

            // Handle site
            $siteId = $request->request->getInt('site_id');
            if ($siteId) {
                $site = $this->siteRepository->find($siteId);
                if ($site && $site->getOwner() === $user) {
                    $link->setSite($site);
                }
            }

            $this->linkRepository->save($link);

            $this->addFlash('success', sprintf('Le lien "%s" a ete cree.', $link->getName()));

            return $this->redirectToRoute('app_affiliate_link_show', ['id' => $link->getId()]);
        }

        $programs = $this->programRepository->findActiveByOwner($user);
        $sites = $this->siteRepository->findAll();

        return $this->render('affiliate/link/new.html.twig', [
            'programs' => $programs,
            'sites' => $sites,
        ]);
    }

    #[Route('/{id}', name: 'app_affiliate_link_show', methods: ['GET'])]
    public function show(AffiliateLink $link): Response
    {
        $this->denyAccessUnlessGranted('view', $link);

        $recentClicks = $this->clickRepository->findByLink($link, 50);
        $clicksByDay = $this->clickRepository->getClicksByDayForLink($link, 30);
        $deviceStats = $this->clickRepository->getDeviceStats($link);
        $countryStats = $this->clickRepository->getCountryStats($link);
        $refererStats = $this->clickRepository->getRefererStats($link);

        return $this->render('affiliate/link/show.html.twig', [
            'link' => $link,
            'recentClicks' => $recentClicks,
            'clicksByDay' => $clicksByDay,
            'deviceStats' => $deviceStats,
            'countryStats' => $countryStats,
            'refererStats' => $refererStats,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_affiliate_link_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, AffiliateLink $link): Response
    {
        $this->denyAccessUnlessGranted('edit', $link);

        $user = $this->security->getUser();

        if ($request->isMethod('POST')) {
            $link->setName($request->request->get('name'));
            $link->setUrl($request->request->get('url'));
            $link->setDestinationUrl($request->request->get('destination_url') ?: null);
            $link->setDescription($request->request->get('description') ?: null);
            $link->setStatus($request->request->get('status') ?: 'active');

            // Handle tags
            $tagsInput = $request->request->get('tags');
            if ($tagsInput) {
                $tags = array_map('trim', explode(',', $tagsInput));
                $link->setTags($tags);
            } else {
                $link->setTags(null);
            }

            // Handle program
            $programId = $request->request->getInt('program_id');
            if ($programId) {
                $program = $this->programRepository->find($programId);
                if ($program && $program->getOwner() === $user) {
                    $link->setProgram($program);
                }
            } else {
                $link->setProgram(null);
            }

            // Handle site
            $siteId = $request->request->getInt('site_id');
            if ($siteId) {
                $site = $this->siteRepository->find($siteId);
                if ($site && $site->getOwner() === $user) {
                    $link->setSite($site);
                }
            } else {
                $link->setSite(null);
            }

            $this->linkRepository->save($link);

            $this->addFlash('success', sprintf('Le lien "%s" a ete modifie.', $link->getName()));

            return $this->redirectToRoute('app_affiliate_link_show', ['id' => $link->getId()]);
        }

        $programs = $this->programRepository->findActiveByOwner($user);
        $sites = $this->siteRepository->findAll();

        return $this->render('affiliate/link/edit.html.twig', [
            'link' => $link,
            'programs' => $programs,
            'sites' => $sites,
        ]);
    }

    #[Route('/{id}', name: 'app_affiliate_link_delete', methods: ['POST'])]
    public function delete(Request $request, AffiliateLink $link): Response
    {
        $this->denyAccessUnlessGranted('delete', $link);

        if ($this->isCsrfTokenValid('delete' . $link->getId(), $request->getPayload()->getString('_token'))) {
            $linkName = $link->getName();
            $this->linkRepository->remove($link);

            $this->addFlash('success', sprintf('Le lien "%s" a ete supprime.', $linkName));
        }

        return $this->redirectToRoute('app_affiliate_links');
    }

    #[Route('/{id}/regenerate', name: 'app_affiliate_link_regenerate', methods: ['POST'])]
    public function regenerateShortCode(Request $request, AffiliateLink $link): Response
    {
        $this->denyAccessUnlessGranted('edit', $link);

        if ($this->isCsrfTokenValid('regenerate' . $link->getId(), $request->getPayload()->getString('_token'))) {
            $link->regenerateShortCode();
            $this->linkRepository->save($link);

            $this->addFlash('success', 'Le code court a ete regenere.');
        }

        return $this->redirectToRoute('app_affiliate_link_show', ['id' => $link->getId()]);
    }
}
