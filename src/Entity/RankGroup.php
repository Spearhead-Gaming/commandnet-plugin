<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Repository\RankGroupRepository;

/**
 * A separate promotion track, e.g. "Enlisted", "NCO", "Officer". Promotion eligibility
 * only ever looks one rank up within the same group, so the top of one track doesn't read
 * as "eligible" for the bottom of the next. Ranks without a group share one ladder.
 */
#[ORM\Entity(RankGroupRepository::class)]
class RankGroup
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 100)]
    private string $name = '';

    /**
     * @var Collection<int, Rank>
     */
    #[ORM\OneToMany(targetEntity: Rank::class, mappedBy: 'group')]
    private Collection $ranks;

    public function __construct()
    {
        $this->ranks = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return Collection<int, Rank>
     */
    public function getRanks(): Collection
    {
        return $this->ranks;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
