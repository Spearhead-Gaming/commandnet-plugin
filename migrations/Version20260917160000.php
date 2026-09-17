<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Unit.discordGuildId so a unit can target its own Discord server for notifications instead of always the community one.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit ADD discord_guild_id VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit DROP discord_guild_id');
    }
}
