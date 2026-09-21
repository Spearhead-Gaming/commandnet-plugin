<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\FormDefinition;

/**
 * @extends AbstractRepository<FormDefinition>
 */
class FormDefinitionRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return FormDefinition::class;
    }

    /**
     * @return array<FormDefinition>
     */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true], ['name' => 'ASC']);
    }
}
