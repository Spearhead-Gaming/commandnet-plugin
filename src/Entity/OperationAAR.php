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

    // The fields below follow the community's AAR template (the summary above is its "Report").
    // They are nullable because reports filed before the template existed, and non-patrol
    // reports, do not have them.

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tasking = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $callsigns = null;

    /** FKIA / FWIA / FMIA, as the reporter wrote it (e.g. "0 / 1 / 0"). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $friendlyCasualties = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $enemyKia = null;

    /**
     * Paths in the asset storage of the map screenshots (required for a patrol's report).
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $mapImages = null;

    /**
     * Paths in the asset storage of the intel images and other media (required for a patrol's report).
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $intelImages = null;

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

    public function getTasking(): ?string
    {
        return $this->tasking;
    }

    public function setTasking(?string $tasking): void
    {
        $this->tasking = $tasking;
    }

    public function getCallsigns(): ?string
    {
        return $this->callsigns;
    }

    public function setCallsigns(?string $callsigns): void
    {
        $this->callsigns = $callsigns;
    }

    public function getFriendlyCasualties(): ?string
    {
        return $this->friendlyCasualties;
    }

    public function setFriendlyCasualties(?string $friendlyCasualties): void
    {
        $this->friendlyCasualties = $friendlyCasualties;
    }

    public function getEnemyKia(): ?string
    {
        return $this->enemyKia;
    }

    public function setEnemyKia(?string $enemyKia): void
    {
        $this->enemyKia = $enemyKia;
    }

    /**
     * @return list<string>
     */
    public function getMapImages(): array
    {
        return $this->mapImages ?? [];
    }

    /**
     * @param list<string> $mapImages
     */
    public function setMapImages(array $mapImages): void
    {
        $this->mapImages = $mapImages;
    }

    /**
     * @return list<string>
     */
    public function getIntelImages(): array
    {
        return $this->intelImages ?? [];
    }

    /**
     * @param list<string> $intelImages
     */
    public function setIntelImages(array $intelImages): void
    {
        $this->intelImages = $intelImages;
    }
}
