<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Discord;

use MajesticDev\CommandNet\Discord\Command\PatrolAarCommand;
use MajesticDev\CommandNet\Discord\DiscordUserResolver;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationAAR;
use MajesticDev\CommandNet\Repository\OperationAARRepository;
use MajesticDev\CommandNet\Repository\OperationRepository;
use MajesticDev\CommandNet\Service\AarImageException;
use MajesticDev\CommandNet\Service\AarImageStore;
use MajesticDev\CommandNet\Service\EventRules;
use MajesticDev\CommandNet\Service\OperationAttendanceService;
use MajesticDev\CommandNet\Tests\Support\DiscordCommandTestCase;
use RuntimeException;

class PatrolAarCommandTest extends DiscordCommandTestCase
{
    private const array COMPLETE = [
        'id' => 7,
        'tasking' => 'Recon the ridge',
        'callsigns' => 'Ghost 1-1',
        'fkia' => 'None',
        'ekia' => '3',
        'report' => "Line one <b>\nLine two",
        'map_urls' => '["https://cdn/map.png"]',
        'intel_urls' => '["https://cdn/intel.png"]',
    ];

    public function testLeaderFilesTheReportAndCombatRecordsAreSynced(): void
    {
        $patrol = $this->patrol();
        $images = $this->createStub(AarImageStore::class);
        $images->method('importFromDiscord')->willReturnCallback(
            static fn (array $urls, string $label): array => ['stored/' . $label . '.png'],
        );
        $aars = $this->createMock(OperationAARRepository::class);
        $aars->expects($this->once())->method('save')->with($this->callback(function (OperationAAR $aar): bool {
            $this->assertSame('Recon the ridge', $aar->getTasking());
            $this->assertSame('Ghost 1-1', $aar->getCallsigns());
            $this->assertSame(['stored/map.png'], $aar->getMapImages());
            $this->assertSame(['stored/intel.png'], $aar->getIntelImages());
            $this->assertSame('<p>Line one &lt;b&gt;<br />' . "\n" . 'Line two</p>', $aar->getSummary(), 'Typed text is escaped, line breaks kept.');

            return true;
        }));
        $attendance = $this->createMock(OperationAttendanceService::class);
        $attendance->expects($this->once())->method('syncCombatRecords')->with($patrol);

        $result = $this->command($patrol, true, $images, $aars, $attendance)->run($this->invocation('command-net-patrol-aar', self::COMPLETE));

        $this->assertSame('After-action report filed for **Night Recon**.', $result->content);
        $this->assertCount(1, $patrol->getAars());
    }

    public function testOnlyTheLeaderMayFileFromDiscord(): void
    {
        $aars = $this->createMock(OperationAARRepository::class);
        $aars->expects($this->never())->method('save');

        $result = $this->command($this->patrol(), false, aars: $aars)->run($this->invocation('command-net-patrol-aar', self::COMPLETE));

        $this->assertStringContainsString("Only the patrol's own leader", (string)$result->content);
        $this->assertStringContainsString(self::URL, (string)$result->content, 'Points them at the web form instead.');
    }

    public function testIncompleteSubmissionGetsGuidanceAndNothingIsStored(): void
    {
        $images = $this->createMock(AarImageStore::class);
        $images->expects($this->never())->method('importFromDiscord');
        $aars = $this->createMock(OperationAARRepository::class);
        $aars->expects($this->never())->method('save');

        foreach ([['id' => 7], [...self::COMPLETE, 'report' => ' '], [...self::COMPLETE, 'map_urls' => '[]'], [...self::COMPLETE, 'intel_urls' => 'not json']] as $options) {
            $result = $this->command($this->patrol(), true, $images, $aars)->run($this->invocation('command-net-patrol-aar', $options));

            $this->assertStringContainsString('Submit AAR', (string)$result->content);
        }
    }

    public function testIntelImageFailureRemovesTheAlreadyStoredMapImages(): void
    {
        $images = $this->createMock(AarImageStore::class);
        $images->method('importFromDiscord')->willReturnCallback(static function (array $urls, string $label): array {
            if ($label === 'intel') {
                throw new AarImageException('That intel image is not a PNG.');
            }

            return ['stored/map.png'];
        });
        $images->expects($this->once())->method('delete')->with(['stored/map.png']);
        $aars = $this->createMock(OperationAARRepository::class);
        $aars->expects($this->never())->method('save');

        $result = $this->command($this->patrol(), true, $images, $aars)->run($this->invocation('command-net-patrol-aar', self::COMPLETE));

        $this->assertStringContainsString('That intel image is not a PNG.', (string)$result->content);
    }

    public function testFailureToSaveCleansUpBothImageSetsThenRethrows(): void
    {
        $images = $this->createMock(AarImageStore::class);
        $images->method('importFromDiscord')->willReturnCallback(
            static fn (array $urls, string $label): array => ['stored/' . $label . '.png'],
        );
        $images->expects($this->once())->method('delete')->with(['stored/map.png', 'stored/intel.png']);
        $aars = $this->createStub(OperationAARRepository::class);
        $aars->method('save')->willThrowException(new RuntimeException('db down'));

        $this->expectExceptionMessage('db down');

        $this->command($this->patrol(), true, $images, $aars)->run($this->invocation('command-net-patrol-aar', self::COMPLETE));
    }

    public function testUnlinkedAccountAndUnknownPatrol(): void
    {
        $unlinked = $this->command($this->patrol(), true, linked: false)->run($this->invocation('command-net-patrol-aar', ['id' => 7]));
        $missing = $this->command(null, true)->run($this->invocation('command-net-patrol-aar', ['id' => 7]));

        $this->assertStringContainsString('could not find your linked forum account', (string)$unlinked->content);
        $this->assertSame('We could not find that patrol.', $missing->content);
    }

    private function patrol(): Operation
    {
        $patrol = new Operation();
        $patrol->setTitle('Night Recon');
        $this->withId($patrol, 7);

        return $patrol;
    }

    private function command(
        ?Operation $patrol,
        bool $isLeader,
        ?AarImageStore $images = null,
        ?OperationAARRepository $aars = null,
        ?OperationAttendanceService $attendance = null,
        bool $linked = true,
    ): PatrolAarCommand {
        $resolver = $this->createStub(DiscordUserResolver::class);
        $resolver->method('resolveUser')->willReturn($linked ? $this->user() : null);
        $operations = $this->createStub(OperationRepository::class);
        $operations->method('findPatrol')->willReturn($patrol);
        $rules = $this->createStub(EventRules::class);
        $rules->method('isLeader')->willReturn($isLeader);

        return new PatrolAarCommand(
            $resolver,
            $operations,
            $aars ?? $this->createStub(OperationAARRepository::class),
            $attendance ?? $this->createStub(OperationAttendanceService::class),
            $rules,
            $this->urls(),
            $images ?? $this->createStub(AarImageStore::class),
        );
    }
}
