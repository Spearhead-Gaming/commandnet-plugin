<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track whether AWOL was set by attendance detection, and file its past audit records under their own type.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE soldier_profile ADD awol_auto_flagged TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE soldier_profile SET awol_auto_flagged = 1 WHERE status = 'awol' AND EXISTS (SELECT 1 FROM service_record sr WHERE sr.soldier_id = soldier_profile.id AND sr.type = 'assignment' AND sr.title = 'Flagged AWOL')");
        $this->addSql("UPDATE service_record SET type = 'awol' WHERE type = 'assignment' AND title IN ('Flagged AWOL', 'Returned to Active')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE service_record SET type = 'assignment' WHERE type = 'awol'");
        $this->addSql('ALTER TABLE soldier_profile DROP awol_auto_flagged');
    }
}
