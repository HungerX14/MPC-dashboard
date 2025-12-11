<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210071525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add new columns
        $this->addSql('ALTER TABLE site ADD type VARCHAR(50) DEFAULT \'wordpress\' NOT NULL');
        $this->addSql('ALTER TABLE site ADD config JSON DEFAULT NULL');

        // Add owner_id as nullable first
        $this->addSql('ALTER TABLE site ADD owner_id INT DEFAULT NULL');

        // Set existing sites to first user (if exists)
        $this->addSql('UPDATE site SET owner_id = (SELECT MIN(id) FROM "user") WHERE owner_id IS NULL');

        // Now make it NOT NULL and add constraint
        $this->addSql('ALTER TABLE site ALTER owner_id SET NOT NULL');
        $this->addSql('ALTER TABLE site ADD CONSTRAINT FK_694309E47E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_694309E47E3C61F9 ON site (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site DROP CONSTRAINT FK_694309E47E3C61F9');
        $this->addSql('DROP INDEX IDX_694309E47E3C61F9');
        $this->addSql('ALTER TABLE site DROP type');
        $this->addSql('ALTER TABLE site DROP config');
        $this->addSql('ALTER TABLE site DROP owner_id');
    }
}
