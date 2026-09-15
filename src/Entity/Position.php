<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Repository\PositionRepository;

/**
 * A reusable duty title, e.g. "Squad Leader", "Combat Medic", "Radio Operator".
 *
 * Positions aren't tied to a specific unit — the same "Squad Leader" title can be used
 * across every squad. The unit it's actually held in comes from the Assignment.
 */
#[ORM\Entity(PositionRepository::class)]
class Position
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 150)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
