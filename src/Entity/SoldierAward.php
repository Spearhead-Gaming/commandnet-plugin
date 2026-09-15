<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Repository\SoldierAwardRepository;

#[ORM\Entity(SoldierAwardRepository::class)]
class SoldierAward
{
    use IdentifiableEntityTrait;

    #[ORM\ManyToOne(targetEntity: SoldierProfile::class, inversedBy: 'awards')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SoldierProfile $soldier;

    #[ORM\ManyToOne(targetEntity: Award::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Award $award;

    #[ORM\Column(type: 'date')]
    private DateTime $dateAwarded;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'awarded_by_id', onDelete: 'SET NULL')]
    private ?User $awardedBy = null;

    /**
     * The citation text explaining why the award was earned, shown on the personnel file.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $citation = null;

    public function __construct(SoldierProfile $soldier, Award $award)
    {
        $this->soldier = $soldier;
        $this->award = $award;
        $this->dateAwarded = new DateTime();
    }

    public function getSoldier(): SoldierProfile
    {
        return $this->soldier;
    }

    public function getAward(): Award
    {
        return $this->award;
    }

    public function getDateAwarded(): DateTime
    {
        return $this->dateAwarded;
    }

    public function setDateAwarded(DateTime $dateAwarded): void
    {
        $this->dateAwarded = $dateAwarded;
    }

    public function getAwardedBy(): ?User
    {
        return $this->awardedBy;
    }

    public function setAwardedBy(?User $awardedBy): void
    {
        $this->awardedBy = $awardedBy;
    }

    public function getCitation(): ?string
    {
        return $this->citation;
    }

    public function setCitation(?string $citation): void
    {
        $this->citation = $citation;
    }
}
