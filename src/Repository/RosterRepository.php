<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Roster;

/**
 * @extends AbstractRepository<Roster>
 */
class RosterRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Roster::class;
    }

    /**
     * @return array<Roster> in the order staff arranged them
     */
    public function findInOrder(): array
    {
        return $this->findBy([], ['position' => 'ASC']);
    }
}
