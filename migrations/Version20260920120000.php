<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add promotion requirements to Rank: a minimum time in the previous rank and required qualifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `rank` ADD min_time_in_grade_days INT DEFAULT NULL');
        $this->addSql('CREATE TABLE rank_required_qualification (rank_id INT NOT NULL, qualification_id INT NOT NULL, INDEX IDX_6D421A437616678F (rank_id), INDEX IDX_6D421A431A75EE38 (qualification_id), PRIMARY KEY (rank_id, qualification_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE rank_required_qualification ADD CONSTRAINT FK_6D421A437616678F FOREIGN KEY (rank_id) REFERENCES `rank` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rank_required_qualification ADD CONSTRAINT FK_6D421A431A75EE38 FOREIGN KEY (qualification_id) REFERENCES qualification (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rank_required_qualification DROP FOREIGN KEY FK_6D421A437616678F');
        $this->addSql('ALTER TABLE rank_required_qualification DROP FOREIGN KEY FK_6D421A431A75EE38');
        $this->addSql('DROP TABLE rank_required_qualification');
        $this->addSql('ALTER TABLE `rank` DROP min_time_in_grade_days');
    }
}
