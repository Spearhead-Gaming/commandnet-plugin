<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Discord;

use MajesticDev\CommandNet\Discord\Command\PatrolLeaveCommand;
use MajesticDev\CommandNet\Discord\DiscordUserResolver;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\OperationRepository;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;
use MajesticDev\CommandNet\Tests\Support\DiscordCommandTestCase;

class PatrolLeaveCommandTest extends DiscordCommandTestCase
{
    public function testAttendingSoldierIsSetBackToNoResponseNotDeleted(): void
    {
        $soldier = $this->soldier();
        $patrol = $this->patrol();
        $rsvp = new OperationRSVP($patrol, $soldier);
        $rsvp->setStatus(RsvpStatus::ATTENDING);
        $patrol->getRsvps()->add($rsvp);
        $rsvps = $this->createMock(OperationRSVPRepository::class);
        $rsvps->expects($this->once())->method('save')->with($rsvp);

        $result = $this->command($soldier, $patrol, $rsvps)->run($this->invocation('command-net-patrol-leave', ['id' => 7]));

        $this->assertSame(RsvpStatus::NO_RESPONSE, $rsvp->getStatus());
        $this->assertSame("You're no longer marked as attending **Night Recon**.", $result->content);
    }

    public function testSoldierWhoNeverJoinedIsToldSo(): void
    {
        $rsvps = $this->createMock(OperationRSVPRepository::class);
        $rsvps->expects($this->never())->method('save');

        $result = $this->command($this->soldier(), $this->patrol(), $rsvps)->run($this->invocation('command-net-patrol-leave', ['id' => 7]));

        $this->assertSame("You aren't marked as attending **Night Recon**.", $result->content);
    }

    public function testNoPersonnelFileOrUnknownPatrol(): void
    {
        $noFile = $this->command(null, $this->patrol())->run($this->invocation('command-net-patrol-leave', ['id' => 7]));
        $noPatrol = $this->command($this->soldier(), null)->run($this->invocation('command-net-patrol-leave', ['id' => 7]));

        $this->assertSame('We could not find your personnel file.', $noFile->content);
        $this->assertSame('We could not find that patrol.', $noPatrol->content);
    }

    private function patrol(): Operation
    {
        $patrol = new Operation();
        $patrol->setTitle('Night Recon');

        return $patrol;
    }

    private function command(?SoldierProfile $soldier, ?Operation $patrol, ?OperationRSVPRepository $rsvps = null): PatrolLeaveCommand
    {
        $resolver = $this->createStub(DiscordUserResolver::class);
        $resolver->method('resolveSoldier')->willReturn($soldier);
        $operations = $this->createStub(OperationRepository::class);
        $operations->method('findPatrol')->willReturn($patrol);

        return new PatrolLeaveCommand($resolver, $operations, $rsvps ?? $this->createStub(OperationRSVPRepository::class));
    }
}
