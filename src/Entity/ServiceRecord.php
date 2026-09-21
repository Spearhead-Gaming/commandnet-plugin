<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\BlameableEntityTrait;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;

/**
 * A single entry on a soldier's timeline. Most of these are created automatically —
 * a promotion writes one when a rank change is saved, an award write one when it's
 * issued, an AAR can write combat records for attendees — but leaders can also add
 * manual notes or disciplinary entries directly.
 *
 * Centralizing this (rather than making each module render its own history list) is
 * what gives the personnel file a single combined timeline instead of five separate tabs.
 */
#[ORM\Entity(ServiceRecordRepository::class)]
#[ORM\Index(columns: ['source_type', 'source_id'], name: 'idx_service_record_source')]
class ServiceRecord
{
    use IdentifiableEntityTrait;
    use BlameableEntityTrait;

    /**
     * Identifies the award/qualification/assignment/AAR that generated this entry, so
     * deleting that record can find and remove this one too. An AAR can generate several
     * of these at once (one combat record per attendee), all sharing the same source id.
     * Records created directly (a manual note, a promotion) leave both null - there's
     * nothing to clean up if those are ever deleted individually.
     */
    public const SOURCE_AWARD = 'soldier_award';
    public const SOURCE_QUALIFICATION = 'soldier_qualification';
    public const SOURCE_ASSIGNMENT = 'assignment';
    public const SOURCE_OPERATION_AAR = 'operation_aar';
    /** Combat records for an operation, one per attendee no matter how many AARs exist; sourceId is the operation. */
    public const SOURCE_OPERATION = 'operation';

    #[ORM\ManyToOne(targetEntity: SoldierProfile::class, inversedBy: 'serviceRecords')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SoldierProfile $soldier;

    #[ORM\Column(length: 20, enumType: ServiceRecordType::class)]
    private ServiceRecordType $type;

    #[ORM\Column(type: 'date')]
    private DateTime $date;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $sourceType = null;

    #[ORM\Column(nullable: true)]
    private ?int $sourceId = null;

    public function __construct(SoldierProfile $soldier, ServiceRecordType $type, string $title)
    {
        $this->soldier = $soldier;
        $this->type = $type;
        $this->title = $title;
        $this->date = new DateTime();
    }

    public function getSoldier(): SoldierProfile
    {
        return $this->soldier;
    }

    public function getType(): ServiceRecordType
    {
        return $this->type;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function setDate(DateTime $date): void
    {
        $this->date = $date;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function setSource(string $sourceType, int $sourceId): void
    {
        $this->sourceType = $sourceType;
        $this->sourceId = $sourceId;
    }
}
