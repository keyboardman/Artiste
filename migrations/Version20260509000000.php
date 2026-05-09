<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des champs social/phone sur user et création de la table article_image';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD social VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD phone VARCHAR(30) DEFAULT NULL');

        $this->addSql('CREATE TABLE article_image (
            id INT AUTO_INCREMENT NOT NULL,
            article_id INT NOT NULL,
            path VARCHAR(500) NOT NULL,
            alt VARCHAR(255) DEFAULT NULL,
            position INT NOT NULL,
            INDEX IDX_ARTICLE_IMAGE_ARTICLE (article_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE article_image ADD CONSTRAINT FK_ARTICLE_IMAGE_ARTICLE
            FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article_image DROP FOREIGN KEY FK_ARTICLE_IMAGE_ARTICLE');
        $this->addSql('DROP TABLE article_image');
        $this->addSql('ALTER TABLE user DROP social');
        $this->addSql('ALTER TABLE user DROP phone');
    }
}
