<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260915041005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE assignment (is_primary TINYINT DEFAULT 1 NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, soldier_id INT NOT NULL, unit_id INT NOT NULL, position_id INT DEFAULT NULL, INDEX IDX_30C544BAA38C1700 (soldier_id), INDEX IDX_30C544BAF8BD700D (unit_id), INDEX IDX_30C544BADD842E46 (position_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE award (name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, INDEX IDX_8A5B2EE7462CE4F5 (position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE operation (title VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, content LONGTEXT DEFAULT NULL, start_date_time DATETIME NOT NULL, end_date_time DATETIME DEFAULT NULL, location VARCHAR(150) DEFAULT NULL, status VARCHAR(20) NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, unit_id INT DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, INDEX IDX_1981A66D8B8E8428 (created_at), INDEX IDX_1981A66D43625D9F (updated_at), INDEX IDX_1981A66DF8BD700D (unit_id), INDEX IDX_1981A66DDE12AB56 (created_by), INDEX IDX_1981A66D16FE72E1 (updated_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE operation_aar (summary LONGTEXT NOT NULL, objectives_met TINYINT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, operation_id INT NOT NULL, submitted_by_id INT NOT NULL, INDEX IDX_6B9824CF8B8E8428 (created_at), INDEX IDX_6B9824CF43625D9F (updated_at), INDEX IDX_6B9824CF44AC3583 (operation_id), INDEX IDX_6B9824CF79F7D87D (submitted_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE operation_rsvp (status VARCHAR(20) NOT NULL, responded_at DATETIME DEFAULT NULL, attended TINYINT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, operation_id INT NOT NULL, soldier_id INT NOT NULL, UNIQUE INDEX operation_soldier_unique (operation_id, soldier_id), INDEX IDX_B0D593DF44AC3583 (operation_id), INDEX IDX_B0D593DFA38C1700 (soldier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE position (title VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE qualification (name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, INDEX IDX_B712F0CE462CE4F5 (position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `rank` (name VARCHAR(100) NOT NULL, abbreviation VARCHAR(20) NOT NULL, pay_grade VARCHAR(20) DEFAULT NULL, insignia VARCHAR(255) DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, position INT NOT NULL, INDEX IDX_8879E8E58B8E8428 (created_at), INDEX IDX_8879E8E543625D9F (updated_at), INDEX IDX_8879E8E5462CE4F5 (position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service_record (type VARCHAR(20) NOT NULL, date DATE NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, soldier_id INT NOT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, INDEX IDX_A5F39AA7A38C1700 (soldier_id), INDEX IDX_A5F39AA7DE12AB56 (created_by), INDEX IDX_A5F39AA716FE72E1 (updated_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE soldier_award (date_awarded DATE NOT NULL, citation LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, soldier_id INT NOT NULL, award_id INT NOT NULL, awarded_by_id INT DEFAULT NULL, INDEX IDX_72D33EBEA38C1700 (soldier_id), INDEX IDX_72D33EBE3D5282CF (award_id), INDEX IDX_72D33EBE98454AC5 (awarded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE soldier_profile (service_number VARCHAR(30) DEFAULT NULL, callsign VARCHAR(50) DEFAULT NULL, status VARCHAR(20) NOT NULL, enlistment_date DATE DEFAULT NULL, discharge_date DATE DEFAULT NULL, bio LONGTEXT DEFAULT NULL, last_report_in DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, rank_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F799A4FEA8571A62 (service_number), INDEX IDX_F799A4FE8B8E8428 (created_at), INDEX IDX_F799A4FE43625D9F (updated_at), UNIQUE INDEX UNIQ_F799A4FEA76ED395 (user_id), INDEX IDX_F799A4FE7616678F (rank_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE soldier_qualification (date_earned DATE NOT NULL, id INT AUTO_INCREMENT NOT NULL, soldier_id INT NOT NULL, qualification_id INT NOT NULL, issued_by_id INT DEFAULT NULL, INDEX IDX_100209C4A38C1700 (soldier_id), INDEX IDX_100209C41A75EE38 (qualification_id), INDEX IDX_100209C4784BB717 (issued_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE unit (name VARCHAR(150) NOT NULL, abbreviation VARCHAR(30) NOT NULL, description LONGTEXT DEFAULT NULL, insignia VARCHAR(255) DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, position INT NOT NULL, parent_id INT DEFAULT NULL, commander_id INT DEFAULT NULL, INDEX IDX_DCBB0C538B8E8428 (created_at), INDEX IDX_DCBB0C5343625D9F (updated_at), INDEX IDX_DCBB0C53462CE4F5 (position), INDEX IDX_DCBB0C53727ACA70 (parent_id), INDEX IDX_DCBB0C533349A583 (commander_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BAA38C1700 FOREIGN KEY (soldier_id) REFERENCES soldier_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BAF8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BADD842E46 FOREIGN KEY (position_id) REFERENCES position (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66DF8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66DDE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66D16FE72E1 FOREIGN KEY (updated_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE operation_aar ADD CONSTRAINT FK_6B9824CF44AC3583 FOREIGN KEY (operation_id) REFERENCES operation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE operation_aar ADD CONSTRAINT FK_6B9824CF79F7D87D FOREIGN KEY (submitted_by_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE operation_rsvp ADD CONSTRAINT FK_B0D593DF44AC3583 FOREIGN KEY (operation_id) REFERENCES operation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE operation_rsvp ADD CONSTRAINT FK_B0D593DFA38C1700 FOREIGN KEY (soldier_id) REFERENCES soldier_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_record ADD CONSTRAINT FK_A5F39AA7A38C1700 FOREIGN KEY (soldier_id) REFERENCES soldier_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_record ADD CONSTRAINT FK_A5F39AA7DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE service_record ADD CONSTRAINT FK_A5F39AA716FE72E1 FOREIGN KEY (updated_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE soldier_award ADD CONSTRAINT FK_72D33EBEA38C1700 FOREIGN KEY (soldier_id) REFERENCES soldier_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE soldier_award ADD CONSTRAINT FK_72D33EBE3D5282CF FOREIGN KEY (award_id) REFERENCES award (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE soldier_award ADD CONSTRAINT FK_72D33EBE98454AC5 FOREIGN KEY (awarded_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE soldier_profile ADD CONSTRAINT FK_F799A4FEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE soldier_profile ADD CONSTRAINT FK_F799A4FE7616678F FOREIGN KEY (rank_id) REFERENCES `rank` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE soldier_qualification ADD CONSTRAINT FK_100209C4A38C1700 FOREIGN KEY (soldier_id) REFERENCES soldier_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE soldier_qualification ADD CONSTRAINT FK_100209C41A75EE38 FOREIGN KEY (qualification_id) REFERENCES qualification (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE soldier_qualification ADD CONSTRAINT FK_100209C4784BB717 FOREIGN KEY (issued_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE unit ADD CONSTRAINT FK_DCBB0C53727ACA70 FOREIGN KEY (parent_id) REFERENCES unit (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE unit ADD CONSTRAINT FK_DCBB0C533349A583 FOREIGN KEY (commander_id) REFERENCES soldier_profile (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BAA38C1700');
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BAF8BD700D');
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BADD842E46');
        $this->addSql('ALTER TABLE operation DROP FOREIGN KEY FK_1981A66DF8BD700D');
        $this->addSql('ALTER TABLE operation DROP FOREIGN KEY FK_1981A66DDE12AB56');
        $this->addSql('ALTER TABLE operation DROP FOREIGN KEY FK_1981A66D16FE72E1');
        $this->addSql('ALTER TABLE operation_aar DROP FOREIGN KEY FK_6B9824CF44AC3583');
        $this->addSql('ALTER TABLE operation_aar DROP FOREIGN KEY FK_6B9824CF79F7D87D');
        $this->addSql('ALTER TABLE operation_rsvp DROP FOREIGN KEY FK_B0D593DF44AC3583');
        $this->addSql('ALTER TABLE operation_rsvp DROP FOREIGN KEY FK_B0D593DFA38C1700');
        $this->addSql('ALTER TABLE service_record DROP FOREIGN KEY FK_A5F39AA7A38C1700');
        $this->addSql('ALTER TABLE service_record DROP FOREIGN KEY FK_A5F39AA7DE12AB56');
        $this->addSql('ALTER TABLE service_record DROP FOREIGN KEY FK_A5F39AA716FE72E1');
        $this->addSql('ALTER TABLE soldier_award DROP FOREIGN KEY FK_72D33EBEA38C1700');
        $this->addSql('ALTER TABLE soldier_award DROP FOREIGN KEY FK_72D33EBE3D5282CF');
        $this->addSql('ALTER TABLE soldier_award DROP FOREIGN KEY FK_72D33EBE98454AC5');
        $this->addSql('ALTER TABLE soldier_profile DROP FOREIGN KEY FK_F799A4FEA76ED395');
        $this->addSql('ALTER TABLE soldier_profile DROP FOREIGN KEY FK_F799A4FE7616678F');
        $this->addSql('ALTER TABLE soldier_qualification DROP FOREIGN KEY FK_100209C4A38C1700');
        $this->addSql('ALTER TABLE soldier_qualification DROP FOREIGN KEY FK_100209C41A75EE38');
        $this->addSql('ALTER TABLE soldier_qualification DROP FOREIGN KEY FK_100209C4784BB717');
        $this->addSql('ALTER TABLE unit DROP FOREIGN KEY FK_DCBB0C53727ACA70');
        $this->addSql('ALTER TABLE unit DROP FOREIGN KEY FK_DCBB0C533349A583');
        $this->addSql('DROP TABLE assignment');
        $this->addSql('DROP TABLE award');
        $this->addSql('DROP TABLE operation');
        $this->addSql('DROP TABLE operation_aar');
        $this->addSql('DROP TABLE operation_rsvp');
        $this->addSql('DROP TABLE position');
        $this->addSql('DROP TABLE qualification');
        $this->addSql('DROP TABLE `rank`');
        $this->addSql('DROP TABLE service_record');
        $this->addSql('DROP TABLE soldier_award');
        $this->addSql('DROP TABLE soldier_profile');
        $this->addSql('DROP TABLE soldier_qualification');
        $this->addSql('DROP TABLE unit');
    }
}
