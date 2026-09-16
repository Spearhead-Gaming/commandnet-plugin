<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the Report In log backing the previously-unused lastReportIn cache column.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE report_in (id INT AUTO_INCREMENT NOT NULL, soldier_id INT NOT NULL, reported_at DATETIME NOT NULL, INDEX IDX_AB074DDEA38C1700 (soldier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE report_in ADD CONSTRAINT FK_AB074DDEA38C1700 FOREIGN KEY (soldier_id) REFERENCES soldier_profile (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report_in DROP FOREIGN KEY FK_AB074DDEA38C1700');
        $this->addSql('DROP TABLE report_in');
    }
}