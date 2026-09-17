<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917122228 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Turn Operation.calendarEventId into a real FK and add Operation.calendar, matching MILHQ Mission\'s calendar relation shape.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation ADD calendar_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66DA40A2C8 FOREIGN KEY (calendar_id) REFERENCES calendar (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66D7495C8E3 FOREIGN KEY (calendar_event_id) REFERENCES calendar_event (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1981A66D7495C8E3 ON operation (calendar_event_id)');
        $this->addSql('CREATE INDEX IDX_1981A66DA40A2C8 ON operation (calendar_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation DROP FOREIGN KEY FK_1981A66DA40A2C8');
        $this->addSql('ALTER TABLE operation DROP FOREIGN KEY FK_1981A66D7495C8E3');
        $this->addSql('DROP INDEX UNIQ_1981A66D7495C8E3 ON operation');
        $this->addSql('DROP INDEX IDX_1981A66DA40A2C8 ON operation');
        $this->addSql('ALTER TABLE operation DROP calendar_id');
    }
}
