<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Doctrine\ORM\EntityManagerInterface;
use MajesticDev\CommandNet\Entity\Squad;
use MajesticDev\CommandNet\Entity\Unit;

/**
 * Builds a Unit/Squad subtree from a plain-text outline, so a unit's whole structure can be
 * pasted in at once instead of created one row at a time through the admin form. Deliberately
 * structure-only otherwise: it never touches Assignment, since a bare first name in a roster
 * sheet can't be reliably matched to a Forumify account - staffing people into the created
 * units/squads/positions stays the existing per-soldier assignment flow.
 *
 * Outline syntax: one node per line, indented 2 spaces per nesting level.
 *   - A bare line creates a Unit - a real command (e.g. a division, regiment, detachment).
 *     It can only nest under another Unit, not under a squad/team.
 *   - A line starting with "+ " creates a Squad - a squad or team within a Unit. It nests
 *     under the nearest enclosing Unit (making it a squad) or Squad (making it a team);
 *     Squad carries none of Unit's command-specific fields (commander, insignia, Discord
 *     role, vehicles, ORBAT export) since a squad/team isn't a command in its own right.
 *   - A line starting with "= " links a position (see PositionCatalog) to the nearest
 *     enclosing Unit or Squad, creating it in the catalog first if it doesn't already exist.
 *   - A line starting with "@" expands to one of PositionCatalog::PRESETS instead of typing
 *     the same handful of billets under every team/squad, e.g. "@Team".
 *   - A line starting with "#" is a comment, ignored regardless of indentation - lets a
 *     downloadable template document itself and still be pasted in as-is.
 *
 *   # Comments like this are ignored
 *   Detachment 7
 *     = Commanding Officer
 *     + Squad 1
 *       @Squad
 *       + Team 1
 *         @Team
 */
class OrbatImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PositionCatalog $positions,
    ) {
    }

    /**
     * @return array{units: int, squads: int, positions: int}
     */
    public function import(string $outline, Unit|Squad|null $parent = null): array
    {
        $lines = preg_split('/\R/', rtrim($outline)) ?: [];
        /** @var array<int, Unit|Squad|null> $parentAt depth => the node new lines at that depth attach under */
        $parentAt = [-1 => $parent];
        $maxDepth = -1;
        $counts = ['units' => 0, 'squads' => 0, 'positions' => 0];

        foreach ($lines as $i => $raw) {
            $trimmed = trim($raw);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $lineNumber = $i + 1;
            $depth = $this->depthOf($raw, $lineNumber);
            if ($depth > $maxDepth + 1) {
                throw new \DomainException("Line {$lineNumber}: indented further than the line above allows.");
            }
            $parentNode = $parentAt[$depth - 1] ?? null;

            $titles = match (true) {
                str_starts_with($trimmed, '= ') => [substr($trimmed, 2)],
                str_starts_with($trimmed, '@') => $this->preset($trimmed, $lineNumber),
                default => null,
            };
            if ($titles !== null) {
                if ($depth < 1) {
                    throw new \DomainException("Line {$lineNumber}: a position must be indented under a unit or squad/team.");
                }
                $counts['positions'] += $this->linkPositions($titles, $parentNode);
                continue;
            }

            if (str_starts_with($trimmed, '+ ')) {
                $parentAt[$depth] = $this->createSquad(substr($trimmed, 2), $parentNode, $lineNumber);
                $maxDepth = $depth;
                $counts['squads']++;
                continue;
            }

            $parentAt[$depth] = $this->createUnit($trimmed, $parentNode, $lineNumber);
            $maxDepth = $depth;
            $counts['units']++;
        }

        $this->em->flush();

        return $counts;
    }

    private function depthOf(string $raw, int $lineNumber): int
    {
        $indent = strlen($raw) - strlen(ltrim($raw, ' '));
        if ($indent % 2 !== 0) {
            throw new \DomainException("Line {$lineNumber}: indent by 2 spaces per level.");
        }

        return intdiv($indent, 2);
    }

    /**
     * @param array<string> $titles
     */
    private function linkPositions(array $titles, Unit|Squad|null $owner): int
    {
        $linked = 0;
        foreach ($this->positions->resolve($titles) as $position) {
            if ($owner === null || $owner->getPositions()->contains($position)) {
                continue;
            }
            $owner->addPosition($position);
            $linked++;
        }

        return $linked;
    }

    private function createSquad(string $name, Unit|Squad|null $parentNode, int $lineNumber): Squad
    {
        if (!$parentNode instanceof Unit && !$parentNode instanceof Squad) {
            throw new \DomainException("Line {$lineNumber}: a squad/team must be indented under a unit or another squad/team.");
        }

        $squad = new Squad($parentNode instanceof Squad ? $parentNode->getUnit() : $parentNode);
        if ($parentNode instanceof Squad) {
            $squad->setParent($parentNode);
        }
        $squad->setName($name);
        $this->em->persist($squad);

        return $squad;
    }

    private function createUnit(string $name, Unit|Squad|null $parentNode, int $lineNumber): Unit
    {
        if ($parentNode instanceof Squad) {
            throw new \DomainException("Line {$lineNumber}: a unit can't be nested under a squad/team - use \"+ {$name}\" here instead.");
        }

        $unit = new Unit();
        $unit->setName($name);
        $unit->setAbbreviation(mb_substr($name, 0, 30));
        $unit->setParent($parentNode);
        $this->em->persist($unit);

        return $unit;
    }

    /**
     * @return array<string>
     */
    private function preset(string $content, int $lineNumber): array
    {
        $key = trim(substr($content, 1));
        foreach (PositionCatalog::PRESETS as $name => $titles) {
            if (mb_strtolower($name) === mb_strtolower($key)) {
                return $titles;
            }
        }

        $known = implode(', ', array_keys(PositionCatalog::PRESETS));
        throw new \DomainException("Line {$lineNumber}: unknown preset \"@{$key}\" - known presets: {$known}.");
    }
}
