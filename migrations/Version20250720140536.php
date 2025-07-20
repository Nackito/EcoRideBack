<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250720140536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ride ADD departure_date DATE NOT NULL, ADD departure_hour TIME NOT NULL, ADD arrival_date DATE NOT NULL, ADD arrival_hour TIME NOT NULL, DROP departure_time, DROP origin_lat_lng, DROP destination_lat_lng, DROP estimated_distance, DROP estimated_duration, DROP waypoints, DROP conditions');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ride ADD departure_time DATETIME NOT NULL, ADD origin_lat_lng VARCHAR(255) DEFAULT NULL, ADD destination_lat_lng VARCHAR(255) DEFAULT NULL, ADD estimated_distance INT DEFAULT NULL, ADD estimated_duration INT DEFAULT NULL, ADD waypoints JSON DEFAULT NULL, ADD conditions LONGTEXT DEFAULT NULL, DROP departure_date, DROP departure_hour, DROP arrival_date, DROP arrival_hour');
    }
}
