<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Site;
use App\Exception\WordpressApiException;
use App\Form\SiteType;
use App\Service\SiteManager;
use App\Service\WordpressApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sites')]
class SiteController extends AbstractController
{
    public function __construct(
        private readonly SiteManager $siteManager,
        private readonly WordpressApiClient $apiClient,
    ) {
    }

    #[Route('', name: 'app_site_index', methods: ['GET'])]
    public function index(): Response
    {
        $sites = $this->siteManager->getAllSites();

        return $this->render('site/index.html.twig', [
            'sites' => $sites,
        ]);
    }

    #[Route('/new', name: 'app_site_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $site = new Site();
        $form = $this->createForm(SiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->siteManager->createSite($site);

            $this->addFlash('success', sprintf('Le site "%s" a ete cree avec succes.', $site->getName()));

            return $this->redirectToRoute('app_site_show', ['id' => $site->getId()]);
        }

        return $this->render('site/create.html.twig', [
            'site' => $site,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_site_show', methods: ['GET'])]
    public function show(Site $site): Response
    {
        $stats = null;
        $statsError = null;

        try {
            $stats = $this->apiClient->fetchStats($site);
        } catch (WordpressApiException $e) {
            $statsError = $e->getUserMessage();
        }

        return $this->render('site/show.html.twig', [
            'site' => $site,
            'stats' => $stats,
            'stats_error' => $statsError,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_site_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Site $site): Response
    {
        $form = $this->createForm(SiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->siteManager->updateSite($site);

            $this->addFlash('success', sprintf('Le site "%s" a ete modifie avec succes.', $site->getName()));

            return $this->redirectToRoute('app_site_show', ['id' => $site->getId()]);
        }

        return $this->render('site/edit.html.twig', [
            'site' => $site,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_site_delete', methods: ['POST'])]
    public function delete(Request $request, Site $site): Response
    {
        if ($this->isCsrfTokenValid('delete' . $site->getId(), $request->getPayload()->getString('_token'))) {
            $siteName = $site->getName();
            $this->siteManager->deleteSite($site);

            $this->addFlash('success', sprintf('Le site "%s" a ete supprime.', $siteName));
        }

        return $this->redirectToRoute('app_site_index');
    }

    #[Route('/{id}/test', name: 'app_site_test', methods: ['POST'])]
    public function testConnection(Site $site): Response
    {
        $isConnected = $this->siteManager->testSiteConnection($site);

        if ($isConnected) {
            $this->addFlash('success', sprintf('Connexion reussie a "%s".', $site->getName()));
        } else {
            $this->addFlash('error', sprintf('Echec de connexion a "%s". Verifiez la configuration.', $site->getName()));
        }

        return $this->redirectToRoute('app_site_show', ['id' => $site->getId()]);
    }

    #[Route('/{id}/stats', name: 'app_site_stats', methods: ['GET'])]
    public function stats(Site $site): Response
    {
        try {
            $stats = $this->apiClient->fetchStats($site);

            return $this->render('site/_stats.html.twig', [
                'site' => $site,
                'stats' => $stats,
            ]);
        } catch (WordpressApiException $e) {
            return $this->render('site/_stats_error.html.twig', [
                'site' => $site,
                'error' => $e->getUserMessage(),
            ]);
        }
    }
}
