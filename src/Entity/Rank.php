<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\SortableEntityInterface;
use Forumify\Core\Entity\SortableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use MajesticDev\CommandNet\Repository\RankRepository;

/**
 * A rank in the seniority ladder, e.g. Private, Corporal, Sergeant.
 *
 * Position (from SortableEntityTrait) is the seniority order: 0 is the lowest rank,
 * higher numbers outrank lower ones. Reordering the admin list re-ranks everyone.
 */
#[ORM\Entity(RankRepository::class)]
#[ORM\Table(name: '`rank`')]
class Rank implements SortableEntityInterface
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;
    use SortableEntityTrait;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(length: 20)]
    private string $abbreviation = '';

    /**
     * Optional pay grade / tier label shown alongside the name, e.g. "E-4", "O-1".
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $payGrade = null;

    /**
     * Path to the insignia image, uploaded via UploadType and served from the asset storage.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $insignia = null;

    /** @var Collection<int, SoldierProfile> */
    #[ORM\OneToMany(mappedBy: 'rank', targetEntity: SoldierProfile::class)]
    private Collection $soldiers;

    public function __construct()
    {
        $this->soldiers = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAbbreviation(): string
    {
        return $this->abbreviation;
    }

    public function setAbbreviation(string $abbreviation): void
    {
        $this->abbreviation = $abbreviation;
    }

    public function getPayGrade(): ?string
    {
        return $this->payGrade;
    }

    public function setPayGrade(?string $payGrade): void
    {
        $this->payGrade = $payGrade;
    }

    public function getInsignia(): ?string
    {
        return $this->insignia;
    }

    public function setInsignia(?string $insignia): void
    {
        $this->insignia = $insignia;
    }

    /**
     * @return Collection<int, SoldierProfile>
     */
    public function getSoldiers(): Collection
    {
        return $this->soldiers;
    }

    public function __toString(): string
    {
        return $this->abbreviation !== '' ? $this->abbreviation : $this->name;
    }
}
