<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DOMDocument;
use DOMElement;
use DOMImplementation;
use Forumify\Core\Repository\SettingRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the squad.xml Arma reads to show a unit. The elements follow the order the DTD asks for
 * (name, email, web, picture, title, then the members), and everything goes through DOM text nodes
 * and attributes so names with symbols such as & are escaped properly.
 */
class SquadXmlGenerator
{
    public function __construct(
        private readonly SquadXmlSettings $settings,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly SettingRepository $forumSettings,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function generate(): string
    {
        $settings = $this->settings->all();
        $name = $settings['name'] !== '' ? $settings['name'] : (string)$this->forumSettings->get('forumify.title');
        $title = $settings['title'] !== '' ? $settings['title'] : $name;
        $web = $settings['web'] !== ''
            ? $settings['web']
            : $this->urlGenerator->generate('forumify_core_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $implementation = new DOMImplementation();
        $document = $implementation->createDocument(null, '', $implementation->createDocumentType('squad', '', 'squad.dtd'));
        $document->formatOutput = true;

        $squad = $document->createElement('squad');
        $squad->setAttribute('nick', $settings['nick']);
        $document->appendChild($squad);

        $this->text($document, $squad, 'name', $name);
        $this->text($document, $squad, 'email', $settings['email'] !== '' ? $settings['email'] : 'N/A');
        $this->text($document, $squad, 'web', $web);
        if ($settings['picture'] !== null && $settings['picture'] !== '') {
            $this->text($document, $squad, 'picture', 'logo.paa');
        }
        $this->text($document, $squad, 'title', $title);

        foreach ($this->soldierProfileRepository->findForSquadXml() as $soldier) {
            $displayName = $soldier->getUser()->getDisplayName();
            $member = $document->createElement('member');
            $member->setAttribute('id', (string)$soldier->getSteamId());
            $member->setAttribute('nick', $soldier->getCallsign() ?? $displayName);
            $squad->appendChild($member);

            $this->text($document, $member, 'name', $displayName);
            $this->text($document, $member, 'email', 'N/A');
            $this->text($document, $member, 'icq', 'N/A');
            $unit = $soldier->getPrimaryAssignment()?->getUnit();
            if ($unit !== null) {
                $this->text($document, $member, 'remark', $unit->getName());
            }
        }

        return (string)$document->saveXML();
    }

    private function text(DOMDocument $document, DOMElement $parent, string $tag, string $value): void
    {
        $element = $document->createElement($tag);
        $element->appendChild($document->createTextNode($value));
        $parent->appendChild($element);
    }
}
