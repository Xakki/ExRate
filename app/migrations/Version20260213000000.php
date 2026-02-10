<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename `source` to `provider`';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX unique_rate_idx ON exchange_rates');
        $this->addSql('alter table exchange_rates change source_id provider_id int UNSIGNED default 1 not null');
        $this->addSql('alter table exchange_rates modify currency varchar(12) not null');
        $this->addSql('alter table exchange_rates modify base_currency varchar(12) not null');
        $this->addSql('CREATE UNIQUE INDEX unique_rate_idx ON exchange_rates (date, currency, base_currency, provider_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX unique_rate_idx ON exchange_rates');
        $this->addSql('alter table exchange_rates change provider_id source_id int UNSIGNED default 1 not null');
        $this->addSql('CREATE UNIQUE INDEX unique_rate_idx ON exchange_rates (date, currency, base_currency, source_id)');
    }
}
