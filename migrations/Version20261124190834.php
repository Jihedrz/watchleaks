<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124190834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sites_scores_audit table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sites_scores_audit (id INT AUTO_INCREMENT NOT NULL, targets LONGTEXT NOT NULL, prompt LONGTEXT NOT NULL, score DOUBLE PRECISION DEFAULT NULL, grade VARCHAR(4) DEFAULT NULL, report_json LONGTEXT DEFAULT NULL, error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, owner_id INT DEFAULT NULL, INDEX IDX_1C8EE4247E3C61F9 (owner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE sites_scores_audit ADD CONSTRAINT FK_1C8EE4247E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sites_scores_audit DROP FOREIGN KEY FK_1C8EE4247E3C61F9');
        $this->addSql('DROP TABLE sites_scores_audit');
    }
}
