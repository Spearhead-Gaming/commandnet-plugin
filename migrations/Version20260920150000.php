<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track whether AWOL came from failing to report in, so reporting in restores Active.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE soldier_profile ADD report_in_flagged TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE soldier_profile DROP report_in_flagged');
    }
}
