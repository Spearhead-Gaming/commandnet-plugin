<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Service\AarDefaults;
use PHPUnit\Framework\TestCase;

class AarDefaultsTest extends TestCase
{
    private Operation $patrol;

    protected function setUp(): void
    {
        $this->patrol = new Operation();
        $this->patrol->setType(OperationType::PATROL);
        $this->patrol->setTitle('Patrol');
        $this->patrol->setStartDateTime(new DateTime('2026-09-26 20:00'));
    }

    private function join(string $displayName, ?string $callsign, RsvpStatus $status = RsvpStatus::ATTENDING): void
    {
        $user = new User();
        $user->setDisplayName($displayName);
        $soldier = new SoldierProfile($user);
        $soldier->setCallsign($callsign);
        $rsvp = new OperationRSVP($this->patrol, $soldier);
        $rsvp->setStatus($status);
        $this->patrol->getRsvps()->add($rsvp);
    }

    public function testListsTheCallsignsOfWhoJoinedInOrder(): void
    {
        $this->join('Pvt Roe', 'Viper');
        $this->join('Cpl Doe', 'Anvil');

        self::assertSame('Anvil, Viper', AarDefaults::callsigns($this->patrol));
    }

    public function testFallsBackToTheNameWhereThereIsNoCallsign(): void
    {
        $this->join('Cpl Doe', null);
        $this->join('Pvt Roe', '  ');

        self::assertSame('Cpl Doe, Pvt Roe', AarDefaults::callsigns($this->patrol));
    }

    public function testLeavesOutAnyoneWhoDidNotJoin(): void
    {
        $this->join('Cpl Doe', 'Anvil');
        $this->join('Sgt Maybe', 'Ghost', RsvpStatus::MAYBE);
        $this->join('Sgt No', 'Nope', RsvpStatus::DECLINED);

        self::assertSame('Anvil', AarDefaults::callsigns($this->patrol));
    }

    public function testIsEmptyWhenNobodyJoined(): void
    {
        self::assertSame('', AarDefaults::callsigns($this->patrol));
    }
}
