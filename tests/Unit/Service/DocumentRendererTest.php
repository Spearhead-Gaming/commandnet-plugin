<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Document;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Position;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Specialty;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Service\DocumentRenderer;
use MajesticDev\CommandNet\Service\RankSettings;
use PHPUnit\Framework\TestCase;

class DocumentRendererTest extends TestCase
{
    public function testFillsInSoldierAndRecordValues(): void
    {
        $record = $this->record();

        $html = $this->renderer()->render(
            $this->document('<p>{user_rank_abbreviation} {user_name} of {user_unit} ({user_position}) received {record_title} on {record_date}: {record_description}</p>'),
            $record,
        );

        $this->assertSame('<p>SGT Alice of Alpha (Squad Leader) received Medal of Honor on 2026-06-01: Valor.</p>', $html);
    }

    public function testEveryListedPlaceholderIsFilledIn(): void
    {
        $content = implode(' ', array_map(
            static fn (string $name): string => '{' . $name . '}',
            array_keys(DocumentRenderer::PLACEHOLDERS),
        ));

        $html = $this->renderer()->render($this->document($content), $this->record());

        $this->assertStringNotContainsString('{', $html);
        $this->assertStringContainsString('Sergeant', $html);
        $this->assertStringContainsString('Medic', $html);
        $this->assertStringContainsString('Award', $html);
    }

    public function testValuesAreHtmlEscaped(): void
    {
        $record = $this->record(displayName: '<script>alert(1)</script>');

        $html = $this->renderer()->render($this->document('{user_name}'), $record);

        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testAValueContainingAPlaceholderIsNotExpandedAgain(): void
    {
        $record = $this->record();
        $record->getSoldier()->setCallsign('{user_rank}');

        $html = $this->renderer()->render($this->document('{user_callsign}'), $record);

        $this->assertSame('{user_rank}', $html);
    }

    public function testUnknownPlaceholdersAreLeftAsWritten(): void
    {
        $html = $this->renderer()->render($this->document('Hello {user_nmae}, {not a placeholder}'), $this->record());

        $this->assertSame('Hello {user_nmae}, {not a placeholder}', $html);
    }

    public function testMissingRankUnitAndPositionRenderAsEmpty(): void
    {
        $soldier = new SoldierProfile($this->user('Bob'));
        $record = new ServiceRecord($soldier, ServiceRecordType::NOTE, 'Note');

        $html = $this->renderer()->render($this->document('[{user_rank}][{user_unit}][{user_position}]'), $record);

        $this->assertSame('[][][]', $html);
    }

    public function testRankPlaceholdersAreBlankWhenRanksAreDisabled(): void
    {
        $html = $this->renderer(ranksEnabled: false)->render(
            $this->document('[{user_rank}][{user_rank_abbreviation}]'),
            $this->record(),
        );

        $this->assertSame('[][]', $html);
    }

    private function renderer(bool $ranksEnabled = true): DocumentRenderer
    {
        $rankSettings = $this->createMock(RankSettings::class);
        $rankSettings->method('isEnabled')->willReturn($ranksEnabled);

        return new DocumentRenderer($rankSettings);
    }

    private function record(string $displayName = 'Alice'): ServiceRecord
    {
        $rank = new Rank();
        $rank->setName('Sergeant');
        $rank->setAbbreviation('SGT');
        $unit = new Unit();
        $unit->setName('Alpha');
        $position = new Position();
        $position->setTitle('Squad Leader');
        $specialty = new Specialty();
        $specialty->setName('Combat Medic');

        $soldier = new SoldierProfile($this->user($displayName));
        $soldier->setRank($rank);
        $soldier->setSpecialty($specialty);
        $soldier->setCallsign('Viper');
        $soldier->setServiceNumber('1234');
        $assignment = new Assignment($soldier, $unit);
        $assignment->setPosition($position);
        $soldier->addAssignment($assignment);

        $record = new ServiceRecord($soldier, ServiceRecordType::AWARD, 'Medal of Honor');
        $record->setDescription('Valor.');
        $record->setDate(new DateTime('2026-06-01'));

        return $record;
    }

    private function user(string $displayName): User
    {
        $user = new User();
        $user->setDisplayName($displayName);

        return $user;
    }

    private function document(string $content): Document
    {
        $document = new Document();
        $document->setContent($content);

        return $document;
    }
}
