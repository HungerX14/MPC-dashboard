<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/billing')]
#[IsGranted('ROLE_USER')]
class BillingController extends AbstractController
{
    #[Route('', name: 'app_billing')]
    public function index(SubscriptionRepository $subscriptionRepository): Response
    {
        $subscription = $subscriptionRepository->getOrCreateForUser($this->getUser());

        return $this->render('billing/index.html.twig', [
            'subscription' => $subscription,
            'plans' => Subscription::PLANS,
        ]);
    }

    #[Route('/upgrade/{plan}', name: 'app_billing_upgrade', methods: ['POST'])]
    public function upgrade(
        string $plan,
        SubscriptionRepository $subscriptionRepository,
        EntityManagerInterface $em
    ): Response {
        if (!array_key_exists($plan, Subscription::PLANS)) {
            $this->addFlash('error', 'Plan invalide');
            return $this->redirectToRoute('app_billing');
        }

        $subscription = $subscriptionRepository->getOrCreateForUser($this->getUser());

        // In a real app, this would integrate with Stripe
        $subscription->setPlan($plan);
        $em->flush();

        $this->addFlash('success', 'Votre abonnement a ete mis a jour vers ' . Subscription::PLANS[$plan]['name']);

        return $this->redirectToRoute('app_billing');
    }
}
