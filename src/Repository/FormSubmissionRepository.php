<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Repository;

use Forumify\Core\Entity\User;
use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\CommandNet\Entity\FormSubmission;

/**
 * @extends AbstractRepository<FormSubmission>
 */
class FormSubmissionRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return FormSubmission::class;
    }

    /**
     * @return array<FormSubmission> newest first
     */
    public function findRecentFor(User $user, int $limit = 20): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit);
    }
}
