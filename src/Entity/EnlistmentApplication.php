<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\ApplicationStatus;
use MajesticDev\CommandNet\Repository\EnlistmentApplicationRepository;

/**
 * A request to join. Reviewing it (accept or decline) is what creates or restores the
 * SoldierProfile; until then the applicant has no personnel file and can't RSVP or report in.
 * Applications are kept after a decision as a record of who was let in and why not.
 */
#[ORM\Entity(EnlistmentApplicationRepository::class)]
class EnlistmentApplication
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $callsign = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $steamId = null;

    #[ORM\Column(type: 'text')]
    private string $motivation = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $experience = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $availability = null;

    #[ORM\Column(length: 20, enumType: ApplicationStatus::class)]
    private ApplicationStatus $status = ApplicationStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $reviewedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $decisionNote = null;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function getUser(): User
    {
        return $this->user;
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

    public function getMotivation(): string
    {
        return $this->motivation;
    }

    public function setMotivation(string $motivation): void
    {
        $this->motivation = $motivation;
    }

    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function setExperience(?string $experience): void
    {
        $this->experience = $experience;
    }

    public function getAvailability(): ?string
    {
        return $this->availability;
    }

    public function setAvailability(?string $availability): void
    {
        $this->availability = $availability;
    }

    public function getStatus(): ApplicationStatus
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === ApplicationStatus::PENDING;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function getReviewedAt(): ?DateTime
    {
        return $this->reviewedAt;
    }

    public function getDecisionNote(): ?string
    {
        return $this->decisionNote;
    }

    /**
     * Records the decision. Creating the personnel file is EnlistmentService's job.
     */
    public function decide(ApplicationStatus $status, ?User $reviewer, ?string $note): void
    {
        $this->status = $status;
        $this->reviewedBy = $reviewer;
        $this->reviewedAt = new DateTime();
        $this->decisionNote = $note;
    }

    public function __toString(): string
    {
        return $this->user->getDisplayName();
    }
}
