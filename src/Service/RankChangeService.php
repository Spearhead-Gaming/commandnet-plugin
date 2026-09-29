<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\GenericNotificationType;
use Forumify\Core\Notification\NotificationService;
use MajesticDev\CommandNet\Entity\Document;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Everything that has to follow a rank change, wherever it came from: the promotion action
 * and the admin personnel form both end here, so the timeline record, the rank Role and the
 * notification can't drift apart.
 */
class RankChangeService
{
    public function __construct(
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
        private readonly RankRoleSyncer $rankRoleSyncer,
        private readonly NotificationService $notificationService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function changeRank(SoldierProfile $soldier, Rank $newRank, ?Document $document = null): void
    {
        $previousRank = $soldier->getRank();
        $soldier->setRank($newRank);
        $this->soldierProfileRepository->save($soldier);

        $this->afterRankChange($soldier, $previousRank, $document);
    }

    /**
     * For callers that already applied the new rank themselves (a submitted form mutates the
     * managed entity in place), passing the rank it had before. The optional document is shown
     * with the resulting promotion/demotion record on the personnel file.
     */
    public function afterRankChange(SoldierProfile $soldier, ?Rank $previousRank, ?Document $document = null): void
    {
        $newRank = $soldier->getRank();
        if ($newRank === $previousRank) {
            return;
        }

        // Runs for a cleared rank too, so its role is revoked.
        $this->rankRoleSyncer->sync($soldier);

        if ($newRank === null) {
            // Clearing a rank entirely doesn't fit "promotion" or "demotion" - nothing to record.
            return;
        }

        $isPromotion = $previousRank === null || $newRank->getPosition() > $previousRank->getPosition();
        $record = new ServiceRecord(
            $soldier,
            $isPromotion ? ServiceRecordType::PROMOTION : ServiceRecordType::DEMOTION,
            (string) $newRank,
        );
        $record->setDocument($document);
        $this->serviceRecordRepository->save($record);

        $this->notificationService->sendNotification(new Notification(
            GenericNotificationType::TYPE,
            $soldier->getUser(),
            [
                'title' => $isPromotion ? 'Promoted' : 'Rank changed',
                'description' => ($isPromotion ? 'You have been promoted to ' : 'Your rank is now ') . $newRank->getName() . '.',
                'url' => $this->urlGenerator->generate('command_net_roster_profile', [
                    'username' => $soldier->getUser()->getUsername(),
                ]),
            ],
        ));
    }
}
