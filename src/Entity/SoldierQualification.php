<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Repository\SoldierQualificationRepository;

#[ORM\Entity(SoldierQualificationRepository::class)]
class SoldierQualification
{
    use IdentifiableEntityTrait;

    #[ORM\ManyToOne(targetEntity: SoldierProfile::class, inversedBy: 'qualifications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SoldierProfile $soldier;

    #[ORM\ManyToOne(targetEntity: Qualification::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Qualification $qualification;

    #[ORM\Column(type: 'date')]
    private DateTime $dateEarned;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'issued_by_id', onDelete: 'SET NULL')]
    private ?User $issuedBy = null;

    public function __construct(SoldierProfile $soldier, Qualification $qualification)
    {
        $this->soldier = $soldier;
        $this->qualification = $qualification;
        $this->dateEarned = new DateTime();
    }

    public function getSoldier(): SoldierProfile
    {
        return $this->soldier;
    }

    public function getQualification(): Qualification
    {
        return $this->qualification;
    }

    public function getDateEarned(): DateTime
    {
        return $this->dateEarned;
    }

    public function setDateEarned(DateTime $dateEarned): void
    {
        $this->dateEarned = $dateEarned;
    }

    public function getIssuedBy(): ?User
    {
        return $this->issuedBy;
    }

    public function setIssuedBy(?User $issuedBy): void
    {
        $this->issuedBy = $issuedBy;
    }
}
