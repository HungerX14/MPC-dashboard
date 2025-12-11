<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\AffiliateProgram;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AffiliateProgramVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof AffiliateProgram;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var AffiliateProgram $program */
        $program = $subject;

        return match ($attribute) {
            self::VIEW, self::EDIT, self::DELETE => $this->isOwner($program, $user),
            default => false,
        };
    }

    private function isOwner(AffiliateProgram $program, User $user): bool
    {
        return $program->getOwner() === $user;
    }
}
