<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\BlameableEntityTrait;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use MajesticDev\CommandNet\Entity\Enum\OperationStatus;
use MajesticDev\CommandNet\Entity\Enum\OperationType;

/**
 * Everything about Operation that doesn't depend on the optional calendar plugin - split
 * out so Operation itself can be declared twice (with or without the calendar relations)
 * without duplicating the rest of the entity. See Operation.php.
 */
trait OperationFields
{
    use IdentifiableEntityTrait;
    use BlameableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 20, enumType: OperationType::class)]
    private OperationType $type = OperationType::OPERATION;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'datetime')]
    private DateTime $startDateTime;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $endDateTime = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $location = null;

    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(name: 'unit_id', onDelete: 'SET NULL')]
    private ?Unit $unit = null;

    #[ORM\Column(length: 20, enumType: OperationStatus::class)]
    private OperationStatus $status = OperationStatus::SCHEDULED;

    /** @var Collection<int, OperationRSVP> */
    #[ORM\OneToMany(mappedBy: 'operation', targetEntity: OperationRSVP::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $rsvps;

    /** @var Collection<int, OperationAAR> */
    #[ORM\OneToMany(mappedBy: 'operation', targetEntity: OperationAAR::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $aars;

    public function __construct()
    {
        $this->startDateTime = new DateTime();
        $this->rsvps = new ArrayCollection();
        $this->aars = new ArrayCollection();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getType(): OperationType
    {
        return $this->type;
    }

    public function setType(OperationType $type): void
    {
        $this->type = $type;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getStartDateTime(): DateTime
    {
        return $this->startDateTime;
    }

    public function setStartDateTime(DateTime $startDateTime): void
    {
        $this->startDateTime = $startDateTime;
    }

    public function getEndDateTime(): ?DateTime
    {
        return $this->endDateTime;
    }

    public function setEndDateTime(?DateTime $endDateTime): void
    {
        $this->endDateTime = $endDateTime;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(?Unit $unit): void
    {
        $this->unit = $unit;
    }

    public function getStatus(): OperationStatus
    {
        return $this->status;
    }

    public function setStatus(OperationStatus $status): void
    {
        $this->status = $status;
    }

    /**
     * @return Collection<int, OperationRSVP>
     */
    public function getRsvps(): Collection
    {
        return $this->rsvps;
    }

    /**
     * Finds this operation's existing RSVP for a soldier, if one has been recorded yet.
     */
    public function getRsvpFor(SoldierProfile $soldier): ?OperationRSVP
    {
        foreach ($this->rsvps as $rsvp) {
            if ($rsvp->getSoldier() === $soldier) {
                return $rsvp;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, OperationAAR>
     */
    public function getAars(): Collection
    {
        return $this->aars;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
