<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\Role;
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

    /**
     * Forumify Role granted while this is a soldier's rank (see RankRoleSyncer). Mapping it to a
     * Discord role is the Discord plugin's own job.
     */
    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(name: 'role_id', onDelete: 'SET NULL')]
    private ?Role $role = null;

    /**
     * The promotion track this rank belongs to; promotion only moves within a group.
     * Ranks with no group share one ladder.
     */
    #[ORM\ManyToOne(targetEntity: RankGroup::class, inversedBy: 'ranks')]
    #[ORM\JoinColumn(name: 'group_id', onDelete: 'SET NULL')]
    private ?RankGroup $group = null;

    /**
     * Requirements to be promoted INTO this rank, checked by PromotionEligibility.
     * Null means no minimum time in grade.
     */
    #[ORM\Column(nullable: true)]
    private ?int $minTimeInGradeDays = null;

    /** @var Collection<int, Qualification> */
    #[ORM\ManyToMany(targetEntity: Qualification::class)]
    #[ORM\JoinTable(name: 'rank_required_qualification')]
    private Collection $requiredQualifications;

    /** @var Collection<int, SoldierProfile> */
    #[ORM\OneToMany(mappedBy: 'rank', targetEntity: SoldierProfile::class)]
    private Collection $soldiers;

    public function __construct()
    {
        $this->soldiers = new ArrayCollection();
        $this->requiredQualifications = new ArrayCollection();
    }

    public function getMinTimeInGradeDays(): ?int
    {
        return $this->minTimeInGradeDays;
    }

    public function setMinTimeInGradeDays(?int $minTimeInGradeDays): void
    {
        $this->minTimeInGradeDays = $minTimeInGradeDays;
    }

    public function getGroup(): ?RankGroup
    {
        return $this->group;
    }

    public function setGroup(?RankGroup $group): void
    {
        $this->group = $group;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): void
    {
        $this->role = $role;
    }

    /**
     * @return Collection<int, Qualification>
     */
    public function getRequiredQualifications(): Collection
    {
        return $this->requiredQualifications;
    }

    public function addRequiredQualification(Qualification $qualification): void
    {
        if (!$this->requiredQualifications->contains($qualification)) {
            $this->requiredQualifications->add($qualification);
        }
    }

    public function removeRequiredQualification(Qualification $qualification): void
    {
        $this->requiredQualifications->removeElement($qualification);
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
