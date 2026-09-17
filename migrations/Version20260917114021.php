<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917114021 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a calendar_event_id back-reference for the optional calendar plugin sync.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation ADD calendar_event_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation DROP calendar_event_id');
    }
}
