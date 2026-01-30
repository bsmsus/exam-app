<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260129000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admins, students, refresh_tokens tables and update attempts with student_id';
    }

    public function up(Schema $schema): void
    {
        // Create admins table
        $this->addSql('CREATE TABLE admins (
            id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A2E0150FE7927C74 ON admins (email)');

        // Create students table
        $this->addSql('CREATE TABLE students (
            id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A4698DB2E7927C74 ON students (email)');

        // Create refresh_tokens table
        $this->addSql('CREATE TABLE refresh_tokens (
            id UUID NOT NULL,
            token VARCHAR(255) NOT NULL,
            user_id UUID NOT NULL,
            user_type VARCHAR(20) NOT NULL,
            expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9BACE7E15F37A13B ON refresh_tokens (token)');
        $this->addSql('CREATE INDEX idx_refresh_token_user ON refresh_tokens (user_id, user_type)');

        // Drop old constraint on attempts
        $this->addSql('ALTER TABLE attempts DROP CONSTRAINT IF EXISTS uniq_exam_attempt');

        // Add student_id column to attempts
        $this->addSql('ALTER TABLE attempts ADD student_id UUID NULL');

        // Delete existing attempts (they have no student association)
        $this->addSql('DELETE FROM attempts');

        // Make student_id NOT NULL after clearing data
        $this->addSql('ALTER TABLE attempts ALTER COLUMN student_id SET NOT NULL');

        // Add foreign key
        $this->addSql('ALTER TABLE attempts ADD CONSTRAINT FK_27F938C6CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Add new unique constraint
        $this->addSql('CREATE UNIQUE INDEX uniq_exam_student_attempt ON attempts (exam_id, student_id, attempt_number)');

        // Add index on student_id
        $this->addSql('CREATE INDEX idx_attempt_student ON attempts (student_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove new constraints and column from attempts
        $this->addSql('DROP INDEX IF EXISTS uniq_exam_student_attempt');
        $this->addSql('DROP INDEX IF EXISTS idx_attempt_student');
        $this->addSql('ALTER TABLE attempts DROP CONSTRAINT IF EXISTS FK_27F938C6CB944F1A');
        $this->addSql('ALTER TABLE attempts DROP COLUMN student_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_exam_attempt ON attempts (exam_id, attempt_number)');

        // Drop new tables
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE students');
        $this->addSql('DROP TABLE admins');
    }
}
