<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use MajesticDev\CommandNet\Entity\Document;
use MajesticDev\CommandNet\Entity\ServiceRecord;

/**
 * Fills a document with values from one soldier and one of their service records. Values are
 * HTML-escaped, since names and record text are typed by members, and replacement is a single
 * pass so a value that happens to contain a placeholder is never expanded again. A placeholder
 * that is not recognised is left as written so a typo is easy to spot.
 */
class DocumentRenderer
{
    public function __construct(private readonly RankSettings $rankSettings)
    {
    }

    /**
     * Placeholder name => what it stands for, listed in the document editor.
     */
    public const array PLACEHOLDERS = [
        'user_name' => 'The soldier display name',
        'user_rank' => 'Their rank name',
        'user_rank_abbreviation' => 'Their rank abbreviation',
        'user_callsign' => 'Their callsign',
        'user_service_number' => 'Their service number',
        'user_unit' => 'The unit of their primary assignment',
        'user_position' => 'The position of their primary assignment',
        'user_specialty' => 'Their specialty',
        'user_status' => 'Their status',
        'record_type' => 'The type of the record, such as Award',
        'record_title' => 'The record title, such as the award name',
        'record_description' => 'The record description, such as the citation',
        'record_date' => 'The record date',
    ];

    public function render(Document $document, ServiceRecord $record): string
    {
        $values = $this->values($record);

        return (string)preg_replace_callback(
            '/\{([a-z_]+)\}/',
            static fn (array $match): string => isset($values[$match[1]]) ? htmlspecialchars($values[$match[1]]) : $match[0],
            $document->getContent(),
        );
    }

    /**
     * @return array<string, string>
     */
    private function values(ServiceRecord $record): array
    {
        $soldier = $record->getSoldier();
        $assignment = $soldier->getPrimaryAssignment();

        $rank = $this->rankSettings->isEnabled() ? $soldier->getRank() : null;

        return [
            'user_name' => $soldier->getUser()->getDisplayName(),
            'user_rank' => (string)$rank?->getName(),
            'user_rank_abbreviation' => (string)$rank?->getAbbreviation(),
            'user_callsign' => (string)$soldier->getCallsign(),
            'user_service_number' => (string)$soldier->getServiceNumber(),
            'user_unit' => (string)$assignment?->getUnit()->getName(),
            'user_position' => (string)$assignment?->getPosition()?->getTitle(),
            'user_specialty' => (string)$soldier->getSpecialty()?->getName(),
            'user_status' => $soldier->getStatus()->label(),
            'record_type' => $record->getType()->label(),
            'record_title' => $record->getTitle(),
            'record_description' => (string)$record->getDescription(),
            'record_date' => $record->getDate()->format('Y-m-d'),
        ];
    }
}
