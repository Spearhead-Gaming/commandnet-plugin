<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\SortableEntityInterface;
use Forumify\Core\Entity\SortableEntityTrait;
use MajesticDev\CommandNet\Repository\QualificationRepository;

/**
 * A skill badge/qualification soldiers can earn, e.g. "Combat Medic", "Jumpmaster".
 * The Courses module can be configured to auto-issue one of these on graduation.
 */
#[ORM\Entity(QualificationRepository::class)]
class Qualification implements SortableEntityInterface
{
    use IdentifiableEntityTrait;
    use SortableEntityTrait;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $icon = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
