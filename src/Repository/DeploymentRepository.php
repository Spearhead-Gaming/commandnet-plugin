<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Deployment;

/**
 * @extends AbstractRepository<Deployment>
 */
class DeploymentRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Deployment::class;
    }
}
