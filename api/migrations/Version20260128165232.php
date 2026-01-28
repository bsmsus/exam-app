<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260128165232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attempts (id UUID NOT NULL, attempt_number INT NOT NULL, status VARCHAR(255) NOT NULL, started_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, ended_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, exam_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BFC7E764578D5E91 ON attempts (exam_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_exam_attempt ON attempts (exam_id, attempt_number)');
        $this->addSql('CREATE TABLE exams (id UUID NOT NULL, title VARCHAR(255) NOT NULL, max_attempts INT NOT NULL, cooldown_minutes INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE attempts ADD CONSTRAINT FK_BFC7E764578D5E91 FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attempts DROP CONSTRAINT FK_BFC7E764578D5E91');
        $this->addSql('DROP TABLE attempts');
        $this->addSql('DROP TABLE exams');
    }
}
