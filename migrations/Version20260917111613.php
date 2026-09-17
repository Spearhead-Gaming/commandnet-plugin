<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917111613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optional forumify Role link to units, for Discord role sync.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit ADD role_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE unit ADD CONSTRAINT FK_DCBB0C53D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_DCBB0C53D60322AC ON unit (role_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unit DROP FOREIGN KEY FK_DCBB0C53D60322AC');
        $this->addSql('DROP INDEX IDX_DCBB0C53D60322AC ON unit');
        $this->addSql('ALTER TABLE unit DROP role_id');
    }
}
