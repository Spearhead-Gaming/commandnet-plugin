<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Discord;

use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Discord\Command\PatrolCreateCommand;
use MajesticDev\CommandNet\Discord\DiscordUserResolver;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\OperationRepository;
use MajesticDev\CommandNet\Service\DeploymentLookup;
use MajesticDev\CommandNet\Service\RawPermissionChecker;
use MajesticDev\CommandNet\Tests\Support\DiscordCommandTestCase;

class PatrolCreateCommandTest extends DiscordCommandTestCase
{
    public function testEnlistedLeaderPostsAPatrolAndAutoAttends(): void
    {
        $user = $this->user();
        $soldier = $this->soldier($user);
        $operations = $this->createMock(OperationRepository::class);
        $operations->expects($this->once())->method('save')->with($this->callback(
            function (Operation $patrol) use ($user, $soldier): bool {
                $this->withId($patrol, 7);
                $this->assertSame(OperationType::PATROL, $patrol->getType());
                $this->assertSame('Night Recon', $patrol->getTitle());
                $this->assertSame($user, $patrol->getLeader());
                $this->assertSame('2026-09-24 20:00', $patrol->getStartDateTime()->format('Y-m-d H:i'));
                $this->assertCount(1, $patrol->getRsvps());
                $this->assertSame(RsvpStatus::ATTENDING, $patrol->getRsvps()->first()->getStatus());
                $this->assertSame($soldier, $patrol->getRsvps()->first()->getSoldier());

                return true;
            },
        ));

        $result = $this->command($user, $soldier, true, $operations)->run($this->invocation('command-net-patrol-create', [
            'title' => ' Night Recon ',
            'when' => '2026-09-24 20:00',
        ]));

        $embed = $result->embeds[0];
        $this->assertSame('Night Recon', $embed->title);
        $this->assertSame('Thursday, September 24 at 8:00 PM', $embed->description);
        $this->assertSame(self::URL, $embed->url);
    }

    public function testDischargedLeaderStillPostsButDoesNotAutoAttend(): void
    {
        $user = $this->user();
        $soldier = $this->soldier($user);
        $soldier->setStatus(SoldierStatus::DISCHARGED);
        $operations = $this->createMock(OperationRepository::class);
        $operations->expects($this->once())->method('save')->with($this->callback(
            function (Operation $patrol): bool {
                $this->withId($patrol, 7);

                return $patrol->getRsvps()->isEmpty();
            },
        ));

        $this->command($user, $soldier, true, $operations)->run($this->invocation('command-net-patrol-create', [
            'title' => 'Night Recon',
            'when' => '2026-09-24 20:00',
        ]));
    }

    public function testUnlinkedAccountAndMissingPermissionAreRefusedBeforeAnythingIsSaved(): void
    {
        $operations = $this->createMock(OperationRepository::class);
        $operations->expects($this->never())->method('save');
        $options = ['title' => 'Night Recon', 'when' => '2026-09-24 20:00'];

        $unlinked = $this->command(null, null, true, $operations)->run($this->invocation('command-net-patrol-create', $options));
        $forbidden = $this->command($this->user(), null, false, $operations)->run($this->invocation('command-net-patrol-create', $options));

        $this->assertStringContainsString('could not find your linked forum account', (string)$unlinked->content);
        $this->assertSame('You do not have permission to post a patrol.', $forbidden->content);
    }

    public function testBlankTitleAndBadDatesAreRejected(): void
    {
        $operations = $this->createMock(OperationRepository::class);
        $operations->expects($this->never())->method('save');
        $command = $this->command($this->user(), null, true, $operations);

        $noTitle = $command->run($this->invocation('command-net-patrol-create', ['title' => ' ', 'when' => '2026-09-24 20:00']));
        $this->assertSame('Please provide a title.', $noTitle->content);

        foreach (['tomorrow', '2026-13-45 99:99', '2026-02-30 20:00', '24/09/2026 20:00'] as $when) {
            $result = $command->run($this->invocation('command-net-patrol-create', ['title' => 'Recon', 'when' => $when]));
            $this->assertStringContainsString('did not parse', (string)$result->content, $when . ' must not be silently rolled into another date.');
        }
    }

    public function testUnknownDeploymentIsRefusedAndListsRecentOnes(): void
    {
        $operations = $this->createMock(OperationRepository::class);
        $operations->expects($this->never())->method('save');
        $lookup = $this->createStub(DeploymentLookup::class);
        $lookup->method('findByName')->willReturn(null);
        $lookup->method('recentNames')->willReturn(['Op Winter', 'Op Spring']);

        $result = $this->command($this->user(), null, true, $operations, $lookup)->run($this->invocation('command-net-patrol-create', [
            'title' => 'Recon',
            'when' => '2026-09-24 20:00',
            'deployment' => 'Op Summer',
        ]));

        $this->assertSame('We could not find a deployment called "Op Summer". Recent deployments: Op Winter, Op Spring.', $result->content);
    }

    private function command(?User $user, ?SoldierProfile $soldier, bool $mayCreate, OperationRepository $operations, ?DeploymentLookup $lookup = null): PatrolCreateCommand
    {
        $resolver = $this->createStub(DiscordUserResolver::class);
        $resolver->method('resolveUser')->willReturn($user);
        $resolver->method('resolveSoldier')->willReturn($soldier);
        $permissions = $this->createStub(RawPermissionChecker::class);
        $permissions->method('isGranted')->willReturn($mayCreate);

        return new PatrolCreateCommand($resolver, $permissions, $operations, $this->urls(), $lookup ?? $this->createStub(DeploymentLookup::class));
    }
}
