<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use DateTimeImmutable;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\AarStatus;
use MajesticDev\CommandNet\Entity\Enum\OperationStatus;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationAAR;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Service\EventRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EventRulesTest extends TestCase
{
    private EventRules $rules;

    protected function setUp(): void
    {
        $this->rules = new EventRules();
    }

    /**
     * @return array<string, array{OperationType, bool}>
     */
    public static function awolByType(): array
    {
        return [
            'operation' => [OperationType::OPERATION, true],
            'patrol' => [OperationType::PATROL, false],
            'fun day' => [OperationType::FUN_DAY, false],
            'training' => [OperationType::TRAINING, false],
            'meeting' => [OperationType::MEETING, false],
            'other' => [OperationType::OTHER, false],
        ];
    }

    #[DataProvider('awolByType')]
    public function testOnlyOperationsCountTowardAwol(OperationType $type, bool $counts): void
    {
        $this->assertSame($counts, $this->rules->countsTowardAwol($type));
    }

    /**
     * @return array<string, array{OperationType, bool}>
     */
    public static function rosterByType(): array
    {
        return [
            'operation' => [OperationType::OPERATION, true],
            'training' => [OperationType::TRAINING, true],
            'meeting' => [OperationType::MEETING, true],
            'other' => [OperationType::OTHER, true],
            'patrol' => [OperationType::PATROL, false],
            'fun day' => [OperationType::FUN_DAY, false],
        ];
    }

    #[DataProvider('rosterByType')]
    public function testOnlyPatrolsAndFunDaysExpectJustTheJoiners(OperationType $type, bool $fullRoster): void
    {
        $this->assertSame($fullRoster, $this->rules->expectsFullRoster($type));
    }

    public function testOnlyPatrolsRequireAnAar(): void
    {
        foreach (OperationType::cases() as $type) {
            $this->assertSame($type === OperationType::PATROL, $this->rules->requiresAar($type), $type->label());
        }
    }

    public function testOperationsCreditOnAttendanceAlone(): void
    {
        $this->assertTrue($this->rules->creditsCombat($this->event(OperationType::OPERATION)));
    }

    public function testPatrolsCreditOnlyOnceAnAarIsFiled(): void
    {
        $patrol = $this->event(OperationType::PATROL);
        $this->assertFalse($this->rules->creditsCombat($patrol));

        $this->fileAar($patrol);
        $this->assertTrue($this->rules->creditsCombat($patrol));
    }

    public function testFunDaysNeverCredit(): void
    {
        $funDay = $this->event(OperationType::FUN_DAY);
        $this->fileAar($funDay);

        $this->assertFalse($this->rules->creditsCombat($funDay));
    }

    public function testTrainingMeetingAndOtherKeepTheirOldRuleOfNeedingAnAar(): void
    {
        foreach ([OperationType::TRAINING, OperationType::MEETING, OperationType::OTHER] as $type) {
            $event = $this->event($type);
            $this->assertFalse($this->rules->creditsCombat($event), $type->label());
            $this->fileAar($event);
            $this->assertTrue($this->rules->creditsCombat($event), $type->label());
        }
    }

    public function testAarIsNotRequiredForOperationsOrCancelledPatrols(): void
    {
        $now = new DateTimeImmutable('2030-01-01');

        $this->assertSame(AarStatus::NOT_REQUIRED, $this->rules->aarStatus($this->event(OperationType::OPERATION), $now));

        $cancelled = $this->event(OperationType::PATROL);
        $cancelled->setStatus(OperationStatus::CANCELLED);
        $this->assertSame(AarStatus::NOT_REQUIRED, $this->rules->aarStatus($cancelled, $now));
    }

    public function testPatrolAarGoesFromNotYetDueToDueToOverdue(): void
    {
        $patrol = $this->patrol('2026-09-24 20:00', '2026-09-24 22:00');

        $this->assertSame(AarStatus::NOT_YET_DUE, $this->aarStatusAt($patrol, '2026-09-24 21:59'));
        $this->assertSame(AarStatus::DUE, $this->aarStatusAt($patrol, '2026-09-24 22:00'), 'Due as soon as the patrol ends.');
        $this->assertSame(AarStatus::DUE, $this->aarStatusAt($patrol, '2026-09-25 22:00'), 'Still due exactly 24 hours after it ended.');
        $this->assertSame(AarStatus::OVERDUE, $this->aarStatusAt($patrol, '2026-09-25 22:01'));
    }

    public function testDeadlineIs24HoursAfterTheEnd(): void
    {
        $patrol = $this->patrol('2026-09-24 20:00', '2026-09-24 22:00');

        $this->assertSame('2026-09-25 22:00', $this->rules->aarDueAt($patrol)->format('Y-m-d H:i'));
    }

    public function testPatrolWithoutAnEndTimeCountsFromItsStart(): void
    {
        $patrol = $this->patrol('2026-09-24 20:00', null);

        $this->assertSame(AarStatus::DUE, $this->aarStatusAt($patrol, '2026-09-25 19:59'));
        $this->assertSame(AarStatus::OVERDUE, $this->aarStatusAt($patrol, '2026-09-25 20:01'));
    }

    public function testFilingTheAarClearsAnOverdueState(): void
    {
        $patrol = $this->patrol('2026-09-24 20:00', '2026-09-24 22:00');
        $this->assertSame(AarStatus::OVERDUE, $this->aarStatusAt($patrol, '2026-10-01 00:00'));

        $this->fileAar($patrol);

        $this->assertSame(AarStatus::FILED, $this->aarStatusAt($patrol, '2026-10-01 00:00'));
    }

    public function testRemindsOnceWhenDueAndOnceWhenOverdue(): void
    {
        $patrol = $this->patrol('2026-09-24 20:00', '2026-09-24 22:00');
        $hour = 3600;
        $reminder = fn (string $now): ?AarStatus => $this->rules->reminderFor($patrol, new DateTimeImmutable($now), $hour);

        $this->assertNull($reminder('2026-09-24 21:30'), 'Not over yet.');
        $this->assertSame(AarStatus::DUE, $reminder('2026-09-24 22:30'));
        $this->assertNull($reminder('2026-09-24 23:30'), 'The due reminder is not repeated.');
        $this->assertNull($reminder('2026-09-25 12:00'));
        $this->assertSame(AarStatus::OVERDUE, $reminder('2026-09-25 22:30'));
        $this->assertNull($reminder('2026-09-25 23:30'), 'The overdue reminder is not repeated.');
    }

    public function testNoReminderOnceFiledOrCancelled(): void
    {
        $filed = $this->patrol('2026-09-24 20:00', '2026-09-24 22:00');
        $this->fileAar($filed);
        $this->assertNull($this->rules->reminderFor($filed, new DateTimeImmutable('2026-09-24 22:30'), 3600));

        $cancelled = $this->patrol('2026-09-24 20:00', '2026-09-24 22:00');
        $cancelled->setStatus(OperationStatus::CANCELLED);
        $this->assertNull($this->rules->reminderFor($cancelled, new DateTimeImmutable('2026-09-24 22:30'), 3600));
    }

    public function testLeaderOfAPatrolMarksAttendanceButNotForOthers(): void
    {
        $leader = $this->createStub(User::class);
        $other = $this->createStub(User::class);
        $patrol = $this->event(OperationType::PATROL);
        $patrol->setLeader($leader);
        $someoneElsesPatrol = $this->event(OperationType::PATROL);
        $someoneElsesPatrol->setLeader($other);

        $this->assertTrue($this->rules->canMarkAttendance($patrol, $leader, false));
        $this->assertFalse($this->rules->canMarkAttendance($someoneElsesPatrol, $leader, false));
        $this->assertFalse($this->rules->canMarkAttendance($patrol, $other, false));
        $this->assertFalse($this->rules->canMarkAttendance($patrol, null, false));
        $this->assertTrue($this->rules->canMarkAttendance($someoneElsesPatrol, $leader, true), 'Staff can mark anything.');
    }

    public function testLeaderPowersDoNotExtendToOperations(): void
    {
        $user = $this->createStub(User::class);
        $operation = $this->event(OperationType::OPERATION);
        $operation->setLeader($user);

        $this->assertFalse($this->rules->canMarkAttendance($operation, $user, false));
    }

    public function testWhoMayFileAPatrolAar(): void
    {
        $leader = $this->createStub(User::class);
        $attendeeUser = $this->createStub(User::class);
        $bystander = $this->createStub(User::class);
        $patrol = $this->event(OperationType::PATROL);
        $patrol->setLeader($leader);
        $rsvp = new OperationRSVP($patrol, new SoldierProfile($attendeeUser));
        $rsvp->setAttended(true);
        $patrol->getRsvps()->add($rsvp);

        $this->assertTrue($this->rules->canFileAar($patrol, $leader, false, false), 'The leader needs no submit_aar permission.');
        $this->assertTrue($this->rules->canFileAar($patrol, $attendeeUser, false, false));
        $this->assertTrue($this->rules->canFileAar($patrol, $bystander, true, false), 'Staff can.');
        $this->assertFalse($this->rules->canFileAar($patrol, $bystander, false, false));
        $this->assertFalse($this->rules->canFileAar($patrol, $bystander, false, true), 'submit_aar alone is not enough for a patrol.');
    }

    public function testOtherEventsKeepTheSubmitAarPermissionRule(): void
    {
        $user = $this->createStub(User::class);
        $operation = $this->event(OperationType::OPERATION);

        $this->assertTrue($this->rules->canFileAar($operation, $user, false, true));
        $this->assertFalse($this->rules->canFileAar($operation, $user, true, false), 'Unchanged: staff still need submit_aar here.');
    }

    public function testJoinerCap(): void
    {
        $patrol = $this->event(OperationType::PATROL);
        $first = new SoldierProfile($this->createStub(User::class));
        $second = new SoldierProfile($this->createStub(User::class));
        $this->assertFalse($this->rules->isFull($patrol), 'No cap, never full.');

        $patrol->setMaxParticipants(1);
        $this->assertFalse($this->rules->isFull($patrol));

        $joined = new OperationRSVP($patrol, $first);
        $joined->setStatus(RsvpStatus::ATTENDING);
        $patrol->getRsvps()->add($joined);
        $this->assertTrue($this->rules->isFull($patrol, $second));
        $this->assertFalse($this->rules->isFull($patrol, $first), 'Someone already in is not blocked by their own place.');
    }

    private function aarStatusAt(Operation $operation, string $now): AarStatus
    {
        return $this->rules->aarStatus($operation, new DateTimeImmutable($now));
    }

    private function event(OperationType $type): Operation
    {
        $operation = new Operation();
        $operation->setType($type);
        $operation->setTitle('Event');
        $operation->setStartDateTime(new DateTime('2026-01-01 20:00'));

        return $operation;
    }

    private function patrol(string $start, ?string $end): Operation
    {
        $patrol = $this->event(OperationType::PATROL);
        $patrol->setStartDateTime(new DateTime($start));
        $patrol->setEndDateTime($end !== null ? new DateTime($end) : null);

        return $patrol;
    }

    private function fileAar(Operation $operation): void
    {
        $operation->getAars()->add(new OperationAAR($operation, $this->createStub(User::class)));
    }
}
