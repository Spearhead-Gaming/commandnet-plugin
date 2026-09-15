<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;

/**
 * One soldier's RSVP to one operation, and later, whether they actually showed up.
 * Splitting "intent" (status) from "reality" (attended) is what lets the Attendance
 * module report on no-shows separately from people who declined outright.
 */
#[ORM\Entity(OperationRSVPRepository::class)]
#[ORM\UniqueConstraint(name: 'operation_soldier_unique', columns: ['operation_id', 'soldier_id'])]
class OperationRSVP
{
    use IdentifiableEntityTrait;

    #[ORM\ManyToOne(targetEntity: Operation::class, inversedBy: 'rsvps')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Operation $operation;

    #[ORM\ManyToOne(targetEntity: SoldierProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SoldierProfile $soldier;

    #[ORM\Column(length: 20, enumType: RsvpStatus::class)]
    private RsvpStatus $status = RsvpStatus::NO_RESPONSE;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $respondedAt = null;

    /**
     * Null until a leader marks the roster after the fact. True/false from that point on,
     * regardless of what the soldier originally RSVP'd.
     */
    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $attended = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct(Operation $operation, SoldierProfile $soldier)
    {
        $this->operation = $operation;
        $this->soldier = $soldier;
    }

    public function getOperation(): Operation
    {
        return $this->operation;
    }

    public function getSoldier(): SoldierProfile
    {
        return $this->soldier;
    }

    public function getStatus(): RsvpStatus
    {
        return $this->status;
    }

    public function setStatus(RsvpStatus $status): void
    {
        $this->status = $status;
        $this->respondedAt = new DateTime();
    }

    public function getRespondedAt(): ?DateTime
    {
        return $this->respondedAt;
    }

    public function getAttended(): ?bool
    {
        return $this->attended;
    }

    public function setAttended(?bool $attended): void
    {
        $this->attended = $attended;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }
}
