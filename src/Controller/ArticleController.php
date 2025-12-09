<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ArticleDTO;
use App\Entity\Site;
use App\Form\ArticleType;
use App\Service\ArticlePublisher;
use App\Service\SiteManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/articles')]
class ArticleController extends AbstractController
{
    public function __construct(
        private readonly ArticlePublisher $articlePublisher,
        private readonly SiteManager $siteManager,
    ) {
    }

    #[Route('/create', name: 'app_article_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $sites = $this->siteManager->getAllSites();

        if (empty($sites)) {
            $this->addFlash('warning', 'Vous devez d\'abord ajouter un site WordPress.');
            return $this->redirectToRoute('app_site_new');
        }

        $article = new ArticleDTO();
        $form = $this->createForm(ArticleType::class, $article, [
            'sites' => $sites,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Site $selectedSite */
            $selectedSite = $form->get('site')->getData();

            // Validate article
            $errors = $this->articlePublisher->validateArticle($article);
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('article/create.html.twig', [
                    'form' => $form,
                    'sites' => $sites,
                ]);
            }

            // Publish article
            $result = $this->articlePublisher->publishToSite($selectedSite, $article);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
                return $this->redirectToRoute('app_site_show', ['id' => $selectedSite->getId()]);
            } else {
                $this->addFlash('error', $result['message']);
            }
        }

        return $this->render('article/create.html.twig', [
            'form' => $form,
            'sites' => $sites,
        ]);
    }

    #[Route('/create/{id}', name: 'app_article_create_for_site', methods: ['GET', 'POST'])]
    public function createForSite(Request $request, Site $site): Response
    {
        $article = new ArticleDTO();
        $form = $this->createForm(ArticleType::class, $article, [
            'sites' => [$site],
            'selected_site' => $site,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validate article
            $errors = $this->articlePublisher->validateArticle($article);
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('article/create.html.twig', [
                    'form' => $form,
                    'sites' => [$site],
                    'selected_site' => $site,
                ]);
            }

            // Publish article
            $result = $this->articlePublisher->publishToSite($site, $article);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
                return $this->redirectToRoute('app_site_show', ['id' => $site->getId()]);
            } else {
                $this->addFlash('error', $result['message']);
            }
        }

        return $this->render('article/create.html.twig', [
            'form' => $form,
            'sites' => [$site],
            'selected_site' => $site,
        ]);
    }
}
