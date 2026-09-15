<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Repository\OperationAARRepository;

/**
 * An after-action report. Kept as many-per-operation (rather than one) since larger
 * ops often get separate reports from each element lead rather than a single summary.
 */
#[ORM\Entity(OperationAARRepository::class)]
class OperationAAR
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\ManyToOne(targetEntity: Operation::class, inversedBy: 'aars')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Operation $operation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $submittedBy;

    #[ORM\Column(type: 'text')]
    private string $summary = '';

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $objectivesMet = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct(Operation $operation, User $submittedBy)
    {
        $this->operation = $operation;
        $this->submittedBy = $submittedBy;
    }

    public function getOperation(): Operation
    {
        return $this->operation;
    }

    public function getSubmittedBy(): User
    {
        return $this->submittedBy;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): void
    {
        $this->summary = $summary;
    }

    public function getObjectivesMet(): ?bool
    {
        return $this->objectivesMet;
    }

    public function setObjectivesMet(?bool $objectivesMet): void
    {
        $this->objectivesMet = $objectivesMet;
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
