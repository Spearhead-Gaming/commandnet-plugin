<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\AarStatus;
use MajesticDev\CommandNet\Entity\Enum\OperationStatus;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\SoldierProfile;

/**
 * What each kind of event means for AWOL, combat credit, the expected roster and the AAR. The
 * AWOL, attendance and AAR code all ask this class instead of looking at the type themselves,
 * so a rule changes in one place.
 *
 * Operations earn combat credit on attendance alone and are the only events that count toward
 * AWOL. Patrols and Fun-Days are voluntary, so only those who join are expected; a patrol earns
 * credit on attendance plus a filed AAR and must have one. Training, Meeting and Other keep
 * their behaviour from before event types had rules: everyone is expected and credit needs an
 * AAR (change creditsCombat() if trainings should stop earning combat records).
 *
 * ponytail: the AAR deadline is a constant, not an admin setting - add one if leadership wants
 * to tune it without a code change.
 */
class EventRules
{
    /** Hours after a patrol ends that its AAR is due. */
    public const int AAR_DEADLINE_HOURS = 24;

    /** Whether every active soldier (or the unit's members) is expected, rather than only those who join. */
    public function expectsFullRoster(OperationType $type): bool
    {
        return $type !== OperationType::PATROL && $type !== OperationType::FUN_DAY;
    }

    /** Only Operations count: a marked no-show anywhere else does not touch the AWOL streak. */
    public function countsTowardAwol(OperationType $type): bool
    {
        return $type === OperationType::OPERATION;
    }

    public function requiresAar(OperationType $type): bool
    {
        return $type === OperationType::PATROL;
    }

    /**
     * Whether attendees of this event currently earn combat records.
     */
    public function creditsCombat(Operation $operation): bool
    {
        return match ($operation->getType()) {
            OperationType::OPERATION => true,
            OperationType::FUN_DAY => false,
            default => !$operation->getAars()->isEmpty(),
        };
    }

    /** Events with no end time are treated as ending when they start. */
    public function endsAt(Operation $operation): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($operation->getEndDateTime() ?? $operation->getStartDateTime());
    }

    public function aarDueAt(Operation $operation): DateTimeImmutable
    {
        return $this->endsAt($operation)->add(new DateInterval('PT' . self::AAR_DEADLINE_HOURS . 'H'));
    }

    /**
     * Computed, never stored: from the event type, whether it is cancelled, its end time, the
     * deadline and whether an AAR exists. Cancelled events owe none.
     */
    public function aarStatus(Operation $operation, DateTimeInterface $now): AarStatus
    {
        if (!$this->requiresAar($operation->getType()) || $operation->getStatus() === OperationStatus::CANCELLED) {
            return AarStatus::NOT_REQUIRED;
        }
        if (!$operation->getAars()->isEmpty()) {
            return AarStatus::FILED;
        }
        if ($now < $this->endsAt($operation)) {
            return AarStatus::NOT_YET_DUE;
        }

        return $now > $this->aarDueAt($operation) ? AarStatus::OVERDUE : AarStatus::DUE;
    }

    /**
     * Which reminder the leader should get on a run at $now: "due" when the patrol ended and
     * "overdue" when the deadline passed, each only within $windowSeconds of that moment so
     * a run every $windowSeconds sends it once without any stored state.
     */
    public function reminderFor(Operation $operation, DateTimeInterface $now, int $windowSeconds): ?AarStatus
    {
        $status = $this->aarStatus($operation, $now);
        $since = match ($status) {
            AarStatus::DUE => $now->getTimestamp() - $this->endsAt($operation)->getTimestamp(),
            AarStatus::OVERDUE => $now->getTimestamp() - $this->aarDueAt($operation)->getTimestamp(),
            default => null,
        };

        return $since !== null && $since < $windowSeconds ? $status : null;
    }

    public function isLeader(Operation $operation, ?User $user): bool
    {
        return $user !== null
            && $operation->getType() === OperationType::PATROL
            && $operation->getLeader() === $user;
    }

    /**
     * Staff (operations.manage) can always mark attendance; a patrol's own leader can too, and
     * nobody else's leader can.
     */
    public function canMarkAttendance(Operation $operation, ?User $user, bool $isStaff): bool
    {
        return $isStaff || $this->isLeader($operation, $user);
    }

    /**
     * Who may file an AAR. Patrols: the leader, someone marked as attended, or staff. Other
     * events keep the existing rule, decided by the submit_aar permission ($hasSubmitPermission).
     */
    public function canFileAar(Operation $operation, ?User $user, bool $isStaff, bool $hasSubmitPermission): bool
    {
        if ($operation->getType() !== OperationType::PATROL) {
            return $hasSubmitPermission;
        }
        if ($isStaff || $this->isLeader($operation, $user)) {
            return true;
        }

        foreach ($operation->getRsvps() as $rsvp) {
            if ($user !== null && $rsvp->getAttended() === true && $rsvp->getSoldier()->getUser() === $user) {
                return true;
            }
        }

        return false;
    }

    /** Whether the joiner cap (if any) is already full, not counting $except's own RSVP. */
    public function isFull(Operation $operation, ?SoldierProfile $except = null): bool
    {
        $cap = $operation->getMaxParticipants();
        if ($cap === null) {
            return false;
        }

        $attending = 0;
        foreach ($operation->getRsvps() as $rsvp) {
            if ($rsvp->getStatus() === RsvpStatus::ATTENDING && $rsvp->getSoldier() !== $except) {
                ++$attending;
            }
        }

        return $attending >= $cap;
    }
}
