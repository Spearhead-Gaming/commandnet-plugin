<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DOMDocument;
use DOMElement;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\SettingRepository;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\SquadXmlGenerator;
use MajesticDev\CommandNet\Service\SquadXmlSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SquadXmlGeneratorTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $config = [];
    /** @var array<SoldierProfile> */
    private array $soldiers = [];

    private function parse(): DOMDocument
    {
        $settings = $this->createMock(SquadXmlSettings::class);
        $settings->method('all')->willReturnCallback(fn () => array_replace(SquadXmlSettings::DEFAULTS, $this->config));
        $repository = $this->createMock(SoldierProfileRepository::class);
        $repository->method('findForSquadXml')->willReturnCallback(fn () => $this->soldiers);
        $forumSettings = $this->createMock(SettingRepository::class);
        $forumSettings->method('get')->willReturn('Spearhead Gaming');
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.test/');

        $xml = (new SquadXmlGenerator($settings, $repository, $forumSettings, $urlGenerator))->generate();

        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($xml), 'The output must be well-formed XML.');

        return $document;
    }

    /**
     * @return array<string> names of the direct child elements
     */
    private function childNames(?DOMElement $element): array
    {
        $names = [];
        foreach ($element?->childNodes ?? [] as $child) {
            if ($child instanceof DOMElement) {
                $names[] = $child->tagName;
            }
        }

        return $names;
    }

    public function testTheDocumentReferencesTheDtdAndFollowsItsElementOrder(): void
    {
        $this->config = ['nick' => 'SHG', 'picture' => 'logos/squad.paa'];
        $this->soldiers = [$this->soldier('Alice', '76561198000000001')];

        $document = $this->parse();

        $this->assertSame('squad', $document->doctype?->name);
        $this->assertSame('squad.dtd', $document->doctype?->systemId);
        $squad = $document->documentElement;
        $this->assertSame('SHG', $squad?->getAttribute('nick'));
        $this->assertSame(['name', 'email', 'web', 'picture', 'title', 'member'], $this->childNames($squad));
        $this->assertSame('logo.paa', $document->getElementsByTagName('picture')->item(0)?->textContent);
    }

    public function testDetailsFallBackToTheCommunityTitleAndSiteAddress(): void
    {
        $document = $this->parse();

        $this->assertSame('Spearhead Gaming', $document->getElementsByTagName('name')->item(0)?->textContent);
        $this->assertSame('Spearhead Gaming', $document->getElementsByTagName('title')->item(0)?->textContent);
        $this->assertSame('https://example.test/', $document->getElementsByTagName('web')->item(0)?->textContent);
        $this->assertSame('N/A', $document->getElementsByTagName('email')->item(0)?->textContent);
        $this->assertSame(0, $document->getElementsByTagName('picture')->length, 'No logo, no picture element.');
    }

    public function testConfiguredDetailsAreUsedInsteadOfTheFallbacks(): void
    {
        $this->config = ['name' => 'Spearhead', 'title' => 'The Spearhead', 'web' => 'https://spearhead.test', 'email' => 'ops@spearhead.test'];

        $document = $this->parse();

        $this->assertSame('Spearhead', $document->getElementsByTagName('name')->item(0)?->textContent);
        $this->assertSame('The Spearhead', $document->getElementsByTagName('title')->item(0)?->textContent);
        $this->assertSame('https://spearhead.test', $document->getElementsByTagName('web')->item(0)?->textContent);
        $this->assertSame('ops@spearhead.test', $document->getElementsByTagName('email')->item(0)?->textContent);
    }

    public function testAMemberCarriesTheirSteamIdCallsignAndUnit(): void
    {
        $this->soldiers = [
            $this->soldier('Alice', '76561198000000001', callsign: 'Viper', unit: 'Alpha Squad'),
            $this->soldier('Bob', '76561198000000002'),
        ];

        $document = $this->parse();

        $members = $document->getElementsByTagName('member');
        $first = $members->item(0);
        $this->assertInstanceOf(DOMElement::class, $first);
        $this->assertSame('76561198000000001', $first->getAttribute('id'));
        $this->assertSame('Viper', $first->getAttribute('nick'));
        $this->assertSame(['name', 'email', 'icq', 'remark'], $this->childNames($first));
        $this->assertSame('Alpha Squad', $first->getElementsByTagName('remark')->item(0)?->textContent);

        $second = $members->item(1);
        $this->assertInstanceOf(DOMElement::class, $second);
        $this->assertSame('Bob', $second->getAttribute('nick'), 'Without a callsign the display name is used.');
        $this->assertSame(['name', 'email', 'icq'], $this->childNames($second), 'No unit, no remark.');
    }

    public function testSymbolsInNamesAreEscapedSoTheXmlStaysValid(): void
    {
        $this->config = ['name' => 'Tom & Jerry <Squad>'];
        $this->soldiers = [$this->soldier('A & B <b>"x"</b>', '1', callsign: "O'Neil & Co", unit: 'Unit "One" & Two')];

        $document = $this->parse();

        $this->assertSame('Tom & Jerry <Squad>', $document->getElementsByTagName('name')->item(0)?->textContent);
        $member = $document->getElementsByTagName('member')->item(0);
        $this->assertInstanceOf(DOMElement::class, $member);
        $this->assertSame("O'Neil & Co", $member->getAttribute('nick'));
        $this->assertSame('A & B <b>"x"</b>', $member->getElementsByTagName('name')->item(0)?->textContent);
        $this->assertSame('Unit "One" & Two', $member->getElementsByTagName('remark')->item(0)?->textContent);
    }

    public function testWithNoMembersTheDocumentIsStillValidXml(): void
    {
        $document = $this->parse();

        $this->assertSame(0, $document->getElementsByTagName('member')->length);
    }

    private function soldier(string $displayName, string $steamId, ?string $callsign = null, ?string $unit = null): SoldierProfile
    {
        $user = new User();
        $user->setDisplayName($displayName);
        $soldier = new SoldierProfile($user);
        $soldier->setSteamId($steamId);
        $soldier->setCallsign($callsign);
        if ($unit !== null) {
            $unitEntity = new Unit();
            $unitEntity->setName($unit);
            $soldier->addAssignment(new Assignment($soldier, $unitEntity));
        }

        return $soldier;
    }
}
