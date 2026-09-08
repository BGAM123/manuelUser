<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260903111921 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_category ADD CONSTRAINT FK_842703415DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE asset_category ADD CONSTRAINT FK_8427034112469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE asset_asset_type ADD CONSTRAINT FK_AE5B71BB5DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE asset_asset_type ADD CONSTRAINT FK_AE5B71BBA6A2CDC5 FOREIGN KEY (asset_type_id) REFERENCES asset_type (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE asset_etat_bien ADD CONSTRAINT FK_5645EB135DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE asset_etat_bien ADD CONSTRAINT FK_5645EB13BA4A878A FOREIGN KEY (etat_bien_id) REFERENCES etat_bien (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE asset_service ADD CONSTRAINT FK_8273DB845DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE asset_service ADD CONSTRAINT FK_8273DB84ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE asset_piece_jointe ADD CONSTRAINT FK_D206D1295DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE asset_piece_jointe ADD CONSTRAINT FK_D206D129A3741A05 FOREIGN KEY (piece_jointe_id) REFERENCES piece_jointe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE asset_assignment ADD CONSTRAINT FK_F624A7E05DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE asset_assignment ADD CONSTRAINT FK_F624A7E0ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE asset_assignment ADD CONSTRAINT FK_F624A7E0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE asset_assignment ADD CONSTRAINT FK_F624A7E06E6F1246 FOREIGN KEY (assigned_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE asset_assignment ADD CONSTRAINT FK_F624A7E0B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE asset_assignment ADD CONSTRAINT FK_F624A7E0896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE asset_assignment_piece_jointe ADD CONSTRAINT FK_78EAD12812E6DB99 FOREIGN KEY (asset_assignment_id) REFERENCES asset_assignment (id)');
        $this->addSql('ALTER TABLE asset_assignment_piece_jointe ADD CONSTRAINT FK_78EAD128A3741A05 FOREIGN KEY (piece_jointe_id) REFERENCES piece_jointe (id)');
        $this->addSql('ALTER TABLE asset_depreciation ADD CONSTRAINT FK_628101CFB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE asset_depreciation ADD CONSTRAINT FK_628101CF896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE asset_depreciation_link ADD CONSTRAINT FK_F4C8917FCFF653B FOREIGN KEY (depreciation_id) REFERENCES asset_depreciation (id)');
        $this->addSql('ALTER TABLE asset_depreciation_link ADD CONSTRAINT FK_F4C89175DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE depreciation_piece_jointe ADD CONSTRAINT FK_658AE781FCFF653B FOREIGN KEY (depreciation_id) REFERENCES asset_depreciation (id)');
        $this->addSql('ALTER TABLE depreciation_piece_jointe ADD CONSTRAINT FK_658AE781A3741A05 FOREIGN KEY (piece_jointe_id) REFERENCES piece_jointe (id)');
        $this->addSql('ALTER TABLE asset_maintenance ADD CONSTRAINT FK_A4FCA1E0BA4A878A FOREIGN KEY (etat_bien_id) REFERENCES etat_bien (id)');
        $this->addSql('ALTER TABLE asset_maintenance ADD CONSTRAINT FK_A4FCA1E0B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE asset_maintenance ADD CONSTRAINT FK_A4FCA1E0896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE asset_maintenance_link ADD CONSTRAINT FK_C03FA30CF6C202BC FOREIGN KEY (maintenance_id) REFERENCES asset_maintenance (id)');
        $this->addSql('ALTER TABLE asset_maintenance_link ADD CONSTRAINT FK_C03FA30C5DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE maintenance_piece_jointe ADD CONSTRAINT FK_E3E90076F6C202BC FOREIGN KEY (maintenance_id) REFERENCES asset_maintenance (id)');
        $this->addSql('ALTER TABLE maintenance_piece_jointe ADD CONSTRAINT FK_E3E90076A3741A05 FOREIGN KEY (piece_jointe_id) REFERENCES piece_jointe (id)');
        $this->addSql('ALTER TABLE asset_reevaluation ADD CONSTRAINT FK_1BD9EE80ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE asset_reevaluation ADD CONSTRAINT FK_1BD9EE80B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE asset_reevaluation ADD CONSTRAINT FK_1BD9EE80896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE asset_reevaluation_link ADD CONSTRAINT FK_C7A1AA563B6F995A FOREIGN KEY (reevaluation_id) REFERENCES asset_reevaluation (id)');
        $this->addSql('ALTER TABLE asset_reevaluation_link ADD CONSTRAINT FK_C7A1AA565DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE reevaluation_piece_jointe ADD CONSTRAINT FK_D57ADB53B6F995A FOREIGN KEY (reevaluation_id) REFERENCES asset_reevaluation (id)');
        $this->addSql('ALTER TABLE reevaluation_piece_jointe ADD CONSTRAINT FK_D57ADB5A3741A05 FOREIGN KEY (piece_jointe_id) REFERENCES piece_jointe (id)');
        $this->addSql('ALTER TABLE consumable CHANGE quantite quantite NUMERIC(18, 2) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE consumable_transfer CHANGE quantity_consumed quantity_consumed NUMERIC(18, 2) DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_asset_type DROP FOREIGN KEY FK_AE5B71BB5DA1941');
        $this->addSql('ALTER TABLE asset_asset_type DROP FOREIGN KEY FK_AE5B71BBA6A2CDC5');
        $this->addSql('ALTER TABLE asset_assignment DROP FOREIGN KEY FK_F624A7E05DA1941');
        $this->addSql('ALTER TABLE asset_assignment DROP FOREIGN KEY FK_F624A7E0ED5CA9E6');
        $this->addSql('ALTER TABLE asset_assignment DROP FOREIGN KEY FK_F624A7E0A76ED395');
        $this->addSql('ALTER TABLE asset_assignment DROP FOREIGN KEY FK_F624A7E06E6F1246');
        $this->addSql('ALTER TABLE asset_assignment DROP FOREIGN KEY FK_F624A7E0B03A8386');
        $this->addSql('ALTER TABLE asset_assignment DROP FOREIGN KEY FK_F624A7E0896DBBDE');
        $this->addSql('ALTER TABLE asset_assignment_piece_jointe DROP FOREIGN KEY FK_78EAD12812E6DB99');
        $this->addSql('ALTER TABLE asset_assignment_piece_jointe DROP FOREIGN KEY FK_78EAD128A3741A05');
        $this->addSql('ALTER TABLE asset_category DROP FOREIGN KEY FK_842703415DA1941');
        $this->addSql('ALTER TABLE asset_category DROP FOREIGN KEY FK_8427034112469DE2');
        $this->addSql('ALTER TABLE asset_depreciation DROP FOREIGN KEY FK_628101CFB03A8386');
        $this->addSql('ALTER TABLE asset_depreciation DROP FOREIGN KEY FK_628101CF896DBBDE');
        $this->addSql('ALTER TABLE asset_depreciation_link DROP FOREIGN KEY FK_F4C8917FCFF653B');
        $this->addSql('ALTER TABLE asset_depreciation_link DROP FOREIGN KEY FK_F4C89175DA1941');
        $this->addSql('ALTER TABLE asset_etat_bien DROP FOREIGN KEY FK_5645EB135DA1941');
        $this->addSql('ALTER TABLE asset_etat_bien DROP FOREIGN KEY FK_5645EB13BA4A878A');
        $this->addSql('ALTER TABLE asset_maintenance DROP FOREIGN KEY FK_A4FCA1E0BA4A878A');
        $this->addSql('ALTER TABLE asset_maintenance DROP FOREIGN KEY FK_A4FCA1E0B03A8386');
        $this->addSql('ALTER TABLE asset_maintenance DROP FOREIGN KEY FK_A4FCA1E0896DBBDE');
        $this->addSql('ALTER TABLE asset_maintenance_link DROP FOREIGN KEY FK_C03FA30CF6C202BC');
        $this->addSql('ALTER TABLE asset_maintenance_link DROP FOREIGN KEY FK_C03FA30C5DA1941');
        $this->addSql('ALTER TABLE asset_piece_jointe DROP FOREIGN KEY FK_D206D1295DA1941');
        $this->addSql('ALTER TABLE asset_piece_jointe DROP FOREIGN KEY FK_D206D129A3741A05');
        $this->addSql('ALTER TABLE asset_reevaluation DROP FOREIGN KEY FK_1BD9EE80ED5CA9E6');
        $this->addSql('ALTER TABLE asset_reevaluation DROP FOREIGN KEY FK_1BD9EE80B03A8386');
        $this->addSql('ALTER TABLE asset_reevaluation DROP FOREIGN KEY FK_1BD9EE80896DBBDE');
        $this->addSql('ALTER TABLE asset_reevaluation_link DROP FOREIGN KEY FK_C7A1AA563B6F995A');
        $this->addSql('ALTER TABLE asset_reevaluation_link DROP FOREIGN KEY FK_C7A1AA565DA1941');
        $this->addSql('ALTER TABLE asset_service DROP FOREIGN KEY FK_8273DB845DA1941');
        $this->addSql('ALTER TABLE asset_service DROP FOREIGN KEY FK_8273DB84ED5CA9E6');
        $this->addSql('ALTER TABLE consumable CHANGE quantite quantite NUMERIC(18, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE consumable_transfer CHANGE quantity_consumed quantity_consumed NUMERIC(18, 2) DEFAULT \'0.00\'');
        $this->addSql('ALTER TABLE depreciation_piece_jointe DROP FOREIGN KEY FK_658AE781FCFF653B');
        $this->addSql('ALTER TABLE depreciation_piece_jointe DROP FOREIGN KEY FK_658AE781A3741A05');
        $this->addSql('ALTER TABLE maintenance_piece_jointe DROP FOREIGN KEY FK_E3E90076F6C202BC');
        $this->addSql('ALTER TABLE maintenance_piece_jointe DROP FOREIGN KEY FK_E3E90076A3741A05');
        $this->addSql('ALTER TABLE reevaluation_piece_jointe DROP FOREIGN KEY FK_D57ADB53B6F995A');
        $this->addSql('ALTER TABLE reevaluation_piece_jointe DROP FOREIGN KEY FK_D57ADB5A3741A05');
    }
}
