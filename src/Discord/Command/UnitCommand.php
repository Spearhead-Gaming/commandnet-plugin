<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Discord\Command;

use MajesticDev\Discord\Api\DTO\DiscordCommandOption;
use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use MajesticDev\Discord\Api\DTO\DiscordEmbed;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;
use MajesticDev\Discord\Discord\DiscordCommandInterface;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Equipment;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\UnitRepository;
use MajesticDev\CommandNet\Service\RankSettings;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Mirrors MILHQ's "milhq-unit" Discord command: required name search, description +
 * roster embed, just pointed at Command Net's own Unit/Assignment entities.
 */
class UnitCommand implements DiscordCommandInterface
{
    private const int FIELD_LIMIT = 1024;

    public function __construct(
        private readonly UnitRepository $unitRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RankSettings $rankSettings,
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

        $vehicles = $unit->getVehicles()->toArray();
        if ($vehicles !== []) {
            $embed->addField('Vehicles', $this->joinWithinFieldLimit(array_map(static fn (Equipment $e): string => $e->getName(), $vehicles)));
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

    /**
     * Discord rejects an embed whose field value is over 1024 characters, which would fail the
     * whole reply, so a long list is cut short with a count of what was left out.
     *
     * @param array<string> $names
     */
    private function joinWithinFieldLimit(array $names): string
    {
        $full = implode(', ', $names);
        if (mb_strlen($full) <= self::FIELD_LIMIT) {
            return $full;
        }

        $kept = [];
        $length = 0;
        foreach ($names as $i => $name) {
            $suffix = sprintf(', …and %d more', count($names) - $i);
            $added = ($kept === [] ? 0 : 2) + mb_strlen($name);
            if ($length + $added + mb_strlen($suffix) > self::FIELD_LIMIT) {
                break;
            }
            $kept[] = $name;
            $length += $added;
        }

        $more = sprintf('…and %d more', count($names) - count($kept));

        return $kept === [] ? $more : implode(', ', $kept) . ', ' . $more;
    }

    private function formatSoldier(SoldierProfile $soldier): string
    {
        $rank = $this->rankSettings->isEnabled() ? $soldier->getRank() : null;
        $rankAbbreviation = $rank !== null ? $rank->getAbbreviation() . ' ' : '';

        return trim($rankAbbreviation . $soldier->getUser()->getDisplayName());
    }
}
