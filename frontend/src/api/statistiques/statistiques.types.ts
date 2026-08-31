/**
 * Types et interfaces TypeScript complets pour le module Statistiques MINEPIA.
 * Conforme au Manuel d'intégration Frontend (API REST Symfony /api/stats/*, 40 endpoints).
 */

// ─── 1. Filtres Communs (Query Parameters) ─────────────────────────────────

export interface StatisticsFilter {
  // IDs de structures/services (chaque ID inclut ses descendants d'organigramme).
  // Nom confirmé sur le Swagger de /api/stats/dashboard (2026-08-28) — remplace
  // les anciens `service_ids` et `organigramme_service_id`, non reconnus par le backend.
  services_id?: number[];
  // ID de catégorie — UN SEUL id, pas une liste (vérifié sur le Swagger de
  // /api/stats/dashboard, 2026-08-28). Le backend ignore silencieusement
  // l'ancien `categorie_ids` (pluriel). Évolution vers une liste annoncée côté
  // backend mais pas encore livrée — voir filterMapper.ts pour la stratégie
  // interimaire (dernière catégorie cochée envoyée). Pour categorie_id=29
  // (TERRAINS) et categorie_id=35 (BÂTIMENTS), la réponse est en plus
  // restructurée : la clé `patrimoine` disparaît, remplacée par `terrains` /
  // `batiments` seul — les autres catégories (véhicules, informatique, etc.)
  // gardent `patrimoine`.
  categorie_id?: number;
  type_bien_ids?: number[];
  sous_type_ids?: number[];
  projet_ids?: number[];
  statuts?: ("ACTIF" | "EN_MAINTENANCE" | "SORTIS")[];
  etat_bien_ids?: number[];
  region_ids?: number[];
  departement_ids?: number[];
  arrondissement_ids?: number[];
  annee_debut?: number;            // Borne basse incluse sur dateAcquisition
  annee_fin?: number;              // Borne haute incluse
  /** Année d'exercice — restreint TOUS les KPIs aux biens acquis cette année précise (SUBSTRING(dateAcquisition, 1, 4), cf. StatisticsRepository::applyFiltersOnAsset). Confirmé sur GET /api/stats/dashboard?exercice=2026 (2026-08-29). */
  exercice?: number;
  [key: string]: unknown;
}

// ─── 2. Section 5.1 : Patrimoine Général ───────────────────────────────────

export interface BiensParCategorieItem {
  categorie_id: number;
  categorie_nom: string;
  nombre_biens: number;
  valeur_patrimoine: number;
}

export interface StructuresCentralesVsDeconcentrees {
  central: number;
  deconcentre: number;
}

export interface BiensMauvaisEtat {
  nombre_biens: number;
  total_biens: number;
  pourcentage: number;
}

export interface BiensSansInformation {
  sans_etat: number;
  sans_occupation: number;
  sans_securisation: number;
}

export interface VueGlobale {
  total_biens: number;
  valeur_totale_patrimoine: number;
  total_biens_actifs: number;
  total_biens_sortis: number;
  total_biens_maintenance: number;
  total_services_avec_biens: number;
  biens_par_categorie: BiensParCategorieItem[];
  structures_centrales_vs_deconcentrees: StructuresCentralesVsDeconcentrees;
  biens_mauvais_etat: BiensMauvaisEtat;
  biens_sans_information: BiensSansInformation;
}

export interface RepartitionStructureNombreItem {
  service_id: number;
  service_nom: string;
  nombre_biens: number;
}

export interface RepartitionStructureValeurItem {
  service_id: number;
  service_nom: string;
  valeur_patrimoine: number;
  nombre_biens: number;
}

export interface RepartitionProjetItem {
  projet_id: number;
  projet_nom: string;
  nombre_biens: number;
}

export interface RepartitionRegionItem {
  region_id: number;
  region_nom: string;
  nombre_biens: number;
  valeur_patrimoine: number;
}

export interface EvolutionMensuellePatrimoineItem {
  mois: string; // "YYYY-MM" (valeur cumulée)
  valeur_patrimoine: number;
}

export interface EvolutionMensuelleCompteItem {
  mois: string; // "YYYY-MM"
  nombre_biens: number;
}

export interface RepartitionCategorieItem {
  categorie_id: number;
  categorie_nom: string;
  nombre_biens: number;
  valeur_patrimoine: number;
}

export interface EvolutionGapItem {
  region_id: number;
  region_nom: string;
  categorie_id: number;
  categorie_nom: string;
  annee_debut: number;
  annee_fin: number;
  gap: number;
}

export interface ClassementRegionsItem {
  categorie_id: number;
  categorie_nom: string;
  regions: {
    region_id: number;
    region_nom: string;
    nombre_biens: number;
  }[];
}

// ─── 3. Section 5.2 : Matériel Roulant (Véhicules) ──────────────────────────

export interface RegionCountNode {
  region_id: number;
  region_nom: string;
  nombre: number;
}

export interface VehiculesVueGlobale {
  total_vehicules: number;
  repartition_par_etat: {
    etat: string;
    nombre: number;
    pourcentage: number;
  }[];
  vehicules_a_reformer: {
    total: number;
    par_region: RegionCountNode[];
  };
  vehicules_disparus: {
    nombre: number;
    total_vehicules: number;
    pourcentage: number;
    par_region: RegionCountNode[];
  };
  carte_grise_manquante: {
    nombre: number;
    total_vehicules: number;
    pourcentage: number;
  };
  valeur_parc: {
    valeur_totale: number;
    total_vehicules: number;
    vehicules_avec_valeur_renseignee: number;
  };
}

export interface GeoArrondissementNode {
  arrondissement_id: number;
  arrondissement_nom: string;
  nombre_vehicules?: number;
  nombre_terrains?: number;
  nombre_batiments?: number;
  [key: string]: unknown;
}

export interface GeoDepartementNode {
  departement_id: number;
  departement_nom: string;
  nombre_vehicules?: number;
  nombre_terrains?: number;
  nombre_batiments?: number;
  arrondissements: GeoArrondissementNode[];
  [key: string]: unknown;
}

export interface GeoNode {
  region_id: number;
  region_nom: string;
  nombre_vehicules?: number;
  nombre_terrains?: number;
  nombre_batiments?: number;
  departements: GeoDepartementNode[];
  [key: string]: unknown;
}

export interface FinancementItem {
  source_financement: string;
  nombre: number;
}

export interface VehiculeSousTypeNode {
  sous_type_id: number;
  sous_type_nom: string;
  nombre: number;
}

export interface VehiculeTypeItem {
  type_id: number;
  type_nom: string;
  nombre: number;
  sous_types: VehiculeSousTypeNode[];
}

export interface VehiculesAnciennete {
  age_moyen_annees: number | null;
  total_vehicules: number;
  vehicules_avec_date_connue: number;
  repartition_par_tranche: {
    tranche: "0-2 ans" | "3-5 ans" | "6-10 ans" | "plus de 10 ans";
    nombre: number;
  }[];
}

export interface CroisementEtatDepartementItem {
  departement_id: number;
  departement_nom: string;
  etats: {
    etat: string;
    nombre: number;
  }[];
}

export interface VehiculesClassementRegionItem {
  region_id: number;
  region_nom: string;
  nombre_vehicules: number;
}

export interface VehiculesDetenteurResponse {
  detenteur: {
    id: number;
    nom: string;
    matricule: string | null;
    fonction: string | null;
  };
  nombre_vehicules: number;
  vehicules: {
    id: number;
    reference: string;
    nom: string;
    numeroSerie: string | null;
    statut: string;
  }[];
}

// ─── 4. Section 5.3 : Terrains ──────────────────────────────────────────────

export interface TerrainsVueGlobale {
  total_terrains: number;
  valeur: {
    valeur_totale: number;
    total_terrains: number;
    terrains_avec_valeur_renseignee: number;
    terrains_sans_valeur: number;
  };
  repartition_bati: {
    statut: "Bâti" | "Non bâti" | "Aucune information";
    nombre: number;
    pourcentage: number;
  }[];
  securisation: {
    juridique_seulement: number;
    physique_seulement: number;
    les_deux: number;
    aucun: number;
    total_terrains: number;
  };
  occupation: {
    occupation: "Régulière" | "Irrégulière" | "Aucune information";
    nombre: number;
    pourcentage: number;
  }[];
  titre_foncier: {
    nombre_sans_titre: number;
    nombre_avec_titre: number;
    total_terrains: number;
    pourcentage_sans_titre: number;
  };
  terrains_loues: {
    nombre: number;
  };
}

export interface TerrainsEnLitigeResponse {
  nombre: number;
  terrains: {
    id: number;
    reference: string;
    nom: string;
  }[];
}

export interface AcquisitionsParAnneeItem {
  annee: string;
  nombre: number;
}

export interface CroisementBatiDepartementItem {
  departement_id: number;
  departement_nom: string;
  bati: number;
  non_bati: number;
  aucune_information: number;
}

export interface CroisementOccupationDepartementItem {
  departement_id: number;
  departement_nom: string;
  reguliere: number;
  irreguliere: number;
  aucune_information: number;
}

export interface TerrainsClassementRegionItem {
  region_id: number;
  region_nom: string;
  nombre_terrains: number;
}

// ─── 5. Section 5.4 : Bâtiments ────────────────────────────────────────────

export interface BatimentsVueGlobale {
  total_batiments: number;
  repartition_par_etat: {
    etat: string;
    nombre: number;
    pourcentage: number;
  }[];
  batiments_a_refectionner: {
    total: number;
    par_region: RegionCountNode[];
  };
  occupation: {
    occupation:
      | "Régulière"
      | "Irrégulière"
      | "Cohabitation"
      | "Inoccupé"
      | "Aucune information";
    nombre: number;
    pourcentage: number;
  }[];
  titre_foncier: {
    nombre_sans_titre: number;
    nombre_avec_titre: number;
    total_batiments: number;
    pourcentage_sans_titre: number;
  };
  batiments_loues: {
    nombre: number;
  };
}

export interface BatimentsFinancementResponse {
  par_source?: { source_financement: string; nombre: number }[];
  par_projet?: { projet_id: number; projet_nom: string; nombre: number }[];
  [key: string]: unknown;
}

export interface BatimentsEnLitigeResponse {
  nombre: number;
  batiments: {
    id: number;
    reference: string;
    nom: string;
  }[];
}

export interface BatimentsClassementRegionItem {
  region_id: number;
  region_nom: string;
  nombre_batiments: number;
}

// ─── 6. Section 5.5 : Structures (Service) ──────────────────────────────────

// Structures — types réels confirmés le 2026-08-24 sur /api/stats/structures/vue-globale
export interface StructuresTotalClassification {
  classification: string;
  nombre: number;
}
export interface StructuresTotalItem {
  total: number;
  par_classification: StructuresTotalClassification[];
}
export interface StructuresTypeServiceItem {
  type_service: string;
  nombre: number;
}
export interface StructuresAvecBatiment {
  total_structures: number;
  avec_batiment: number;
  sans_batiment: number;
}
export interface StructuresARefectionner {
  nombre: number;
  total_structures: number;
}
export interface StructuresValeurInfrastructures {
  valeur_totale: number;
  batiments_avec_valeur_renseignee: number;
}
export interface StructuresPlanDisponible {
  total_structures: number;
  avec_plan: number;
  construites_selon_plan_type_officiel: number;
}
export interface StructuresDevisDisponible {
  avec_devis: number;
  total_structures: number;
}

export interface StructuresVueGlobale {
  total_structures: StructuresTotalItem;
  repartition_par_type_service: StructuresTypeServiceItem[];
  avec_batiment: StructuresAvecBatiment;
  a_refectionner: StructuresARefectionner;
  valeur_infrastructures: StructuresValeurInfrastructures;
  plan_disponible: StructuresPlanDisponible;
  devis_disponible: StructuresDevisDisponible;
}

export interface StructuresCoutRefectionRegionItem {
  region_id?: number;
  region_nom: string;
  cout_total?: number;
  cout_estime?: number;
  nombre_structures?: number;
  nombre?: number;
}

export interface StructuresCoutRefectionResponse {
  cout_total?: number;
  nombre_batiments_concernes?: number;
  par_region?: StructuresCoutRefectionRegionItem[];
}

// ─── 7. Section 5.6 : Suivi, Évolution et Alertes ───────────────────────────

export interface SuiviEvolutionPluriannuelleItem {
  region_id: number;
  region_nom: string;
  categorie_id: number;
  categorie_nom: string;
  series: {
    annee: string;
    nombre_biens: number;
  }[];
}

export interface SuiviClassementAnnuelItem {
  categorie_id: number;
  categorie_nom: string;
  annee: number;
  regions: {
    region_id: number;
    region_nom: string;
    nombre_biens: number;
    rang: number;
  }[];
}

export interface DepartementSansDeclarationItem {
  departement_id: number;
  departement_nom: string;
  region_id: number;
  region_nom: string;
}

export interface SuiviCollecteDonneesResponse {
  nouveaux_biens_par_semaine: {
    semaine: string; // ex: "2026-W33"
    nombre: number;
  }[];
  biens_mis_a_jour_par_semaine: {
    semaine: string;
    nombre: number;
  }[];
}

export interface PointAttentionPrioritaireItem {
  id: number;
  site: string;
  region_nom: string | null;
  departement_nom: string | null;
  arrondissement_nom: string | null;
  traitement:
    | "Sécurisé (juridique + physique)"
    | "Sécurisation juridique uniquement"
    | "Sécurisation physique uniquement"
    | "Non traité";
}
