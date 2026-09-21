<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add equipment, the weapons a position allows and the vehicles a unit has.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE equipment (name VARCHAR(150) NOT NULL, type VARCHAR(20) NOT NULL, id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE position_primary_weapon (position_id INT NOT NULL, equipment_id INT NOT NULL, INDEX IDX_D679D98BDD842E46 (position_id), INDEX IDX_D679D98B517FE9FE (equipment_id), PRIMARY KEY (position_id, equipment_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE position_primary_weapon ADD CONSTRAINT FK_D679D98BDD842E46 FOREIGN KEY (position_id) REFERENCES position (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE position_primary_weapon ADD CONSTRAINT FK_D679D98B517FE9FE FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE position_secondary_weapon (position_id INT NOT NULL, equipment_id INT NOT NULL, INDEX IDX_8B6F4234DD842E46 (position_id), INDEX IDX_8B6F4234517FE9FE (equipment_id), PRIMARY KEY (position_id, equipment_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE position_secondary_weapon ADD CONSTRAINT FK_8B6F4234DD842E46 FOREIGN KEY (position_id) REFERENCES position (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE position_secondary_weapon ADD CONSTRAINT FK_8B6F4234517FE9FE FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE unit_vehicle (unit_id INT NOT NULL, equipment_id INT NOT NULL, INDEX IDX_4FC9EA44F8BD700D (unit_id), INDEX IDX_4FC9EA44517FE9FE (equipment_id), PRIMARY KEY (unit_id, equipment_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE unit_vehicle ADD CONSTRAINT FK_4FC9EA44F8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE unit_vehicle ADD CONSTRAINT FK_4FC9EA44517FE9FE FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE unit_vehicle');
        $this->addSql('DROP TABLE position_secondary_weapon');
        $this->addSql('DROP TABLE position_primary_weapon');
        $this->addSql('DROP TABLE equipment');
    }
}
