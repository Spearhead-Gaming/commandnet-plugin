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
use MajesticDev\CommandNet\Service\AarImageStore;

/**
 * Cleans up after a deleted operation, whichever way it was deleted (the patrol's delete button,
 * or the admin list): its RSVPs and AARs go with it in the database, but the combat records they
 * earned sit on personnel files pointing at it by id, so they would be left behind, crediting a
 * soldier for an operation that no longer exists. The map and intel images of its AARs are files,
 * not rows, so they would be left behind too.
 *
 * The ids are taken before the delete, when the AARs can still be read, and acted on after the
 * flush - and only if the operation is really gone, since preRemove also fires for a flush that
 * then fails.
 */
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class OperationRemovalListener
{
    /** @var array<int, array{aars: array<int>, images: array<string>}> operation id => its AARs' ids and image files */
    private array $removed = [];

    public function __construct(
        private readonly ServiceRecordRepository $serviceRecordRepository,
        private readonly AarImageStore $imageStore,
    ) {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $operation = $args->getObject();
        if (!$operation instanceof Operation) {
            return;
        }

        $aars = $operation->getAars()->toArray();
        $this->removed[$operation->getId()] = [
            'aars' => array_map(static fn (OperationAAR $aar): int => $aar->getId(), $aars),
            'images' => array_merge(...array_map(
                static fn (OperationAAR $aar): array => [...$aar->getMapImages(), ...$aar->getIntelImages()],
                $aars,
            )),
        ];
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->removed === []) {
            return;
        }

        $removed = $this->removed;
        $this->removed = [];

        foreach ($removed as $operationId => $gone) {
            if ($args->getObjectManager()->find(Operation::class, $operationId) !== null) {
                continue;
            }

            $this->serviceRecordRepository->deleteForOperation($operationId, $gone['aars']);
            $this->imageStore->delete($gone['images']);
        }
    }
}
