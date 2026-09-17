<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Discord\Command;

use Forumify\Discord\Api\DTO\DiscordCommandOption;
use Forumify\Discord\Api\DTO\DiscordCommandResult;
use Forumify\Discord\Api\DTO\DiscordEmbed;
use Forumify\Discord\Api\Resource\DiscordCommandRun;
use Forumify\Discord\Discord\DiscordCommandInterface;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\UnitRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Mirrors MILHQ's "milhq-unit" Discord command: required name search, description +
 * roster embed, just pointed at Command Net's own Unit/Assignment entities.
 */
class UnitCommand implements DiscordCommandInterface
{
    public function __construct(
        private readonly UnitRepository $unitRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getName(): string
    {
        return 'command-net-unit';
    }

    public function getDescription(): string
    {
        return 'Shows Command Net unit information.';
    }

    public function getOptions(): array
    {
        return [
            new DiscordCommandOption()
                ->setName('name')
                ->setDescription('The (partial) name of the unit to look up, i.e.: "1st Squad".')
                ->setRequired(),
        ];
    }

    public function run(DiscordCommandRun $command): DiscordCommandResult
    {
        $result = new DiscordCommandResult();

        $name = trim($command->options['name'] ?? '');
        if ($name === '') {
            $result->content = 'Please provide a unit name to search for.';
            return $result;
        }

        $units = $this->unitRepository->findByNameLike($name);
        $unit = reset($units);
        if (!$unit) {
            $result->content = "We could not find any units matching \"$name\".";
            return $result;
        }

        $result->embeds[] = $this->createEmbed($unit);
        return $result;
    }

    private function createEmbed(Unit $unit): DiscordEmbed
    {
        $embed = new DiscordEmbed(
            title: $unit->getName(),
            description: $unit->getDescription(),
            url: $this->urlGenerator->generate('command_net_units', [], UrlGeneratorInterface::ABSOLUTE_URL),
        );

        $abbreviation = $unit->getAbbreviation();
        if (!empty($abbreviation)) {
            $embed->addField('Abbreviation', $abbreviation, true);
        }

        $commander = $unit->getCommander();
        if ($commander !== null) {
            $embed->addField('Commander', $this->formatSoldier($commander), true);
        }

        $roster = array_filter($unit->getAssignments()->toArray(), fn (Assignment $a) => $a->isActive());
        if (!empty($roster)) {
            $lines = array_map(
                fn (Assignment $a) => '**' . $this->formatSoldier($a->getSoldier()) . '** ' . ($a->getPosition()?->getTitle() ?? ''),
                $roster,
            );
            $embed->addField('Roster', implode("\n", $lines));
        }

        return $embed;
    }

    private function formatSoldier(SoldierProfile $soldier): string
    {
        $rank = $soldier->getRank();
        $rankAbbreviation = $rank !== null ? $rank->getAbbreviation() . ' ' : '';

        return trim($rankAbbreviation . $soldier->getUser()->getDisplayName());
    }
}
