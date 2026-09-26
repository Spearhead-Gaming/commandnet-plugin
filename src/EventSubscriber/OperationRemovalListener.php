<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationAAR;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;

/**
 * Cleans up after a deleted operation, whichever way it was deleted (the patrol's delete button,
 * or the admin list): its RSVPs and AARs go with it in the database, but the combat records they
 * earned sit on personnel files pointing at it by id, so they would be left behind, crediting a
 * soldier for an operation that no longer exists.
 *
 * The ids are taken before the delete, when the AARs can still be read, and acted on after the
 * flush - and only if the operation is really gone, since preRemove also fires for a flush that
 * then fails.
 */
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class OperationRemovalListener
{
    /** @var array<int, array<int>> operation id => the ids of its AARs */
    private array $removed = [];

    public function __construct(private readonly ServiceRecordRepository $serviceRecordRepository)
    {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $operation = $args->getObject();
        if (!$operation instanceof Operation) {
            return;
        }

        $this->removed[$operation->getId()] = array_map(
            static fn (OperationAAR $aar): int => $aar->getId(),
            $operation->getAars()->toArray(),
        );
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->removed === []) {
            return;
        }

        $removed = $this->removed;
        $this->removed = [];

        foreach ($removed as $operationId => $aarIds) {
            if ($args->getObjectManager()->find(Operation::class, $operationId) === null) {
                $this->serviceRecordRepository->deleteForOperation($operationId, $aarIds);
            }
        }
    }
}
