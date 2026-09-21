<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Entity\User;
use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\EnlistmentApplication;

/**
 * @extends AbstractRepository<EnlistmentApplication>
 */
class EnlistmentApplicationRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return EnlistmentApplication::class;
    }

    public function findLatestFor(User $user): ?EnlistmentApplication
    {
        return $this->findOneBy(['user' => $user], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }
}
