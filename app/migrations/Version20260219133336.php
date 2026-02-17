<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219133336 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('rename table exchange_rates TO rates');
        $this->addSql('alter table rates modify rate decimal(20, 10) not null');
        $this->addSql("create table rates_extend
(
    id            int auto_increment
        primary key,
    date          date                   not null comment '(DC2Type:date_immutable)',
    currency      varchar(12)            not null,
    base_currency varchar(12)            not null,
    rate_open     decimal(20, 10)        not null,
    rate_low      decimal(20, 10)        not null,
    rate_high     decimal(20, 10)        not null,
    rate          decimal(20, 10)        not null comment 'Close',
    volume        decimal(20, 10)        not null,
    created_at    datetime               not null comment '(DC2Type:datetime_immutable)',
    provider_id   int unsigned default 1 not null,
    constraint unique_rate_idx
        unique (provider_id, base_currency, date, currency)
)
    collate = utf8mb4_unicode_ci");

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE rates_extend');
        $this->addSql('RENAME TABLE rates TO exchange_rates');
    }
}
