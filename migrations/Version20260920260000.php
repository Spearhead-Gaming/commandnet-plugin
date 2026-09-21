<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920260000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the auto-flagged mark only on soldiers whose latest AWOL entry is a flag, not a return to Active.';
    }

    /**
     * The earlier backfill marked a soldier who is AWOL now as auto-flagged if they ever had a
     * "Flagged AWOL" entry. A soldier who was flagged, returned to Active, and later set AWOL by
     * an admin has an old flag but is not auto-flagged, and would be returned to Active the next
     * time they attend. Their latest entry is the return, so that is what is checked here.
     */
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE soldier_profile SET awol_auto_flagged = 0
            WHERE awol_auto_flagged = 1
              AND status = 'awol'
              AND COALESCE((
                  SELECT sr.title FROM service_record sr
                  WHERE sr.soldier_id = soldier_profile.id
                    AND sr.type = 'awol'
                    AND sr.title IN ('Flagged AWOL', 'Returned to Active')
                  ORDER BY sr.id DESC
                  LIMIT 1
              ), '') <> 'Flagged AWOL'
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Nothing to restore: the flag this cleared was wrong for those soldiers.
    }
}
