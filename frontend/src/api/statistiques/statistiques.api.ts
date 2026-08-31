/**
 * Service API complet pour le module Statistiques MINEPIA.
 * Implémente l'ensemble des 40 endpoints GET Symfony (/api/stats/*).
 *
 * Utilise l'instance Axios configurée avec :
 * - Injection automatique du token Bearer
 * - Refresh token préventif & réactif sans collisions
 * - Gestion du flux 2FA
 * - Téléchargement des exports binaires XLSX / PDF
 */

import api from "@/api/axios";
import type { ApiResponse } from "@/api/types";
import type {
  StatisticsFilter,
  VueGlobale,
  RepartitionStructureNombreItem,
  RepartitionStructureValeurItem,
  RepartitionProjetItem,
  RepartitionRegionItem,
  EvolutionMensuellePatrimoineItem,
  EvolutionMensuelleCompteItem,
  RepartitionCategorieItem,
  EvolutionGapItem,
  ClassementRegionsItem,
  VehiculesVueGlobale,
  GeoNode,
  FinancementItem,
  VehiculeTypeItem,
  VehiculesAnciennete,
  CroisementEtatDepartementItem,
  VehiculesClassementRegionItem,
  VehiculesDetenteurResponse,
  TerrainsVueGlobale,
  TerrainsEnLitigeResponse,
  AcquisitionsParAnneeItem,
  CroisementBatiDepartementItem,
  CroisementOccupationDepartementItem,
  TerrainsClassementRegionItem,
  BatimentsVueGlobale,
  BatimentsFinancementResponse,
  BatimentsEnLitigeResponse,
  BatimentsClassementRegionItem,
  StructuresVueGlobale,
  StructuresCoutRefectionResponse,
  SuiviEvolutionPluriannuelleItem,
  SuiviClassementAnnuelItem,
  DepartementSansDeclarationItem,
  SuiviCollecteDonneesResponse,
  PointAttentionPrioritaireItem,
} from "./statistiques.types";
import {
  toQueryString,
  downloadStatsExport,
  sanitizeDateYYYYMMDD,
} from "./statistiques.helpers";

// ─── Helper Interne pour Requêtes GET Statistiques ─────────────────────────

async function getStats<T>(path: string, filter: StatisticsFilter = {}): Promise<T> {
  const qs = toQueryString(filter);
  const separator = path.includes("?") ? "&" : "?";
  const url = qs ? `${path}${separator}${qs}` : path;

  const response = await api.get<ApiResponse<T>>(url);
  return response.data.data;
}

// ═══════════════════════════════════════════════════════════════════════════
// 5.1 PATRIMOINE GÉNÉRAL
// ═══════════════════════════════════════════════════════════════════════════

/**
 * 1. GET /api/stats/vue-globale
 * Synthèse globale de tout le patrimoine MINEPIA.
 */
export async function getVueGlobale(filter?: StatisticsFilter): Promise<VueGlobale> {
  return getStats<VueGlobale>("/api/stats/vue-globale", filter);
}

/**
 * 2. GET /api/stats/repartition-par-structure
 * Top structures détentrices de biens (par nombre: Top 10, par valeur: Top 6).
 */
export async function getRepartitionParStructure(
  critere: "nombre" | "valeur" = "nombre",
  filter?: StatisticsFilter,
): Promise<RepartitionStructureNombreItem[] | RepartitionStructureValeurItem[]> {
  return getStats<RepartitionStructureNombreItem[] | RepartitionStructureValeurItem[]>(
    `/api/stats/repartition-par-structure?critere=${critere}`,
    filter,
  );
}

/**
 * 3. GET /api/stats/repartition-par-projet
 * Top 5 projets / bailleurs donateurs.
 */
export async function getRepartitionParProjet(
  filter?: StatisticsFilter,
): Promise<RepartitionProjetItem[]> {
  return getStats<RepartitionProjetItem[]>("/api/stats/repartition-par-projet", filter);
}

/**
 * 4. GET /api/stats/repartition-par-region
 * Répartition de tous les biens par région avec valeur patrimoniale.
 */
export async function getRepartitionParRegion(
  filter?: StatisticsFilter,
): Promise<RepartitionRegionItem[]> {
  return getStats<RepartitionRegionItem[]>("/api/stats/repartition-par-region", filter);
}

/**
 * 5. GET /api/stats/evolution-mensuelle
 * Évolution temporelle mois par mois (patrimoine cumulé, maintenance ou mouvements).
 */
export async function getEvolutionMensuelle(
  type: "patrimoine" | "maintenance" | "mouvements" = "patrimoine",
  filter?: StatisticsFilter,
): Promise<EvolutionMensuellePatrimoineItem[] | EvolutionMensuelleCompteItem[]> {
  return getStats<EvolutionMensuellePatrimoineItem[] | EvolutionMensuelleCompteItem[]>(
    `/api/stats/evolution-mensuelle?type=${type}`,
    filter,
  );
}

/**
 * 6. GET /api/stats/repartition-par-categorie
 * Répartition par grande catégorie de biens (Véhicules, Terrains, Bâtiments, IT, Mobilier).
 */
export async function getRepartitionParCategorie(
  filter?: StatisticsFilter,
): Promise<RepartitionCategorieItem[]> {
  return getStats<RepartitionCategorieItem[]>("/api/stats/repartition-par-categorie", filter);
}

/**
 * 7. GET /api/stats/evolution-gap
 * Comparatif d'évolution (GAP) entre deux exercices budgétaires.
 */
export async function getEvolutionGap(
  anneeDebut?: number,
  anneeFin?: number,
  filter?: StatisticsFilter,
): Promise<EvolutionGapItem[]> {
  const mergedFilter: StatisticsFilter = {
    ...filter,
    ...(anneeDebut ? { annee_debut: anneeDebut } : {}),
    ...(anneeFin ? { annee_fin: anneeFin } : {}),
  };
  return getStats<EvolutionGapItem[]>("/api/stats/evolution-gap", mergedFilter);
}

/**
 * 8. GET /api/stats/classement-regions
 * Classement des régions pour chaque catégorie de biens.
 */
export async function getClassementRegions(
  filter?: StatisticsFilter,
): Promise<ClassementRegionsItem[]> {
  return getStats<ClassementRegionsItem[]>("/api/stats/classement-regions", filter);
}

// ═══════════════════════════════════════════════════════════════════════════
// 5.2 MATÉRIEL ROULANT (VÉHICULES)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * 9. GET /api/stats/vehicules/vue-globale
 * Synthèse générale du parc automobile (état, réformes, disparitions, cartes grises, valeur).
 */
export async function getVehiculesVueGlobale(
  filter?: StatisticsFilter,
): Promise<VehiculesVueGlobale> {
  return getStats<VehiculesVueGlobale>("/api/stats/vehicules/vue-globale", filter);
}

/**
 * 10. GET /api/stats/vehicules/repartition-geographique
 * Arbre hiérarchique Région → Département → Arrondissement.
 */
export async function getVehiculesRepartitionGeographique(
  filter?: StatisticsFilter,
): Promise<GeoNode[]> {
  return getStats<GeoNode[]>("/api/stats/vehicules/repartition-geographique", filter);
}

/**
 * 11. GET /api/stats/vehicules/repartition-financement
 * Répartition des véhicules par source de financement (BIP, Don, MINEPIA, etc.).
 */
export async function getVehiculesRepartitionFinancement(
  filter?: StatisticsFilter,
): Promise<FinancementItem[]> {
  return getStats<FinancementItem[]>("/api/stats/vehicules/repartition-financement", filter);
}

/**
 * 12. GET /api/stats/vehicules/repartition-type
 * Répartition par type de véhicule et sous-types (Pick-up, Berline, Moto, etc.).
 */
export async function getVehiculesRepartitionType(
  filter?: StatisticsFilter,
): Promise<VehiculeTypeItem[]> {
  return getStats<VehiculeTypeItem[]>("/api/stats/vehicules/repartition-type", filter);
}

/**
 * 13. GET /api/stats/vehicules/anciennete
 * Âge moyen et pyramide des tranches d'ancienneté du parc automobile.
 */
export async function getVehiculesAnciennete(
  filter?: StatisticsFilter,
): Promise<VehiculesAnciennete> {
  return getStats<VehiculesAnciennete>("/api/stats/vehicules/anciennete", filter);
}

/**
 * 14. GET /api/stats/vehicules/croisement-etat-departement
 * Tableau croisé de l'état de fonctionnement par département.
 */
export async function getVehiculesCroisementEtatDepartement(
  filter?: StatisticsFilter,
): Promise<CroisementEtatDepartementItem[]> {
  return getStats<CroisementEtatDepartementItem[]>(
    "/api/stats/vehicules/croisement-etat-departement",
    filter,
  );
}

/**
 * 15. GET /api/stats/vehicules/classement-regions
 * Classement des régions selon le volume de véhicules.
 */
export async function getVehiculesClassementRegions(
  filter?: StatisticsFilter,
): Promise<VehiculesClassementRegionItem[]> {
  return getStats<VehiculesClassementRegionItem[]>(
    "/api/stats/vehicules/classement-regions",
    filter,
  );
}

/**
 * 16. GET /api/stats/vehicules/detenteur
 * Liste des véhicules affectés à un agent spécifique (via matricule ou user_id).
 */
export async function getVehiculesDetenteur(
  params: { matricule?: string; user_id?: number },
  filter?: StatisticsFilter,
): Promise<VehiculesDetenteurResponse> {
  const queryParams = new URLSearchParams();
  if (params.matricule) queryParams.set("matricule", params.matricule);
  if (params.user_id) queryParams.set("user_id", String(params.user_id));

  const qs = toQueryString(filter);
  const fullQs = [queryParams.toString(), qs].filter(Boolean).join("&");

  const response = await api.get<ApiResponse<VehiculesDetenteurResponse>>(
    `/api/stats/vehicules/detenteur?${fullQs}`,
  );
  return response.data.data;
}

// ═══════════════════════════════════════════════════════════════════════════
// 5.3 TERRAINS (DOMAINE FONCIER)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * 17. GET /api/stats/terrains/vue-globale
 * Synthèse générale du patrimoine foncier.
 */
export async function getTerrainsVueGlobale(
  filter?: StatisticsFilter,
): Promise<TerrainsVueGlobale> {
  return getStats<TerrainsVueGlobale>("/api/stats/terrains/vue-globale", filter);
}

/**
 * 18. GET /api/stats/terrains/repartition-geographique
 * Arbre hiérarchique Région → Département → Arrondissement des terrains.
 */
export async function getTerrainsRepartitionGeographique(
  filter?: StatisticsFilter,
): Promise<GeoNode[]> {
  return getStats<GeoNode[]>("/api/stats/terrains/repartition-geographique", filter);
}

/**
 * 19. GET /api/stats/terrains/en-litige
 * Liste et nombre de parcelles foncières en situation de litige.
 */
export async function getTerrainsEnLitige(
  filter?: StatisticsFilter,
): Promise<TerrainsEnLitigeResponse> {
  return getStats<TerrainsEnLitigeResponse>("/api/stats/terrains/en-litige", filter);
}

/**
 * 20. GET /api/stats/terrains/acquisitions-par-annee
 * Historique des acquisitions foncières par année.
 */
export async function getTerrainsAcquisitionsParAnnee(
  filter?: StatisticsFilter,
): Promise<AcquisitionsParAnneeItem[]> {
  return getStats<AcquisitionsParAnneeItem[]>(
    "/api/stats/terrains/acquisitions-par-annee",
    filter,
  );
}

/**
 * 21. GET /api/stats/terrains/croisement-bati-departement
 * Tableau croisé Bâti / Non bâti / Sans information par département.
 */
export async function getTerrainsCroisementBatiDepartement(
  filter?: StatisticsFilter,
): Promise<CroisementBatiDepartementItem[]> {
  return getStats<CroisementBatiDepartementItem[]>(
    "/api/stats/terrains/croisement-bati-departement",
    filter,
  );
}

/**
 * 22. GET /api/stats/terrains/croisement-occupation-departement
 * Tableau croisé de l'occupation (Régulière, Irrégulière, Sans info) par département.
 */
export async function getTerrainsCroisementOccupationDepartement(
  filter?: StatisticsFilter,
): Promise<CroisementOccupationDepartementItem[]> {
  return getStats<CroisementOccupationDepartementItem[]>(
    "/api/stats/terrains/croisement-occupation-departement",
    filter,
  );
}

/**
 * 23. GET /api/stats/terrains/classement-regions
 * Classement des régions selon le nombre de terrains.
 */
export async function getTerrainsClassementRegions(
  filter?: StatisticsFilter,
): Promise<TerrainsClassementRegionItem[]> {
  return getStats<TerrainsClassementRegionItem[]>(
    "/api/stats/terrains/classement-regions",
    filter,
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// 5.4 BÂTIMENTS (PATRIMOINE BÂTI)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * 24. GET /api/stats/batiments/vue-globale
 * Synthèse générale du parc immobilier et édifices.
 */
export async function getBatimentsVueGlobale(
  filter?: StatisticsFilter,
): Promise<BatimentsVueGlobale> {
  return getStats<BatimentsVueGlobale>("/api/stats/batiments/vue-globale", filter);
}

/**
 * 25. GET /api/stats/batiments/repartition-geographique
 * Arbre hiérarchique Région → Département → Arrondissement des bâtiments.
 */
export async function getBatimentsRepartitionGeographique(
  filter?: StatisticsFilter,
): Promise<GeoNode[]> {
  return getStats<GeoNode[]>("/api/stats/batiments/repartition-geographique", filter);
}

/**
 * 26. GET /api/stats/batiments/repartition-financement
 * Ventilation des investissements par source budgétaire ou projet bailleur.
 */
export async function getBatimentsRepartitionFinancement(
  filter?: StatisticsFilter,
): Promise<BatimentsFinancementResponse> {
  return getStats<BatimentsFinancementResponse>(
    "/api/stats/batiments/repartition-financement",
    filter,
  );
}

/**
 * 27. GET /api/stats/batiments/en-litige
 * Liste et décompte des édifices en situation de litige.
 */
export async function getBatimentsEnLitige(
  filter?: StatisticsFilter,
): Promise<BatimentsEnLitigeResponse> {
  return getStats<BatimentsEnLitigeResponse>("/api/stats/batiments/en-litige", filter);
}

/**
 * 28. GET /api/stats/batiments/annee-construction
 * Répartition des édifices par année d'érection / construction.
 */
export async function getBatimentsAnneeConstruction(
  filter?: StatisticsFilter,
): Promise<AcquisitionsParAnneeItem[]> {
  return getStats<AcquisitionsParAnneeItem[]>(
    "/api/stats/batiments/annee-construction",
    filter,
  );
}

/**
 * 29. GET /api/stats/batiments/annee-refection
 * Répartition des bâtiments par année de dernière réfection majeure.
 */
export async function getBatimentsAnneeRefection(
  filter?: StatisticsFilter,
): Promise<AcquisitionsParAnneeItem[]> {
  return getStats<AcquisitionsParAnneeItem[]>(
    "/api/stats/batiments/annee-refection",
    filter,
  );
}

/**
 * 30. GET /api/stats/batiments/croisement-etat-departement
 * Tableau croisé de l'état physique des bâtiments par département.
 */
export async function getBatimentsCroisementEtatDepartement(
  filter?: StatisticsFilter,
): Promise<CroisementEtatDepartementItem[]> {
  return getStats<CroisementEtatDepartementItem[]>(
    "/api/stats/batiments/croisement-etat-departement",
    filter,
  );
}

/**
 * 31. GET /api/stats/batiments/classement-regions
 * Classement des régions selon le parc immobilier détenu.
 */
export async function getBatimentsClassementRegions(
  filter?: StatisticsFilter,
): Promise<BatimentsClassementRegionItem[]> {
  return getStats<BatimentsClassementRegionItem[]>(
    "/api/stats/batiments/classement-regions",
    filter,
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// 5.5 STRUCTURES (ORGANISATION / SERVICES)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * 32. GET /api/stats/structures/vue-globale
 * Synthèse des structures (bâtiments construits, plans types, devis, réfections).
 */
export async function getStructuresVueGlobale(
  filter?: StatisticsFilter,
): Promise<StructuresVueGlobale> {
  return getStats<StructuresVueGlobale>("/api/stats/structures/vue-globale", filter);
}

/**
 * 33. GET /api/stats/structures/repartition-geographique
 * Répartition géographique des services du MINEPIA.
 */
export async function getStructuresRepartitionGeographique(
  filter?: StatisticsFilter,
): Promise<GeoNode[]> {
  return getStats<GeoNode[]>("/api/stats/structures/repartition-geographique", filter);
}

/**
 * 34. GET /api/stats/structures/cout-refection
 * Estimation budgétaire des coûts de réfection des sièges par région.
 */
export async function getStructuresCoutRefection(
  filter?: StatisticsFilter,
): Promise<StructuresCoutRefectionResponse> {
  return getStats<StructuresCoutRefectionResponse>(
    "/api/stats/structures/cout-refection",
    filter,
  );
}

/**
 * 35. GET /api/stats/structures/annee-construction
 * Historique des années de construction des bâtiments abritant les structures.
 */
export async function getStructuresAnneeConstruction(
  filter?: StatisticsFilter,
): Promise<AcquisitionsParAnneeItem[]> {
  return getStats<AcquisitionsParAnneeItem[]>(
    "/api/stats/structures/annee-construction",
    filter,
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// 5.6 SUIVI, ÉVOLUTION ET ALERTES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * 36. GET /api/stats/suivi/evolution-pluriannuelle
 * Séries chronologiques pluriannuelles par catégorie et par région.
 */
export async function getSuiviEvolutionPluriannuelle(
  filter?: StatisticsFilter,
): Promise<SuiviEvolutionPluriannuelleItem[]> {
  return getStats<SuiviEvolutionPluriannuelleItem[]>(
    "/api/stats/suivi/evolution-pluriannuelle",
    filter,
  );
}

/**
 * 37. GET /api/stats/suivi/classement-annuel-regions
 * Rang de chaque région pour un exercice annuel donné.
 */
export async function getSuiviClassementAnnuelRegions(
  annee?: number,
  filter?: StatisticsFilter,
): Promise<SuiviClassementAnnuelItem[]> {
  const mergedFilter: StatisticsFilter = {
    ...filter,
    ...(annee ? { annee } : {}),
  };
  return getStats<SuiviClassementAnnuelItem[]>(
    "/api/stats/suivi/classement-annuel-regions",
    mergedFilter,
  );
}

/**
 * 38. GET /api/stats/suivi/departements-sans-declaration
 * Liste des divisions départementales n'ayant transmis aucun bien pour une catégorie.
 * @param categorieId ID numérique de la catégorie (requis par le backend)
 */
export async function getSuiviDepartementsSansDeclaration(
  categorieId: number,
  filter?: StatisticsFilter,
): Promise<DepartementSansDeclarationItem[]> {
  // Cet endpoint exige ?categorie_id= (singulier) — paramètre spécifique à cet endpoint
  const qs = toQueryString(filter ?? {});
  const sep = qs ? "&" : "?";
  const url = `/api/stats/suivi/departements-sans-declaration?categorie_id=${categorieId}${sep}${qs}`;
  const response = await api.get<ApiResponse<DepartementSansDeclarationItem[]>>(url.replace(/\?$/, "").replace(/&$/, ""));
  return response.data.data;
}

/**
 * 39. GET /api/stats/suivi/collecte-donnees
 * Suivi hebdomadaire de la cadence de consolidation et mise à jour du patrimoine.
 * @param depuis Date de début (validée automatiquement au format YYYY-MM-DD)
 */
export async function getSuiviCollecteDonnees(
  depuis?: string | Date,
  filter?: StatisticsFilter,
): Promise<SuiviCollecteDonneesResponse> {
  const formattedDepuis = sanitizeDateYYYYMMDD(depuis);
  const mergedFilter: StatisticsFilter = {
    ...filter,
    ...(formattedDepuis ? { depuis: formattedDepuis } : {}),
  };
  return getStats<SuiviCollecteDonneesResponse>(
    "/api/stats/suivi/collecte-donnees",
    mergedFilter,
  );
}

/**
 * 40. GET /api/stats/suivi/points-attention-prioritaires
 * Matrice opérationnelle des sites et emprises prioritaires à sécuriser.
 */
export async function getSuiviPointsAttentionPrioritaires(
  filter?: StatisticsFilter,
): Promise<PointAttentionPrioritaireItem[]> {
  return getStats<PointAttentionPrioritaireItem[]>(
    "/api/stats/suivi/points-attention-prioritaires",
    filter,
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// 6. EXPORT UNIVERSEL (TÉLÉCHARGEMENT DIRECT XLSX / PDF)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Télécharge un export statistique binaire officiel pour n'importe lequel des 40 endpoints.
 *
 * @param endpoint Chemin de l'endpoint (ex: '/api/stats/vue-globale')
 * @param filter Filtres actifs à appliquer
 * @param format Format du fichier : 'xlsx' | 'pdf'
 * @param defaultFilename Nom de fichier personnalisé optionnel
 */
export async function exportStatistiques(
  endpoint: string,
  filter: StatisticsFilter = {},
  format: "xlsx" | "pdf" = "xlsx",
  defaultFilename?: string,
): Promise<void> {
  return downloadStatsExport(endpoint, filter, format, defaultFilename);
}

// ═══════════════════════════════════════════════════════════════════════════
// ENDPOINT CENTRALISÉ — /api/stats/dashboard (payload unique)
// Remplace les 40 appels individuels par un seul appel.
// ═══════════════════════════════════════════════════════════════════════════

import type { DashboardStats } from "./dashboard.types";

/**
 * GET /api/stats/dashboard
 * Retourne toutes les statistiques en un seul payload structuré.
 * Accepte les mêmes filtres que les endpoints individuels.
 */
export async function getDashboardStats(
  filter: StatisticsFilter = {},
): Promise<DashboardStats> {
  return getStats<DashboardStats>("/api/stats/dashboard", filter);
}
