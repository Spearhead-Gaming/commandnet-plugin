<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Twig;

use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Service\DocumentRenderer;
use MajesticDev\CommandNet\Service\RankSettings;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Mirrors the 5-minute activity window Forumify\Admin\OnlineUsers uses, so the
 * count shown outside that live component always agrees with it.
 */
class CommandNetRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentRenderer $documentRenderer,
        private readonly RankSettings $rankSettings,
    ) {
    }

    public function ranksEnabled(): bool
    {
        return $this->rankSettings->isEnabled();
    }

    /**
     * The record document filled in for its soldier, or an empty string if it has none.
     */
    public function renderDocument(ServiceRecord $record): string
    {
        $document = $record->getDocument();

        return $document !== null ? $this->documentRenderer->render($document, $record) : '';
    }

    public function getOnlineCount(): int
    {
        $min = (new DateTime())->sub(new DateInterval('PT5M'));

        return (int)$this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.lastActivity > :min')
            ->setParameter('min', $min)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The soldier's whole timeline for the personnel file's Service Record tab, in the order the
     * profile keeps it. With ranks turned off, promotions and demotions are left out: their title
     * is the rank itself ("Sergeant"), so showing them would put rank back on a page that is meant
     * not to have it. The records stay stored and reappear if ranks are switched back on.
     *
     * @return array<ServiceRecord>
     */
    public function getServiceRecords(SoldierProfile $profile): array
    {
        $records = $profile->getServiceRecords()->toArray();
        if ($this->rankSettings->isEnabled()) {
            return $records;
        }

        return array_values(array_filter(
            $records,
            static fn (ServiceRecord $record): bool => !in_array($record->getType(), [ServiceRecordType::PROMOTION, ServiceRecordType::DEMOTION], true),
        ));
    }

    /**
     * The personnel file's per-tab record views (Award/Combat/Rank Record, etc.) are all
     * the same underlying timeline, just filtered by type - there's no need for separate
     * queries or entities per tab.
     *
     * @param array<string> $types ServiceRecordType values to include
     * @return array<ServiceRecord> newest first
     */
    public function getRecordsByType(SoldierProfile $profile, array $types): array
    {
        $records = array_values(array_filter(
            $profile->getServiceRecords()->toArray(),
            static fn (ServiceRecord $record): bool => in_array($record->getType()->value, $types, true),
        ));

        usort($records, static fn (ServiceRecord $a, ServiceRecord $b): int => $b->getDate() <=> $a->getDate());

        return $records;
    }

    /**
     * A short "3 months" / "1 year, 2 months" / "1 day" string, the way a roster page
     * usually shows time in service rather than the raw enlistment date.
     */
    public function getTimeInService(SoldierProfile $profile): ?string
    {
        $start = $profile->getEnlistmentDate();
        if ($start === null) {
            return null;
        }

        $end = $profile->getDischargeDate() ?? new DateTime();
        $interval = $start->diff($end);

        if ($interval->y > 0) {
            $years = $interval->y . ' year' . ($interval->y === 1 ? '' : 's');
            return $interval->m > 0
                ? $years . ', ' . $interval->m . ' month' . ($interval->m === 1 ? '' : 's')
                : $years;
        }

        if ($interval->m > 0) {
            return $interval->m . ' month' . ($interval->m === 1 ? '' : 's');
        }

        return $interval->d . ' day' . ($interval->d === 1 ? '' : 's');
    }
}
