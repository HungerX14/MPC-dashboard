<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PublicationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/publications')]
#[IsGranted('ROLE_USER')]
class PublicationsController extends AbstractController
{
    #[Route('', name: 'app_publications')]
    public function index(PublicationRepository $publicationRepository): Response
    {
        $publications = $publicationRepository->findRecentByUser($this->getUser(), 50);

        return $this->render('publications/index.html.twig', [
            'publications' => $publications,
        ]);
    }
}
