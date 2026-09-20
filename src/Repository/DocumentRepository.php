<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\Document;

/**
 * @extends AbstractRepository<Document>
 */
class DocumentRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Document::class;
    }
}
