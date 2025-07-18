<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250717232605 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, passenger_id INT NOT NULL, ride_id INT NOT NULL, number_of_seats INT NOT NULL, status VARCHAR(50) NOT NULL, message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', total_price NUMERIC(6, 2) DEFAULT NULL, INDEX IDX_E00CEDDE4502E565 (passenger_id), INDEX IDX_E00CEDDE302A8A70 (ride_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ride (id INT AUTO_INCREMENT NOT NULL, driver_id INT NOT NULL, origin VARCHAR(255) NOT NULL, destination VARCHAR(255) NOT NULL, departure_time DATETIME NOT NULL, available_seats INT NOT NULL, price NUMERIC(6, 2) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(50) NOT NULL, origin_lat_lng VARCHAR(255) DEFAULT NULL, destination_lat_lng VARCHAR(255) DEFAULT NULL, estimated_distance INT DEFAULT NULL, estimated_duration INT DEFAULT NULL, waypoints JSON DEFAULT NULL, conditions LONGTEXT DEFAULT NULL, INDEX IDX_9B3D7CD0C3423909 (driver_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, birth_date DATE DEFAULT NULL, bio LONGTEXT DEFAULT NULL, is_verified TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', profile_picture VARCHAR(255) DEFAULT NULL, rating NUMERIC(3, 2) DEFAULT NULL, total_rides INT DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE4502E565 FOREIGN KEY (passenger_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE302A8A70 FOREIGN KEY (ride_id) REFERENCES ride (id)');
        $this->addSql('ALTER TABLE ride ADD CONSTRAINT FK_9B3D7CD0C3423909 FOREIGN KEY (driver_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY fk_utilisateur_avis');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY fk_utilisateur');
        $this->addSql('ALTER TABLE avisutilisateur DROP FOREIGN KEY fk_avis');
        $this->addSql('ALTER TABLE avisutilisateur DROP FOREIGN KEY fk_avisUtilisateur');
        $this->addSql('ALTER TABLE covoiturage DROP FOREIGN KEY fk_utilisateur_covoit');
        $this->addSql('ALTER TABLE covoiturage DROP FOREIGN KEY fk_voiture');
        $this->addSql('ALTER TABLE utilisateurcovoiturage DROP FOREIGN KEY fk_covoit_utilisateur');
        $this->addSql('ALTER TABLE utilisateurcovoiturage DROP FOREIGN KEY fk_utilisateur_passager');
        $this->addSql('ALTER TABLE roleutilisateur DROP FOREIGN KEY fk_role');
        $this->addSql('ALTER TABLE roleutilisateur DROP FOREIGN KEY fk_utilisateur_role');
        $this->addSql('DROP TABLE avis');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE voiture');
        $this->addSql('DROP TABLE avisutilisateur');
        $this->addSql('DROP TABLE covoiturage');
        $this->addSql('DROP TABLE utilisateurcovoiturage');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE roleutilisateur');
        $this->addSql('DROP TABLE marque');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE avis (avis_id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, commentaire TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, note INT DEFAULT NULL, statut VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, INDEX fk_utilisateur_avis (utilisateur_id), PRIMARY KEY(avis_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE role (role_id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, PRIMARY KEY(role_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE voiture (voiture_id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, modele VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, immatriculation VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, energie VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, couleur VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, date_premiere_immatriculation DATE DEFAULT NULL, INDEX fk_utilisateur (utilisateur_id), PRIMARY KEY(voiture_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE avisutilisateur (utilisateur_id INT NOT NULL, avis_id INT NOT NULL, INDEX fk_avis (avis_id), INDEX fk_avisUtilisateur (utilisateur_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE covoiturage (covoiturage_id INT AUTO_INCREMENT NOT NULL, voiture_id INT DEFAULT NULL, utilisateur_id INT DEFAULT NULL, date_depart DATE DEFAULT NULL, heure_depart TIME DEFAULT NULL, date_arrivee DATE DEFAULT NULL, heure_arrivee TIME DEFAULT NULL, statut VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, nb_place INT DEFAULT NULL, prix DOUBLE PRECISION DEFAULT NULL, lieu_depart VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, lieu_arrive VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, INDEX fk_utilisateur_covoit (utilisateur_id), INDEX fk_voiture (voiture_id), PRIMARY KEY(covoiturage_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE utilisateurcovoiturage (utilisateur_id INT NOT NULL, covoiturage_id INT NOT NULL, INDEX fk_covoit_utilisateur (covoiturage_id), INDEX fk_utilisateur_passager (utilisateur_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE utilisateur (utilisateur_id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, prenom VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, email VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, password VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, telephone VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, adresse TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, date_naissance DATE DEFAULT NULL, photo BLOB DEFAULT NULL, pseudo VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, credits INT DEFAULT 20, avis_id INT DEFAULT NULL, PRIMARY KEY(utilisateur_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE roleutilisateur (utilisateur_id INT NOT NULL, role_id INT NOT NULL, INDEX fk_role (role_id), INDEX fk_utilisateur_role (utilisateur_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE marque (marque_id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, PRIMARY KEY(marque_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT fk_utilisateur_avis FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (utilisateur_id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT fk_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (utilisateur_id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE avisutilisateur ADD CONSTRAINT fk_avis FOREIGN KEY (avis_id) REFERENCES avis (avis_id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE avisutilisateur ADD CONSTRAINT fk_avisUtilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (utilisateur_id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE covoiturage ADD CONSTRAINT fk_utilisateur_covoit FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (utilisateur_id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE covoiturage ADD CONSTRAINT fk_voiture FOREIGN KEY (voiture_id) REFERENCES voiture (voiture_id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE utilisateurcovoiturage ADD CONSTRAINT fk_covoit_utilisateur FOREIGN KEY (covoiturage_id) REFERENCES covoiturage (covoiturage_id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE utilisateurcovoiturage ADD CONSTRAINT fk_utilisateur_passager FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (utilisateur_id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE roleutilisateur ADD CONSTRAINT fk_role FOREIGN KEY (role_id) REFERENCES role (role_id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE roleutilisateur ADD CONSTRAINT fk_utilisateur_role FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (utilisateur_id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE4502E565');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE302A8A70');
        $this->addSql('ALTER TABLE ride DROP FOREIGN KEY FK_9B3D7CD0C3423909');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE ride');
        $this->addSql('DROP TABLE user');
    }
}
