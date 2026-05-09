<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ created_at sur user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        // Backfill : on met les comptes existants 60 jours dans le passé pour qu'ils n'apparaissent pas dans les rapports hebdomadaires.
        $this->addSql('UPDATE user SET created_at = DATE_SUB(NOW(), INTERVAL 60 DAY) WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE user MODIFY created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP created_at');
    }
}
