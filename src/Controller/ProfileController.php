<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile')]
    public function index(SubscriptionRepository $subscriptionRepository): Response
    {
        $user = $this->getUser();
        $subscription = $subscriptionRepository->getOrCreateForUser($user);

        return $this->render('profile/index.html.twig', [
            'subscription' => $subscription,
        ]);
    }

    #[Route('/update', name: 'app_profile_update', methods: ['POST'])]
    public function update(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $fullName = $request->request->get('full_name');
        $email = $request->request->get('email');

        if ($fullName) {
            $user->setFullName($fullName);
        }

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user->setEmail($email);
        }

        $em->flush();

        $this->addFlash('success', 'Profil mis a jour avec succes');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/password', name: 'app_profile_password', methods: ['POST'])]
    public function updatePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $currentPassword = $request->request->get('current_password');
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Mot de passe actuel incorrect');
            return $this->redirectToRoute('app_profile');
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas');
            return $this->redirectToRoute('app_profile');
        }

        if (strlen($newPassword) < 8) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caracteres');
            return $this->redirectToRoute('app_profile');
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $em->flush();

        $this->addFlash('success', 'Mot de passe mis a jour avec succes');

        return $this->redirectToRoute('app_profile');
    }
}
