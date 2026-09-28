<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Squads and teams stop being tracked as Unit rows (they carry no commander/insignia/Discord
 * role/vehicles/ORBAT export of their own) and get their own lightweight, self-referencing
 * table instead - a squad belongs directly to a Unit, a team belongs to a squad.
 */
final class Version20260927220246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the squad table (squads/teams within a Unit) and squad_position.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE squad (id INT AUTO_INCREMENT NOT NULL, unit_id INT NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(150) NOT NULL, INDEX IDX_SQUAD_UNIT (unit_id), INDEX IDX_SQUAD_PARENT (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE squad ADD CONSTRAINT FK_SQUAD_UNIT FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE squad ADD CONSTRAINT FK_SQUAD_PARENT FOREIGN KEY (parent_id) REFERENCES squad (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE squad_position (squad_id INT NOT NULL, position_id INT NOT NULL, INDEX IDX_SQUAD_POSITION_SQUAD (squad_id), INDEX IDX_SQUAD_POSITION_POSITION (position_id), PRIMARY KEY(squad_id, position_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE squad_position ADD CONSTRAINT FK_SQUAD_POSITION_SQUAD FOREIGN KEY (squad_id) REFERENCES squad (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE squad_position ADD CONSTRAINT FK_SQUAD_POSITION_POSITION FOREIGN KEY (position_id) REFERENCES position (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE assignment ADD squad_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_ASSIGNMENT_SQUAD FOREIGN KEY (squad_id) REFERENCES squad (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_ASSIGNMENT_SQUAD ON assignment (squad_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_ASSIGNMENT_SQUAD');
        $this->addSql('DROP INDEX IDX_ASSIGNMENT_SQUAD ON assignment');
        $this->addSql('ALTER TABLE assignment DROP squad_id');
        $this->addSql('DROP TABLE squad_position');
        $this->addSql('DROP TABLE squad');
    }
}
