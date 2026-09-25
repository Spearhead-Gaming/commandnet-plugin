<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Security;

use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Service\RawPermissionChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Makes the built-in "user" role do what Forumify's role screen says it does: give its
 * permissions to every logged-in user. Only the Command Net permissions are covered, so
 * granting "command-net.patrols.create" to the "user" role in Admin -> Roles lets every
 * member post a patrol. The Discord commands read the same role through
 * RawPermissionChecker, so the two never disagree.
 *
 * Forumify's own PermissionVoter votes "no" for anything not on an attached role rather
 * than abstaining, and access decisions are affirmative (SuperAdminVoter relies on that),
 * so a "yes" from here is enough.
 *
 * @extends Voter<string, mixed>
 */
class UserRolePermissionVoter extends Voter
{
    public function __construct(private readonly RawPermissionChecker $permissionChecker)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'command-net.');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return $token->getUser() instanceof User && $this->permissionChecker->grantedByUserRole($attribute);
    }
}
