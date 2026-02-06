<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250204000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create exchange_rates table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE exchange_rates (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', currency VARCHAR(3) NOT NULL, base_currency VARCHAR(3) NOT NULL, rate NUMERIC(20, 8) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX unique_rate_idx (date, currency, base_currency), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE exchange_rates');
    }
}
