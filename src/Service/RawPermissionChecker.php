<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Entity\User;

/**
 * The same "does this permission appear on any of the user's roles" check as Forumify's
 * own PermissionVoter, for callers with no security token to vote against - a Discord
 * command is triggered by the bot, not a logged-in session, so there is no token to check
 * against and each command has to check permissions itself.
 */
class RawPermissionChecker
{
    public function isGranted(User $user, string $permission): bool
    {
        foreach ($user->getRoleEntities() as $role) {
            if (in_array($permission, $role->getPermissions(), true)) {
                return true;
            }
        }

        return false;
    }
}
