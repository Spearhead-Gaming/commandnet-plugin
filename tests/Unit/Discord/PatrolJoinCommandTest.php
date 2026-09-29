<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Discord;

use MajesticDev\CommandNet\Discord\Command\PatrolJoinCommand;
use MajesticDev\CommandNet\Discord\DiscordUserResolver;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\OperationRepository;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;
use MajesticDev\CommandNet\Service\EventRules;
use MajesticDev\CommandNet\Service\RawPermissionChecker;
use MajesticDev\CommandNet\Tests\Support\DiscordCommandTestCase;

class PatrolJoinCommandTest extends DiscordCommandTestCase
{
    public function testEnlistedSoldierWithPermissionIsMarkedAttending(): void
    {
        $patrol = $this->patrol();
        $soldier = $this->soldier();
        $rsvps = $this->createMock(OperationRSVPRepository::class);
        $rsvps->expects($this->once())->method('save')->with($this->callback(
            static fn (OperationRSVP $rsvp): bool => $rsvp->getStatus() === RsvpStatus::ATTENDING && $rsvp->getSoldier() === $soldier,
        ));

        $result = $this->command($soldier, true, $patrol, false, $rsvps)->run($this->invocation('command-net-patrol-join', ['id' => 7]));

        $this->assertSame("You're marked as attending **Night Recon**.", $result->content);
    }

    public function testUnknownOrDischargedSoldierCannotJoin(): void
    {
        $discharged = $this->soldier();
        $discharged->setStatus(SoldierStatus::DISCHARGED);

        foreach ([null, $discharged] as $soldier) {
            $result = $this->command($soldier, true, $this->patrol(), false)->run($this->invocation('command-net-patrol-join', ['id' => 7]));

            $this->assertStringContainsString('Only enlisted personnel can join a patrol', (string)$result->content);
        }
    }

    public function testMemberWithoutTheRsvpPermissionIsRefused(): void
    {
        $result = $this->command($this->soldier(), false, $this->patrol(), false)->run($this->invocation('command-net-patrol-join', ['id' => 7]));

        $this->assertSame('You do not have permission to RSVP.', $result->content);
    }

    public function testUnknownPatrolIsReported(): void
    {
        $result = $this->command($this->soldier(), true, null, false)->run($this->invocation('command-net-patrol-join', ['id' => 999]));

        $this->assertSame('We could not find that patrol.', $result->content);
    }

    public function testFullPatrolTurnsTheSoldierAwayWithoutSaving(): void
    {
        $rsvps = $this->createMock(OperationRSVPRepository::class);
        $rsvps->expects($this->never())->method('save');

        $result = $this->command($this->soldier(), true, $this->patrol(), true, $rsvps)->run($this->invocation('command-net-patrol-join', ['id' => 7]));

        $this->assertSame('That patrol is full.', $result->content);
    }

    private function patrol(): Operation
    {
        $patrol = new Operation();
        $patrol->setTitle('Night Recon');

        return $patrol;
    }

    private function command(?SoldierProfile $soldier, bool $mayRsvp, ?Operation $patrol, bool $full, ?OperationRSVPRepository $rsvps = null): PatrolJoinCommand
    {
        $resolver = $this->createStub(DiscordUserResolver::class);
        $resolver->method('resolveSoldier')->willReturn($soldier);
        $permissions = $this->createStub(RawPermissionChecker::class);
        $permissions->method('isGranted')->willReturn($mayRsvp);
        $operations = $this->createStub(OperationRepository::class);
        $operations->method('findPatrol')->willReturn($patrol);
        $rules = $this->createStub(EventRules::class);
        $rules->method('isFull')->willReturn($full);

        return new PatrolJoinCommand($resolver, $permissions, $operations, $rsvps ?? $this->createStub(OperationRSVPRepository::class), $rules);
    }
}
