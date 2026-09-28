<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Doctrine\ORM\EntityManagerInterface;
use MajesticDev\CommandNet\Entity\Position;
use MajesticDev\CommandNet\Repository\PositionRepository;

/**
 * Turns a plain list of position titles (typed directly on a unit's own management page, or
 * from an outline pasted into OrbatImporter) into Position entities, reusing an existing
 * catalog entry by title (case-insensitively) rather than creating a duplicate.
 */
class PositionCatalog
{
    /**
     * Standard billet sets for the self-service quick-fill buttons (see
     * MyUnitController/edit.html.twig) and OrbatImporter's "@Preset" outline shorthand - one
     * shared list so both stay in sync. Key is the preset name as typed after "@" (matched
     * case-insensitively); values are the position titles it expands to.
     *
     * @var array<string, array<string>>
     */
    public const array PRESETS = [
        'Team' => ['Team Lead', 'Medic', 'Team Member'],
        'Squad' => ['Squad Leader', 'Squad Medic'],
    ];

    /** @var array<string, Position>|null lowercased title => Position, loaded once per request */
    private ?array $cache = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PositionRepository $positions,
    ) {
    }

    /**
     * @param iterable<string> $titles
     * @return array<Position>
     */
    public function resolve(iterable $titles): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach ($this->positions->findAll() as $position) {
                $this->cache[mb_strtolower($position->getTitle())] = $position;
            }
        }

        $result = [];
        foreach ($titles as $title) {
            $title = trim($title);
            if ($title === '') {
                continue;
            }
            $key = mb_strtolower($title);
            if (!isset($this->cache[$key])) {
                $position = new Position();
                $position->setTitle($title);
                $this->em->persist($position);
                $this->cache[$key] = $position;
            }
            $result[] = $this->cache[$key];
        }

        return $result;
    }
}
