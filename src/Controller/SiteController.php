<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Site;
use App\Form\SiteType;
use App\Service\ConnectorFactory;
use App\Service\SiteManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sites')]
class SiteController extends AbstractController
{
    public function __construct(
        private readonly SiteManager $siteManager,
        private readonly ConnectorFactory $connectorFactory,
        private readonly Security $security,
    ) {
    }

    #[Route('', name: 'app_site_index', methods: ['GET'])]
    public function index(): Response
    {
        $sites = $this->siteManager->getAllSites();
        $connectors = $this->connectorFactory->getAvailableConnectors();

        return $this->render('site/index.html.twig', [
            'sites' => $sites,
            'connectors' => $connectors,
        ]);
    }

    #[Route('/new', name: 'app_site_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $site = new Site();
        $site->setOwner($this->security->getUser());

        $form = $this->createForm(SiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Extract config fields from form
            $config = $this->extractConfigFromForm($form, $site->getType());
            $site->setConfig($config);

            $this->siteManager->createSite($site);

            $this->addFlash('success', sprintf('Le site "%s" a ete cree avec succes.', $site->getName()));

            return $this->redirectToRoute('app_site_show', ['id' => $site->getId()]);
        }

        $connectors = $this->connectorFactory->getAvailableConnectors();

        return $this->render('site/create.html.twig', [
            'site' => $site,
            'form' => $form,
            'connectors' => $connectors,
        ]);
    }

    #[Route('/{id}', name: 'app_site_show', methods: ['GET'])]
    public function show(Site $site): Response
    {
        $stats = null;
        $statsError = null;

        try {
            $connector = $this->connectorFactory->getConnector($site);
            $stats = $connector->fetchStats($site);
        } catch (\Exception $e) {
            $statsError = $e->getMessage();
        }

        $connectorInfo = $this->connectorFactory->getAvailableConnectors()[$site->getType()] ?? null;

        return $this->render('site/show.html.twig', [
            'site' => $site,
            'stats' => $stats,
            'stats_error' => $statsError,
            'connector' => $connectorInfo,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_site_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Site $site): Response
    {
        $form = $this->createForm(SiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Extract config fields from form
            $config = $this->extractConfigFromForm($form, $site->getType());
            $site->setConfig($config);

            $this->siteManager->updateSite($site);

            $this->addFlash('success', sprintf('Le site "%s" a ete modifie avec succes.', $site->getName()));

            return $this->redirectToRoute('app_site_show', ['id' => $site->getId()]);
        }

        $connectors = $this->connectorFactory->getAvailableConnectors();

        return $this->render('site/edit.html.twig', [
            'site' => $site,
            'form' => $form,
            'connectors' => $connectors,
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
        try {
            $connector = $this->connectorFactory->getConnector($site);
            $isConnected = $connector->testConnection($site);

            if ($isConnected) {
                $site->setStatus('online');
                $site->setLastCheckedAt(new \DateTimeImmutable());
                $this->siteManager->updateSite($site);
                $this->addFlash('success', sprintf('Connexion reussie a "%s".', $site->getName()));
            } else {
                $site->setStatus('offline');
                $site->setLastCheckedAt(new \DateTimeImmutable());
                $this->siteManager->updateSite($site);
                $this->addFlash('error', sprintf('Echec de connexion a "%s". Verifiez la configuration.', $site->getName()));
            }
        } catch (\Exception $e) {
            $site->setStatus('error');
            $site->setLastCheckedAt(new \DateTimeImmutable());
            $this->siteManager->updateSite($site);
            $this->addFlash('error', sprintf('Erreur: %s', $e->getMessage()));
        }

        return $this->redirectToRoute('app_site_show', ['id' => $site->getId()]);
    }

    #[Route('/{id}/stats', name: 'app_site_stats', methods: ['GET'])]
    public function stats(Site $site): Response
    {
        try {
            $connector = $this->connectorFactory->getConnector($site);
            $stats = $connector->fetchStats($site);

            return $this->render('site/_stats.html.twig', [
                'site' => $site,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->render('site/_stats_error.html.twig', [
                'site' => $site,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract config values from form fields
     */
    private function extractConfigFromForm($form, string $type): array
    {
        $config = [];
        $connectorFields = $this->connectorFactory->getConfigurationFields($type);

        foreach ($connectorFields as $fieldName => $fieldConfig) {
            // Skip url and apiToken - they're stored directly on the entity
            if (in_array($fieldName, ['url', 'apiToken'])) {
                continue;
            }

            $formFieldName = 'config_' . $fieldName;
            if ($form->has($formFieldName)) {
                $config[$fieldName] = $form->get($formFieldName)->getData();
            }
        }

        return $config;
    }
}
