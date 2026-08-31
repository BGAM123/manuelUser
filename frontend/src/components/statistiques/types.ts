export type StatTab =
  | "global"
  | "vehicules"
  | "terrains"
  | "batiments"
  | "informatique"
  | "structures"
  | "suivi";

export interface FilterState {
  // ── Champs d'affichage UI (pilotent les selects) ────────────────────────
  period: string;
  startDate?: string;
  endDate?: string;
  /** Slug texte affiché dans le select organigramme (ex: "del_centre") */
  organigramme: string;
  /** Slug texte affiché dans le select catégorie (ex: "vehicules") */
  category: string;
  /** Slug texte affiché dans le select type de bien (ex: "pickup") */
  assetType: string;
  /** Slug texte affiché dans le select projet (ex: "aqua2") */
  project: string;
  /** Nom de la région affiché (ex: "Centre") */
  region: string;
  /** Nom du département affiché (ex: "Mfoundi") */
  departement: string;
  /** Nom de l'arrondissement affiché */
  arrondissement: string;
  /** Labels des états sélectionnés (ex: ["bon", "panne"]) */
  etatBien: string[];
  /** Statut de gestion (ex: "actif" | "maintenance" | "sorti" | "all") */
  statutGestion: string;
  hasTitreFoncier?: string;
  hasCarteGrise?: string;
  enLitigeOnly: boolean;
  searchHolder?: string;

  // ── Résolutions numériques pour l'API (undefined = "all" = non envoyé) ──
  /** ID numérique du service/structure pour organigramme_service_id (legacy — reconstruit depuis organigrammeServiceIds) */
  organigrammeServiceId?: number | null;
  /** ID numérique de la région pour region_ids */
  regionId?: number | null;
  /** ID numérique du département pour departement_ids */
  departementId?: number | null;
  /** ID numérique de l'arrondissement pour arrondissement_ids */
  arrondissementId?: number | null;
  /** ID numérique de la catégorie pour categorie_id */
  categorieId?: number | null;
  /** ID numérique du type de bien pour type_bien_ids */
  assetTypeId?: number | null;
  /** ID numérique du projet pour projet_ids */
  projectId?: number | null;
  /** IDs numériques des états de bien pour etat_bien_ids */
  etatBienIds?: number[];

  // ── Multi-sélection (catégorie, région, source de financement) ──────────
  // categorieId/assetTypeId/regionId/departementId/arrondissementId/projectId
  // ci-dessus restent utilisés pour la reconstruction d'état legacy le temps
  // de la migration ; ces tableaux sont la source de vérité pour ces 3 filtres
  // (demande explicite de multi-sélection, 2026-08-27).
  categorieIds: number[];
  assetTypeIds: number[];
  regionIds: number[];
  departementIds: number[];
  arrondissementIds: number[];
  projectIds: number[];
  /** Structure / Service — multi-sélection (demande explicite, 2026-08-29). Source de vérité (services_id accepte une liste côté API). */
  organigrammeServiceIds: number[];
  /** Exercice — année en cours par défaut, navigation par YearStepper (demande explicite, 2026-08-29). Restreint TOUS les KPIs aux biens acquis cette année. */
  exercice: number;
}

export interface WidgetVisibility {
  kpiCards: boolean;
  catPie: boolean;
  geoMap: boolean;
  svcPie: boolean;
  top10: boolean;
  top5: boolean;
  top6v: boolean;
  valeur: boolean;
  maint: boolean;
  mouv: boolean;
  aEtat: boolean;
  aInfo: boolean;
  evol: boolean;
  classmt: boolean;
  reformer: boolean;
}
