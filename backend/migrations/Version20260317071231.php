<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260317071231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE core_users CHANGE reset_token reset_token LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_user_search ON core_users (search)');
        $this->addSql('ALTER TABLE cour_courrier CHANGE commentaire_public commentaire_public LONGTEXT DEFAULT NULL, CHANGE commentaire_interne commentaire_interne LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE cour_courrier_depart DROP id_correspondant, CHANGE provenances_copie provenances_copie JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE cour_piece_jointe RENAME INDEX fk_piece_jointe_transmission TO IDX_9E9F31BA5C466CED');
        $this->addSql('ALTER TABLE cour_reponse CHANGE types_courrier_ids types_courrier_ids JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE cour_transmission CHANGE structures_copie structures_copie JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_user_search ON core_users');
        $this->addSql('ALTER TABLE core_users CHANGE reset_token reset_token VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE cour_courrier CHANGE commentaire_public commentaire_public TEXT DEFAULT NULL, CHANGE commentaire_interne commentaire_interne TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE cour_courrier_depart ADD id_correspondant JSON DEFAULT NULL, CHANGE provenances_copie provenances_copie LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE cour_piece_jointe RENAME INDEX idx_9e9f31ba5c466ced TO FK_piece_jointe_transmission');
        $this->addSql('ALTER TABLE cour_reponse CHANGE types_courrier_ids types_courrier_ids LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE cour_transmission CHANGE structures_copie structures_copie LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
    }
}
