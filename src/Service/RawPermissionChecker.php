<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\RoleRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The same permission rules the web app applies, for callers with no security token to vote
 * against - a Discord command is triggered by the bot, not a logged-in session, so each
 * command has to check permissions itself.
 *
 * Three things grant a permission: a role attached to the account that lists it, a
 * super-admin role (Forumify's SuperAdminVoter: "access to everything"), and the built-in
 * "user" role. Forumify describes that role as given to every logged-in user, but its
 * PermissionVoter only reads roles attached to the account, so nothing applied it. That is
 * what UserRolePermissionVoter fixes on the web; grantedByUserRole() is the shared half.
 */
class RawPermissionChecker implements ResetInterface
{
    private ?Role $userRole = null;

    public function __construct(private readonly RoleRepository $roleRepository)
    {
    }

    public function isGranted(User $user, string $permission): bool
    {
        if ($this->grantedByUserRole($permission)) {
            return true;
        }

        foreach ($user->getRoleEntities() as $role) {
            if ($role->getRoleName() === 'ROLE_SUPER_ADMIN' || in_array($permission, $role->getPermissions(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the built-in "user" role lists this permission - i.e. every account has it,
     * whether or not that account ever logged in to the site.
     */
    public function grantedByUserRole(string $permission): bool
    {
        $this->userRole ??= $this->roleRepository->findOneBy(['slug' => 'user']);

        return $this->userRole !== null && in_array($permission, $this->userRole->getPermissions(), true);
    }

    /**
     * Symfony calls this after every request (services implementing ResetInterface are
     * reset automatically). The role is only looked up once per request, and must not
     * outlive it: the entity manager is cleared between requests, which would leave a
     * stale copy of the role behind.
     */
    public function reset(): void
    {
        $this->userRole = null;
    }
}
