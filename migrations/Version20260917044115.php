<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917044115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add qualification tier grouping and unit-specific qualification restriction.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE qualification_unit (qualification_id INT NOT NULL, unit_id INT NOT NULL, INDEX IDX_E5FB73341A75EE38 (qualification_id), INDEX IDX_E5FB7334F8BD700D (unit_id), PRIMARY KEY (qualification_id, unit_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE qualification_unit ADD CONSTRAINT FK_E5FB73341A75EE38 FOREIGN KEY (qualification_id) REFERENCES qualification (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE qualification_unit ADD CONSTRAINT FK_E5FB7334F8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE qualification ADD tier VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE qualification_unit DROP FOREIGN KEY FK_E5FB73341A75EE38');
        $this->addSql('ALTER TABLE qualification_unit DROP FOREIGN KEY FK_E5FB7334F8BD700D');
        $this->addSql('DROP TABLE qualification_unit');
        $this->addSql('ALTER TABLE qualification DROP tier');
    }
}
