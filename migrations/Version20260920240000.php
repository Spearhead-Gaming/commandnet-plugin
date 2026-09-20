<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add courses, their prerequisites and granted qualifications, classes and enrolled students.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE course (name VARCHAR(150) NOT NULL, description LONGTEXT NOT NULL, id INT AUTO_INCREMENT NOT NULL, minimum_rank_id INT DEFAULT NULL, INDEX IDX_169E6FB94BF66E9D (minimum_rank_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB94BF66E9D FOREIGN KEY (minimum_rank_id) REFERENCES `rank` (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE course_prerequisite (course_id INT NOT NULL, prerequisite_id INT NOT NULL, INDEX IDX_C45EDAC5591CC992 (course_id), INDEX IDX_C45EDAC5276AF86B (prerequisite_id), PRIMARY KEY (course_id, prerequisite_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE course_prerequisite ADD CONSTRAINT FK_C45EDAC5591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE course_prerequisite ADD CONSTRAINT FK_C45EDAC5276AF86B FOREIGN KEY (prerequisite_id) REFERENCES course (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE course_qualification (course_id INT NOT NULL, qualification_id INT NOT NULL, INDEX IDX_749FB417591CC992 (course_id), INDEX IDX_749FB4171A75EE38 (qualification_id), PRIMARY KEY (course_id, qualification_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE course_qualification ADD CONSTRAINT FK_749FB417591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE course_qualification ADD CONSTRAINT FK_749FB4171A75EE38 FOREIGN KEY (qualification_id) REFERENCES qualification (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE course_class (starts_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, student_slots INT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, processed TINYINT(1) DEFAULT 0 NOT NULL, id INT AUTO_INCREMENT NOT NULL, course_id INT NOT NULL, instructor_id INT DEFAULT NULL, INDEX IDX_4E01E77591CC992 (course_id), INDEX IDX_4E01E778C4FC193 (instructor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE course_class ADD CONSTRAINT FK_4E01E77591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE course_class ADD CONSTRAINT FK_4E01E778C4FC193 FOREIGN KEY (instructor_id) REFERENCES soldier_profile (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE course_class_student (result VARCHAR(20) DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, course_class_id INT NOT NULL, soldier_id INT NOT NULL, INDEX IDX_B1DB8C1643B46646 (course_class_id), INDEX IDX_B1DB8C16A38C1700 (soldier_id), UNIQUE INDEX uniq_course_class_student (course_class_id, soldier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE course_class_student ADD CONSTRAINT FK_B1DB8C1643B46646 FOREIGN KEY (course_class_id) REFERENCES course_class (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE course_class_student ADD CONSTRAINT FK_B1DB8C16A38C1700 FOREIGN KEY (soldier_id) REFERENCES soldier_profile (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE course_class_student');
        $this->addSql('DROP TABLE course_class');
        $this->addSql('DROP TABLE course_qualification');
        $this->addSql('DROP TABLE course_prerequisite');
        $this->addSql('DROP TABLE course');
    }
}
