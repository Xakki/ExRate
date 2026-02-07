<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209034755 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX unique_rate_idx ON exchange_rates');
        $this->addSql('ALTER TABLE exchange_rates ADD source_id INT DEFAULT 1 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX unique_rate_idx ON exchange_rates (date, currency, base_currency, source_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX unique_rate_idx ON exchange_rates');
        $this->addSql('ALTER TABLE exchange_rates DROP source_id');
        $this->addSql('CREATE UNIQUE INDEX unique_rate_idx ON exchange_rates (date, currency, base_currency)');
    }
}
