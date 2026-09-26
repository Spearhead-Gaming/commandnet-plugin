<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use MajesticDev\CommandNet\Entity\Deployment;
use MajesticDev\CommandNet\Repository\DeploymentRepository;

/**
 * Finds a deployment by the name someone typed, for places that cannot offer a dropdown (the
 * Discord slash command). Deployments are a handful per year, so matching in PHP - trimmed and
 * case-insensitive, whatever the database collation - beats a query.
 */
class DeploymentLookup
{
    public function __construct(private readonly DeploymentRepository $deploymentRepository)
    {
    }

    public function findByName(string $name): ?Deployment
    {
        $wanted = mb_strtolower(trim($name));
        if ($wanted === '') {
            return null;
        }

        foreach ($this->deploymentRepository->findBy([], ['startDate' => 'DESC']) as $deployment) {
            if (mb_strtolower(trim($deployment->getName())) === $wanted) {
                return $deployment;
            }
        }

        return null;
    }

    /**
     * The newest deployments' names, to tell someone what they could have typed.
     *
     * @return list<string>
     */
    public function recentNames(int $limit = 10): array
    {
        $names = [];
        foreach ($this->deploymentRepository->findBy([], ['startDate' => 'DESC'], $limit) as $deployment) {
            $names[] = $deployment->getName();
        }

        return $names;
    }
}
