<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

/**
 * The personnel record for a single enlisted member. One-to-one with the forumify User —
 * this is deliberately a separate entity rather than fields bolted onto User, so the
 * plugin can be uninstalled without touching core user data.
 */
#[ORM\Entity(SoldierProfileRepository::class)]
class SoldierProfile
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Rank::class, inversedBy: 'soldiers')]
    #[ORM\JoinColumn(name: 'rank_id', onDelete: 'SET NULL')]
    private ?Rank $rank = null;

    #[ORM\Column(length: 30, nullable: true, unique: true)]
    private ?string $serviceNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $callsign = null;

    /**
     * SteamID64. Manually entered - forumify has no Steam identity provider to pull this
     * from (unlike Discord, which links via Forumify\OAuth\Entity\IdentityProviderUser).
     */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $steamId = null;

    #[ORM\Column(length: 20, enumType: SoldierStatus::class)]
    private SoldierStatus $status = SoldierStatus::ACTIVE;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $enlistmentDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $dischargeDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    /**
     * A full-body/uniform photo shown on the personnel file, distinct from the soldier's
     * forumify avatar - the same "uniform photo" concept MILHQ has, kept local to this
     * plugin's own profile rather than depending on that optional integration.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $uniformImage = null;

    /**
     * Cache of the latest Report In. Kept here (rather than always querying ReportIn)
     * so the roster list and AWOL checks are a single indexed column read.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $lastReportIn = null;

    /**
     * When the soldier last became Active again (from LOA, AWOL, ...). AWOL detection only
     * counts operations that started after this, so absences while on leave don't count against
     * them the moment they return. Null means never changed, so all history counts.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $activeSince = null;

    /**
     * True only while the current AWOL status was set by attendance detection, so an
     * admin-set AWOL is never auto-cleared. Any status change resets it - see setStatus().
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $awolAutoFlagged = false;

    /**
     * True while the current AWOL status came from failing to report in, so the next report
     * in restores Active. Reset on any status change, same as awolAutoFlagged.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $reportInFlagged = false;

    /** @var Collection<int, Assignment> */
    #[ORM\OneToMany(mappedBy: 'soldier', targetEntity: Assignment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $assignments;

    /** @var Collection<int, SoldierAward> */
    #[ORM\OneToMany(mappedBy: 'soldier', targetEntity: SoldierAward::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $awards;

    /** @var Collection<int, SoldierQualification> */
    #[ORM\OneToMany(mappedBy: 'soldier', targetEntity: SoldierQualification::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $qualifications;

    /** @var Collection<int, ServiceRecord> */
    #[ORM\OneToMany(mappedBy: 'soldier', targetEntity: ServiceRecord::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['date' => 'DESC'])]
    private Collection $serviceRecords;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->assignments = new ArrayCollection();
        $this->awards = new ArrayCollection();
        $this->qualifications = new ArrayCollection();
        $this->serviceRecords = new ArrayCollection();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRank(): ?Rank
    {
        return $this->rank;
    }

    public function setRank(?Rank $rank): void
    {
        $this->rank = $rank;
    }

    public function getServiceNumber(): ?string
    {
        return $this->serviceNumber;
    }

    public function setServiceNumber(?string $serviceNumber): void
    {
        $this->serviceNumber = $serviceNumber;
    }

    public function getCallsign(): ?string
    {
        return $this->callsign;
    }

    public function setCallsign(?string $callsign): void
    {
        $this->callsign = $callsign;
    }

    public function getSteamId(): ?string
    {
        return $this->steamId;
    }

    public function setSteamId(?string $steamId): void
    {
        $this->steamId = $steamId;
    }

    public function getStatus(): SoldierStatus
    {
        return $this->status;
    }

    public function setStatus(SoldierStatus $status): void
    {
        if ($status !== $this->status) {
            $this->awolAutoFlagged = false;
            $this->reportInFlagged = false;
            if ($status === SoldierStatus::ACTIVE) {
                $this->activeSince = new DateTime();
            }
        }
        $this->status = $status;
    }

    public function getEnlistmentDate(): ?DateTime
    {
        return $this->enlistmentDate;
    }

    public function setEnlistmentDate(?DateTime $enlistmentDate): void
    {
        $this->enlistmentDate = $enlistmentDate;
    }

    public function getDischargeDate(): ?DateTime
    {
        return $this->dischargeDate;
    }

    public function setDischargeDate(?DateTime $dischargeDate): void
    {
        $this->dischargeDate = $dischargeDate;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }

    public function getUniformImage(): ?string
    {
        return $this->uniformImage;
    }

    public function setUniformImage(?string $uniformImage): void
    {
        $this->uniformImage = $uniformImage;
    }

    public function getActiveSince(): ?DateTime
    {
        return $this->activeSince;
    }

    /**
     * False once discharged or retired, until they enlist again.
     */
    public function isEnlisted(): bool
    {
        return !in_array($this->status, [SoldierStatus::DISCHARGED, SoldierStatus::RETIRED], true);
    }

    public function isAwolAutoFlagged(): bool
    {
        return $this->awolAutoFlagged;
    }

    public function setAwolAutoFlagged(bool $awolAutoFlagged): void
    {
        $this->awolAutoFlagged = $awolAutoFlagged;
    }

    public function isReportInFlagged(): bool
    {
        return $this->reportInFlagged;
    }

    public function setReportInFlagged(bool $reportInFlagged): void
    {
        $this->reportInFlagged = $reportInFlagged;
    }

    public function getLastReportIn(): ?DateTime
    {
        return $this->lastReportIn;
    }

    public function setLastReportIn(?DateTime $lastReportIn): void
    {
        $this->lastReportIn = $lastReportIn;
    }

    /**
     * @return Collection<int, Assignment>
     */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function addAssignment(Assignment $assignment): void
    {
        if (!$this->assignments->contains($assignment)) {
            $this->assignments->add($assignment);
        }
    }

    /**
     * The assignment currently flagged as primary, if any. A soldier can hold several
     * secondary assignments (e.g. attached to a training cadre) alongside one primary billet.
     */
    public function getPrimaryAssignment(): ?Assignment
    {
        $match = $this->assignments
            ->filter(static fn (Assignment $a) => $a->isPrimary() && $a->getEndDate() === null)
            ->first();
        return $match !== false ? $match : null;
    }

    /**
     * @return Collection<int, SoldierAward>
     */
    public function getAwards(): Collection
    {
        return $this->awards;
    }

    /**
     * @return Collection<int, SoldierQualification>
     */
    public function getQualifications(): Collection
    {
        return $this->qualifications;
    }

    /**
     * @return Collection<int, ServiceRecord>
     */
    public function getServiceRecords(): Collection
    {
        return $this->serviceRecords;
    }

    public function __toString(): string
    {
        return $this->user->getDisplayName();
    }
}
