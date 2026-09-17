<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917051134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a uniform image column to soldier profiles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE soldier_profile ADD uniform_image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE soldier_profile DROP uniform_image');
    }
}
