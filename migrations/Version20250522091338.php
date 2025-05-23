<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250522091338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, cif VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(255) NOT NULL, web VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE repo (id INT AUTO_INCREMENT NOT NULL, owner_id INT DEFAULT NULL, client_id INT DEFAULT NULL, projectname VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, fecha_inicio DATE NOT NULL, fecha_fin DATE DEFAULT NULL, file VARCHAR(255) DEFAULT NULL, file_name VARCHAR(255) NOT NULL, INDEX IDX_5C5CBBFF7E3C61F9 (owner_id), INDEX IDX_5C5CBBFF19EB6921 (client_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE repo_colaboradores (repo_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_53B2DC1ABD359B2D (repo_id), INDEX IDX_53B2DC1AA76ED395 (user_id), PRIMARY KEY(repo_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL COMMENT '(DC2Type:json)', UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE repo ADD CONSTRAINT FK_5C5CBBFF7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE repo ADD CONSTRAINT FK_5C5CBBFF19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE repo_colaboradores ADD CONSTRAINT FK_53B2DC1ABD359B2D FOREIGN KEY (repo_id) REFERENCES repo (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE repo_colaboradores ADD CONSTRAINT FK_53B2DC1AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE repo DROP FOREIGN KEY FK_5C5CBBFF7E3C61F9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE repo DROP FOREIGN KEY FK_5C5CBBFF19EB6921
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE repo_colaboradores DROP FOREIGN KEY FK_53B2DC1ABD359B2D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE repo_colaboradores DROP FOREIGN KEY FK_53B2DC1AA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE client
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE repo
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE repo_colaboradores
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user
        SQL);
    }
}
