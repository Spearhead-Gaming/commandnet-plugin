<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Discord;

use DateTime;
use MajesticDev\CommandNet\Discord\Command\PatrolListCommand;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Repository\OperationRepository;
use MajesticDev\CommandNet\Tests\Support\DiscordCommandTestCase;

class PatrolListCommandTest extends DiscordCommandTestCase
{
    public function testNoUpcomingPatrols(): void
    {
        $result = $this->command([])->run($this->invocation('command-net-patrol-list'));

        $this->assertSame('No upcoming patrols.', $result->content);
    }

    public function testListsEachPatrolWithItsIdTitleTimeAndLink(): void
    {
        $patrol = $this->createStub(Operation::class);
        $patrol->method('getId')->willReturn(42);
        $patrol->method('getTitle')->willReturn('Night Recon');
        $patrol->method('getStartDateTime')->willReturn(new DateTime('2026-09-24 20:00'));

        $result = $this->command([$patrol])->run($this->invocation('command-net-patrol-list'));

        $this->assertStringContainsString('**#42** Night Recon - Thu, Sep 24 at 8:00 PM', (string)$result->content);
        $this->assertStringContainsString(self::URL, (string)$result->content);
    }

    /**
     * @param array<Operation> $patrols
     */
    private function command(array $patrols): PatrolListCommand
    {
        $operations = $this->createStub(OperationRepository::class);
        $operations->method('findUpcomingPatrols')->willReturn($patrols);

        return new PatrolListCommand($operations, $this->urls());
    }
}
