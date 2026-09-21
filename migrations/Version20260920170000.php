<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SoldierProfile.activeSince so absences while on leave do not count toward the AWOL streak.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE soldier_profile ADD active_since DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE soldier_profile DROP active_since');
    }
}
