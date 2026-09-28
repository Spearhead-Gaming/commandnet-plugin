<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A unit's own set of billets (e.g. a squad's "Squad Leader"/"Team Leader"), so they can be
 * picked and managed directly on that unit instead of only through the standalone Position
 * catalog page. Position stays the shared, reusable title catalog it already was - this is
 * just which of those titles apply to a given unit.
 */
final class Version20260927173710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add unit_position, linking a unit to the positions it's expected to hold.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE unit_position (unit_id INT NOT NULL, position_id INT NOT NULL, INDEX IDX_UNIT_POSITION_UNIT (unit_id), INDEX IDX_UNIT_POSITION_POSITION (position_id), PRIMARY KEY(unit_id, position_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE unit_position ADD CONSTRAINT FK_UNIT_POSITION_UNIT FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE unit_position ADD CONSTRAINT FK_UNIT_POSITION_POSITION FOREIGN KEY (position_id) REFERENCES position (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE unit_position');
    }
}
