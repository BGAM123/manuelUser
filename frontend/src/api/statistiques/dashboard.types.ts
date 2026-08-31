/**
 * Types TypeScript pour le nouvel endpoint centralisé /api/stats/dashboard.
 * Payload unique retournant toutes les statistiques en un seul appel.
 *
 * Structure confirmée le 2026-08-24 sur http://185.98.136.192:8075/api/stats/dashboard
 */

import type {
  VueGlobale,
  RepartitionCategorieItem,
  RepartitionStructureNombreItem,
  RepartitionStructureValeurItem,
  RepartitionProjetItem,
  RepartitionRegionItem,
  EvolutionMensuellePatrimoineItem,
  EvolutionMensuelleCompteItem,
  EvolutionGapItem,
  ClassementRegionsItem,
  VehiculesVueGlobale,
  GeoNode,
  FinancementItem,
  VehiculeTypeItem,
  VehiculesAnciennete,
  CroisementEtatDepartementItem,
  VehiculesClassementRegionItem,
  TerrainsVueGlobale,
  TerrainsEnLitigeResponse,
  AcquisitionsParAnneeItem,
  CroisementBatiDepartementItem,
  CroisementOccupationDepartementItem,
  TerrainsClassementRegionItem,
  BatimentsVueGlobale,
  BatimentsClassementRegionItem,
  StructuresVueGlobale,
  StructuresCoutRefectionResponse,
  SuiviEvolutionPluriannuelleItem,
  SuiviClassementAnnuelItem,
  PointAttentionPrioritaireItem,
  SuiviCollecteDonneesResponse,
} from "./statistiques.types";

// ── Section Patrimoine Général ─────────────────────────────────────────────

export interface DashboardPatrimoine {
  vue_globale: VueGlobale;
  repartition_par_categorie: RepartitionCategorieItem[];
  /** critere=nombre (top 10) */
  repartition_par_structure: RepartitionStructureNombreItem[];
  repartition_par_projet: RepartitionProjetItem[];
  /** critere=valeur (top 6) */
  top_services_valeur: RepartitionStructureValeurItem[];
  repartition_par_region: RepartitionRegionItem[];
  evolution_mensuelle: {
    patrimoine: EvolutionMensuellePatrimoineItem[];
    mouvements: EvolutionMensuelleCompteItem[];
    maintenance: EvolutionMensuelleCompteItem[];
  };
  evolution_gap: EvolutionGapItem[];
  classement_regions: ClassementRegionsItem[];
}

// ── Section Véhicules ──────────────────────────────────────────────────────

export interface DashboardVehicules {
  vue_globale: VehiculesVueGlobale;
  repartition_par_type: VehiculeTypeItem[];
  repartition_financement: FinancementItem[];
  anciennete: VehiculesAnciennete;
  repartition_geographique: GeoNode[];
  croisement_etat_departement: CroisementEtatDepartementItem[];
  classement_regions: VehiculesClassementRegionItem[];
}

// ── Section Terrains ───────────────────────────────────────────────────────

export interface DashboardTerrains {
  vue_globale: TerrainsVueGlobale;
  en_litige: TerrainsEnLitigeResponse;
  acquisitions_par_annee: AcquisitionsParAnneeItem[];
  croisement_bati_departement: CroisementBatiDepartementItem[];
  croisement_occupation_departement: CroisementOccupationDepartementItem[];
  repartition_geographique: GeoNode[];
  classement_regions: TerrainsClassementRegionItem[];
}

// ── Section Bâtiments ──────────────────────────────────────────────────────

export interface DashboardBatimentsFinancement {
  /** Clé confirmée le 2026-08-24 : par_source_financement (pas par_source) */
  par_source_financement: FinancementItem[];
  par_projet: RepartitionProjetItem[];
}

export interface DashboardBatiments {
  vue_globale: BatimentsVueGlobale;
  financement: DashboardBatimentsFinancement;
  en_litige: { nombre: number; batiments: { id: number; reference: string; nom: string }[] };
  annee_construction: AcquisitionsParAnneeItem[];
  annee_refection: AcquisitionsParAnneeItem[];
  croisement_etat_departement: CroisementEtatDepartementItem[];
  repartition_geographique: GeoNode[];
  classement_regions: BatimentsClassementRegionItem[];
}

// ── Section Structures ────────────────────────────────────────────────────

export interface DashboardStructures {
  vue_globale: StructuresVueGlobale;
  cout_refection: StructuresCoutRefectionResponse;
  annee_construction: AcquisitionsParAnneeItem[];
  repartition_geographique: GeoNode[];
}

// ── Section Suivi & Alertes ────────────────────────────────────────────────

export interface DashboardSuivi {
  collecte_donnees: SuiviCollecteDonneesResponse;
  evolution_pluriannuelle: SuiviEvolutionPluriannuelleItem[];
  classement_annuel_regions: SuiviClassementAnnuelItem[];
  points_attention_prioritaires: PointAttentionPrioritaireItem[];
}

// ── Payload complet du dashboard ──────────────────────────────────────────

export interface DashboardStats {
  patrimoine: DashboardPatrimoine;
  vehicules: DashboardVehicules;
  terrains: DashboardTerrains;
  batiments: DashboardBatiments;
  structures: DashboardStructures;
  suivi: DashboardSuivi;
}
