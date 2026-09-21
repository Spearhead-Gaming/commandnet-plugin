<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920270000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the Arma classname to equipment and the ORBAT size and type to units.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment ADD classname VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE unit ADD orbat_size VARCHAR(20) DEFAULT NULL, ADD orbat_type VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment DROP classname');
        $this->addSql('ALTER TABLE unit DROP orbat_size, DROP orbat_type');
    }
}
