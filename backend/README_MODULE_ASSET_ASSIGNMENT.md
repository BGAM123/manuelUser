# Module Asset Assignment - Documentation

## Vue d'ensemble

Ce module gère l'affectation des biens aux services, ainsi que les modules de maintenance, réévaluation et dépréciation des biens.

## Fichiers créés/modifiés

### 1. Module Asset Assignment (Affectation des biens)

#### Entités
- `src/Entity/AssetAssignment.php` - Entité d'affectation de bien avec relations ManyToOne Asset, Service et ManyToMany PieceJointe
- `src/Entity/Asset.php` - Ajout de la relation OneToMany vers AssetAssignment
- `src/Entity/Service.php` - Ajout de la relation OneToMany vers AssetAssignment

#### Repository
- `src/Repository/AssetAssignmentRepository.php` - Repository pour AssetAssignment

#### Services
- `src/Service/AssetAssignmentService.php` - Service métier pour la gestion des affectations (création, mise à jour, suppression, gestion des pièces jointes, création automatique)
- `src/Service/AssetAssignmentResponseBuilder.php` - Builder pour formater les réponses API d'affectation

#### Contrôleurs
- `src/Controller/AssetAssignments/CreateAssetAssignmentController.php` - POST /asset-assignments - Créer une affectation
- `src/Controller/AssetAssignments/UpdateAssetAssignmentController.php` - PUT /asset-assignments/{id} - Modifier une affectation
- `src/Controller/Assets/GetAssetAssignmentsController.php` - GET /assets/{id}/assignments - Lister les affectations d'un bien
- `src/Controller/AssetAssignments/DeleteAssetAssignmentController.php` - DELETE /asset-assignments/{id} - Supprimer une affectation (soft delete)
- `src/Controller/AssetAssignments/DeleteAssetAssignmentPieceJointeController.php` - DELETE /asset-assignments/{assignmentId}/pieces-jointes/{pieceJointeId} - Supprimer une pièce jointe d'une affectation

### 2. Module Asset Maintenance (Maintenance des biens)

#### Entités
- `src/Entity/AssetMaintenance.php` - Entité de maintenance avec relations ManyToMany Asset et PieceJointe
- `src/Entity/Asset.php` - Ajout de la relation ManyToMany vers AssetMaintenance

#### Repository
- `src/Repository/AssetMaintenanceRepository.php` - Repository pour AssetMaintenance

#### Services
- `src/Service/AssetMaintenanceService.php` - Service métier pour la gestion des maintenances
- `src/Service/AssetMaintenanceResponseBuilder.php` - Builder pour formater les réponses API de maintenance

#### Contrôleurs
- `src/Controller/AssetMaintenances/CreateAssetMaintenanceController.php` - POST /asset-maintenances - Créer une maintenance
- `src/Controller/AssetMaintenances/UpdateAssetMaintenanceController.php` - PUT /asset-maintenances/{id} - Modifier une maintenance
- `src/Controller/AssetMaintenances/GetAssetMaintenanceController.php` - GET /asset-maintenances/{id} - Détail d'une maintenance
- `src/Controller/AssetMaintenances/DeleteAssetMaintenanceController.php` - DELETE /asset-maintenances/{id} - Supprimer une maintenance
- `src/Controller/AssetMaintenances/DeleteAssetMaintenancePieceJointeController.php` - DELETE /asset-maintenances/{maintenanceId}/pieces-jointes/{pieceJointeId} - Supprimer une pièce jointe d'une maintenance

### 3. Module Asset Reevaluation (Réévaluation des biens)

#### Entités
- `src/Entity/AssetReevaluation.php` - Entité de réévaluation avec relations ManyToMany Asset et PieceJointe
- `src/Entity/Asset.php` - Ajout de la relation ManyToMany vers AssetReevaluation

#### Repository
- `src/Repository/AssetReevaluationRepository.php` - Repository pour AssetReevaluation

#### Services
- `src/Service/AssetReevaluationService.php` - Service métier pour la gestion des réévaluations
- `src/Service/AssetReevaluationResponseBuilder.php` - Builder pour formater les réponses API de réévaluation

#### Contrôleurs
- `src/Controller/AssetReevaluations/CreateAssetReevaluationController.php` - POST /asset-reevaluations - Créer une réévaluation
- `src/Controller/AssetReevaluations/UpdateAssetReevaluationController.php` - PUT /asset-reevaluations/{id} - Modifier une réévaluation
- `src/Controller/AssetReevaluations/GetAssetReevaluationController.php` - GET /asset-reevaluations/{id} - Détail d'une réévaluation
- `src/Controller/AssetReevaluations/DeleteAssetReevaluationController.php` - DELETE /asset-reevaluations/{id} - Supprimer une réévaluation
- `src/Controller/AssetReevaluations/DeleteAssetReevaluationPieceJointeController.php` - DELETE /asset-reevaluations/{reevaluationId}/pieces-jointes/{pieceJointeId} - Supprimer une pièce jointe d'une réévaluation

### 4. Module Asset Depreciation (Dépréciation des biens)

#### Entités
- `src/Entity/AssetDepreciation.php` - Entité de dépréciation avec relations ManyToMany Asset et PieceJointe
- `src/Entity/Asset.php` - Ajout de la relation ManyToMany vers AssetDepreciation

#### Repository
- `src/Repository/AssetDepreciationRepository.php` - Repository pour AssetDepreciation

#### Services
- `src/Service/AssetDepreciationService.php` - Service métier pour la gestion des dépréciations
- `src/Service/AssetDepreciationResponseBuilder.php` - Builder pour formater les réponses API de dépréciation

#### Contrôleurs
- `src/Controller/AssetDepreciations/CreateAssetDepreciationController.php` - POST /asset-depreciations - Créer une dépréciation
- `src/Controller/AssetDepreciations/UpdateAssetDepreciationController.php` - PUT /asset-depreciations/{id} - Modifier une dépréciation
- `src/Controller/AssetDepreciations/GetAssetDepreciationController.php` - GET /asset-depreciations/{id} - Détail d'une dépréciation
- `src/Controller/AssetDepreciations/DeleteAssetDepreciationController.php` - DELETE /asset-depreciations/{id} - Supprimer une dépréciation
- `src/Controller/AssetDepreciations/DeleteAssetDepreciationPieceJointeController.php` - DELETE /asset-depreciations/{depreciationId}/pieces-jointes/{pieceJointeId} - Supprimer une pièce jointe d'une dépréciation

### 5. Modifications existantes

#### Contrôleurs Assets
- **Supprimé**: `src/Controller/Assets/UpdateAssetController.php` - PATCH /assets/{id} (remplacé par POST)
- **Créé**: `src/Controller/Assets/DeleteAssetPieceJointeController.php` - DELETE /assets/pieces-jointes/{id} - Supprimer une pièce jointe d'un bien

#### Services
- `src/Service/AssetManagementService.php` - Ajout de la logique de création automatique d'affectations lors de la création/mise à jour de biens
- `src/Service/AssetResponseBuilder.php` - Ajout des blocs affectations, maintenances, réévaluations, dépréciations dans la réponse détaillée des biens
- `src/Service/FileUploadService.php` - Ajout de la méthode deleteFile() pour supprimer les fichiers physiques

## API Endpoints

### Asset Assignment
- `POST /asset-assignments` - Créer une affectation
- `PUT /asset-assignments/{id}` - Modifier une affectation
- `GET /assets/{id}/assignments` - Lister les affectations d'un bien
- `DELETE /asset-assignments/{id}` - Supprimer une affectation (soft delete)
- `DELETE /asset-assignments/{assignmentId}/pieces-jointes/{pieceJointeId}` - Supprimer une pièce jointe d'une affectation

### Asset Maintenance
- `POST /asset-maintenances` - Créer une maintenance
- `PUT /asset-maintenances/{id}` - Modifier une maintenance
- `GET /asset-maintenances/{id}` - Détail d'une maintenance
- `DELETE /asset-maintenances/{id}` - Supprimer une maintenance
- `DELETE /asset-maintenances/{maintenanceId}/pieces-jointes/{pieceJointeId}` - Supprimer une pièce jointe d'une maintenance

### Asset Reevaluation
- `POST /asset-reevaluations` - Créer une réévaluation
- `PUT /asset-reevaluations/{id}` - Modifier une réévaluation
- `GET /asset-reevaluations/{id}` - Détail d'une réévaluation
- `DELETE /asset-reevaluations/{id}` - Supprimer une réévaluation
- `DELETE /asset-reevaluations/{reevaluationId}/pieces-jointes/{pieceJointeId}` - Supprimer une pièce jointe d'une réévaluation

### Asset Depreciation
- `POST /asset-depreciations` - Créer une dépréciation
- `PUT /asset-depreciations/{id}` - Modifier une dépréciation
- `GET /asset-depreciations/{id}` - Détail d'une dépréciation
- `DELETE /asset-depreciations/{id}` - Supprimer une dépréciation
- `DELETE /asset-depreciations/{depreciationId}/pieces-jointes/{pieceJointeId}` - Supprimer une pièce jointe d'une dépréciation

### Assets
- `DELETE /assets/pieces-jointes/{id}` - Supprimer une pièce jointe d'un bien

## Fonctionnalités clés

1. **Affectations automatiques** : Création automatique d'affectations lors de la création d'un bien avec service_id, ou lors de la modification du service d'un bien
2. **Soft delete** : Les affectations utilisent un soft delete (isDelete) au lieu d'une suppression physique
3. **Gestion des pièces jointes** : Tous les modules supportent l'upload de pièces jointes avec multipart/form-data
4. **Suppression de pièces jointes** : Suppression du fichier physique + enregistrement DB + liaison
5. **Enrichissement des réponses** : L'API GET /assets/{id} inclut maintenant les blocs affectations, maintenances, réévaluations et dépréciations
6. **Swagger documentation** : Tous les endpoints sont documentés avec OpenAPI/Swagger

## Migrations

Les migrations Doctrine ont été générées et exécutées pour créer les tables nécessaires :
- `asset_assignment`
- `asset_maintenance`
- `asset_reevaluation`
- `asset_depreciation`
- Tables de liaison pour les relations ManyToMany avec Asset et PieceJointe
