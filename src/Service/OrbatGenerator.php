<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use MajesticDev\CommandNet\Entity\Unit;

/**
 * Builds the CfgORBAT block Arma 3 missions read for the in-game Order of Battle viewer. Units
 * become nested groups, a unit's vehicles become its assets (only those with an Arma classname),
 * and the commander and rank come from the unit's commander. The result is meant to be saved as a
 * file and #included from a mission's description.ext.
 */
class OrbatGenerator
{
    /** Smallest to largest; a unit with no size set gets the one matching how many echelons sit below it. */
    public const array SIZES = ['Squad', 'Platoon', 'Company', 'Battalion', 'Regiment', 'Brigade', 'Division', 'Corps', 'Army'];

    /** Config value => label. */
    public const array TYPES = [
        'Infantry' => 'Infantry',
        'Motorized' => 'Motorized',
        'Mechanized' => 'Mechanized',
        'Armored' => 'Armored',
        'Art' => 'Artillery',
        'Mortar' => 'Mortar',
        'Antiair' => 'Anti-air',
        'Recon' => 'Recon',
        'Air' => 'Air (helicopters)',
        'Plane' => 'Air (fixed wing)',
        'Uav' => 'UAV',
        'Naval' => 'Naval',
        'Medic' => 'Medical',
        'Maint' => 'Maintenance',
        'Support' => 'Support',
        'Service' => 'Service',
        'HQ' => 'Headquarters',
    ];

    /** Config value => label. */
    public const array SIDES = ['West' => 'BLUFOR', 'East' => 'OPFOR', 'Guer' => 'Independent', 'Civ' => 'Civilian'];

    /**
     * @param iterable<Unit> $roots the top-level units, in the order they should appear
     */
    public function generate(iterable $roots, string $side): string
    {
        $side = isset(self::SIDES[$side]) ? $side : 'West';

        $lines = ['class CfgORBAT', '{'];
        foreach ($roots as $root) {
            array_push($lines, ...$this->group($root, $side, 1));
        }
        $lines[] = '};';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array<string>
     */
    private function group(Unit $unit, string $side, int $depth): array
    {
        $pad = str_repeat('    ', $depth);
        $inner = $pad . '    ';

        $commander = $unit->getCommander();
        $properties = [
            'id' => (string)$unit->getId(),
            'idType' => '0',
            'side' => $this->string($side),
            'size' => $this->string($unit->getOrbatSize() ?: self::SIZES[min($this->height($unit), count(self::SIZES) - 1)]),
            'type' => $this->string($unit->getOrbatType() ?: 'Infantry'),
            'commander' => $this->string($commander !== null ? ($commander->getCallsign() ?? $commander->getUser()->getDisplayName()) : ''),
            'commanderRank' => $this->string($commander?->getRank()?->getName() ?? ''),
            'text' => $this->string($unit->getName()),
            'textShort' => $this->string($unit->getAbbreviation()),
            'description' => $this->string($unit->getDescription() ?? ''),
        ];

        $lines = [$pad . 'class Unit_' . $unit->getId(), $pad . '{'];
        foreach ($properties as $key => $value) {
            $lines[] = $inner . $key . ' = ' . $value . ';';
        }

        $assets = $this->assets($unit);
        if ($assets !== []) {
            $pairs = array_map(fn (string $class, int $count) => '{' . $this->string($class) . ', ' . $count . '}', array_keys($assets), $assets);
            $lines[] = $inner . 'assets[] = {' . implode(', ', $pairs) . '};';
        }

        foreach ($unit->getChildren() as $child) {
            array_push($lines, ...$this->group($child, $side, $depth + 1));
        }
        $lines[] = $pad . '};';

        return $lines;
    }

    /**
     * @return array<string, int> classname => how many
     */
    private function assets(Unit $unit): array
    {
        $assets = [];
        foreach ($unit->getVehicles() as $vehicle) {
            $classname = trim((string)$vehicle->getClassname());
            if ($classname !== '') {
                $assets[$classname] = ($assets[$classname] ?? 0) + 1;
            }
        }

        return $assets;
    }

    private function height(Unit $unit): int
    {
        $height = 0;
        foreach ($unit->getChildren() as $child) {
            $height = max($height, $this->height($child) + 1);
        }

        return $height;
    }

    /**
     * A config string: quotes are doubled and line breaks dropped, since config strings cannot span lines.
     */
    private function string(string $value): string
    {
        return '"' . str_replace('"', '""', preg_replace('/\s*[\r\n]+\s*/', ' ', $value) ?? '') . '"';
    }
}
