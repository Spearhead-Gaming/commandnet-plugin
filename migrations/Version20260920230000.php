<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add forms and form submissions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE form_definition (name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, field_list LONGTEXT NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_61F7634C8B8E8428 (created_at), INDEX IDX_61F7634C43625D9F (updated_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE form_submission (form_name VARCHAR(150) NOT NULL, answers JSON NOT NULL, status VARCHAR(20) NOT NULL, reviewed_at DATETIME DEFAULT NULL, decision_note LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, form_id INT DEFAULT NULL, user_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, INDEX IDX_D2C216675FF69B7D (form_id), INDEX IDX_D2C21667A76ED395 (user_id), INDEX IDX_D2C21667FC6B21F1 (reviewed_by_id), INDEX IDX_D2C216678B8E8428 (created_at), INDEX IDX_D2C2166743625D9F (updated_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE form_submission ADD CONSTRAINT FK_D2C216675FF69B7D FOREIGN KEY (form_id) REFERENCES form_definition (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE form_submission ADD CONSTRAINT FK_D2C21667A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE form_submission ADD CONSTRAINT FK_D2C21667FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE form_submission DROP FOREIGN KEY FK_D2C216675FF69B7D');
        $this->addSql('ALTER TABLE form_submission DROP FOREIGN KEY FK_D2C21667A76ED395');
        $this->addSql('ALTER TABLE form_submission DROP FOREIGN KEY FK_D2C21667FC6B21F1');
        $this->addSql('DROP TABLE form_submission');
        $this->addSql('DROP TABLE form_definition');
    }
}
