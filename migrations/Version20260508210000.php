<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables category, orders, order_items, site_settings + relation article.category_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            UNIQUE INDEX UNIQ_CATEGORY_SLUG (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE orders (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            status VARCHAR(32) NOT NULL,
            total NUMERIC(10, 2) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            reference VARCHAR(255) DEFAULT NULL,
            INDEX IDX_ORDERS_USER (user_id),
            INDEX IDX_ORDERS_STATUS (status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE order_items (
            id INT AUTO_INCREMENT NOT NULL,
            order_id INT NOT NULL,
            article_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            image VARCHAR(500) DEFAULT NULL,
            unit_price NUMERIC(10, 2) NOT NULL,
            quantity INT NOT NULL,
            INDEX IDX_ORDER_ITEMS_ORDER (order_id),
            INDEX IDX_ORDER_ITEMS_ARTICLE (article_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE site_settings (
            setting_key VARCHAR(100) NOT NULL,
            setting_value LONGTEXT DEFAULT NULL,
            PRIMARY KEY(setting_key)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_ORDERS_USER FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_ORDER_ITEMS_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_ORDER_ITEMS_ARTICLE FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE article ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_ARTICLE_CATEGORY FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_ARTICLE_CATEGORY ON article (category_id)');

        $this->addSql("INSERT INTO category (name, slug) VALUES
            ('Illustration', 'illustration'),
            ('Photographie', 'photographie'),
            ('Graphisme', 'graphisme'),
            ('Peinture', 'peinture'),
            ('Digital Painting', 'digital-painting'),
            ('Motion Design', 'motion-design')");

        $this->addSql("INSERT INTO site_settings (setting_key, setting_value) VALUES
            ('site_name', 'Placeaupro'),
            ('site_email', 'contact@placeaupro.com'),
            ('site_description', 'Plateforme dédiée aux artistes professionnels.'),
            ('maintenance_mode', '0'),
            ('shipping_fee', '4.90')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_ARTICLE_CATEGORY');
        $this->addSql('DROP INDEX IDX_ARTICLE_CATEGORY ON article');
        $this->addSql('ALTER TABLE article DROP category_id');

        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_ORDER_ITEMS_ARTICLE');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_ORDER_ITEMS_ORDER');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_ORDERS_USER');

        $this->addSql('DROP TABLE site_settings');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE category');
    }
}
