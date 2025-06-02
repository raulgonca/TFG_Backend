<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250528074109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE project_file (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, project_id INT NOT NULL, file_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, fecha_subida DATETIME NOT NULL, INDEX IDX_B50EFE08A76ED395 (user_id), INDEX IDX_B50EFE08166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_file ADD CONSTRAINT FK_B50EFE08A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_file ADD CONSTRAINT FK_B50EFE08166D1F9C FOREIGN KEY (project_id) REFERENCES repo (id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_8d93d649e7927c74 ON user
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX email_unique ON user (email)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE project_file DROP FOREIGN KEY FK_B50EFE08A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_file DROP FOREIGN KEY FK_B50EFE08166D1F9C
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE project_file
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX email_unique ON user
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)
        SQL);
    }
}
