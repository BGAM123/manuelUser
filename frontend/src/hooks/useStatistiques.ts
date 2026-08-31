/**
 * Hooks React Query personnalisés pour le module Statistiques MINEPIA.
 * Permet un chargement réactif, une mise en cache automatique et une gestion des erreurs.
 */

import { useQuery, useMutation } from "@tanstack/react-query";
import {
  // ── Patrimoine général ─────────────────────────────────────────────────
  getVueGlobale,
  getRepartitionParStructure,
  getRepartitionParProjet,
  getRepartitionParRegion,
  getEvolutionMensuelle,
  getRepartitionParCategorie,
  getEvolutionGap,
  getClassementRegions,
  // ── Véhicules ─────────────────────────────────────────────────────────
  getVehiculesVueGlobale,
  getVehiculesRepartitionGeographique,
  getVehiculesRepartitionFinancement,
  getVehiculesRepartitionType,
  getVehiculesAnciennete,
  getVehiculesCroisementEtatDepartement,
  getVehiculesClassementRegions,
  getVehiculesDetenteur,
  // ── Terrains ───────────────────────────────────────────────────────────
  getTerrainsVueGlobale,
  getTerrainsRepartitionGeographique,
  getTerrainsEnLitige,
  getTerrainsAcquisitionsParAnnee,
  getTerrainsCroisementBatiDepartement,
  getTerrainsCroisementOccupationDepartement,
  getTerrainsClassementRegions,
  // ── Bâtiments ──────────────────────────────────────────────────────────
  getBatimentsVueGlobale,
  getBatimentsRepartitionGeographique,
  getBatimentsRepartitionFinancement,
  getBatimentsEnLitige,
  getBatimentsAnneeConstruction,
  getBatimentsAnneeRefection,
  getBatimentsCroisementEtatDepartement,
  getBatimentsClassementRegions,
  // ── Structures ─────────────────────────────────────────────────────────
  getStructuresVueGlobale,
  getStructuresRepartitionGeographique,
  getStructuresCoutRefection,
  getStructuresAnneeConstruction,
  // ── Suivi & Alertes ────────────────────────────────────────────────────
  getSuiviEvolutionPluriannuelle,
  getSuiviClassementAnnuelRegions,
  getSuiviDepartementsSansDeclaration,
  getSuiviCollecteDonnees,
  getSuiviPointsAttentionPrioritaires,
  // ── Export ─────────────────────────────────────────────────────────────
  exportStatistiques,
  type StatisticsFilter,
} from "@/api/statistiques";
import { toast } from "sonner";

// ─── Clés de cache React Query ─────────────────────────────────────────────

export const STATS_QUERY_KEYS = {
  all: ["stats"] as const,
  vueGlobale: (filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "vue-globale", filter] as const,
  structures: (critere: string, filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "structures", critere, filter] as const,
  projets: (filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "projets", filter] as const,
  regions: (filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "regions", filter] as const,
  categorie: (filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "categorie", filter] as const,
  evolution: (type: string, filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "evolution", type, filter] as const,
  gap: (filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "gap", filter] as const,
  classement: (filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "classement", filter] as const,
  vehicules: (filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "vehicules", filter] as const,
  terrains: (filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "terrains", filter] as const,
  batiments: (filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "batiments", filter] as const,
  structuresOrg: (filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "structures-org", filter] as const,
  suivi: (filter?: StatisticsFilter) =>
    [...STATS_QUERY_KEYS.all, "suivi", filter] as const,
};

// ═══════════════════════════════════════════════════════════════════════════
// 1. PATRIMOINE GÉNÉRAL
// ═══════════════════════════════════════════════════════════════════════════

export function useVueGlobale(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: STATS_QUERY_KEYS.vueGlobale(filter),
    queryFn: () => getVueGlobale(filter),
    staleTime: 5 * 60 * 1000,
    retry: 1,
  });
}

/** Top 10 structures (critere=nombre) ou Top 6 (critere=valeur). */
export function useRepartitionStructures(
  critere: "nombre" | "valeur" = "nombre",
  filter?: StatisticsFilter,
) {
  return useQuery({
    queryKey: STATS_QUERY_KEYS.structures(critere, filter),
    queryFn: () => getRepartitionParStructure(critere, filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Top 5 projets / bailleurs. */
export function useRepartitionProjets(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: STATS_QUERY_KEYS.projets(filter),
    queryFn: () => getRepartitionParProjet(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Répartition de tous les biens par région avec valeur. */
export function useRepartitionRegions(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: STATS_QUERY_KEYS.regions(filter),
    queryFn: () => getRepartitionParRegion(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Répartition par grande catégorie (Véhicules, Terrains, Bâtiments, IT, Mobilier). */
export function useRepartitionCategorie(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: STATS_QUERY_KEYS.categorie(filter),
    queryFn: () => getRepartitionParCategorie(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Évolution mensuelle — type: patrimoine | maintenance | mouvements. */
export function useEvolutionMensuelle(
  type: "patrimoine" | "maintenance" | "mouvements" = "patrimoine",
  filter?: StatisticsFilter,
) {
  return useQuery({
    queryKey: STATS_QUERY_KEYS.evolution(type, filter),
    queryFn: () => getEvolutionMensuelle(type, filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** GAP d'évolution entre deux exercices budgétaires. */
export function useEvolutionGap(
  anneeDebut?: number,
  anneeFin?: number,
  filter?: StatisticsFilter,
) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.gap(filter), anneeDebut, anneeFin],
    queryFn: () => getEvolutionGap(anneeDebut, anneeFin, filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Classement des régions pour chaque catégorie. */
export function useClassementRegions(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: STATS_QUERY_KEYS.classement(filter),
    queryFn: () => getClassementRegions(filter),
    staleTime: 5 * 60 * 1000,
  });
}

// ═══════════════════════════════════════════════════════════════════════════
// 2. MATÉRIEL ROULANT (VÉHICULES)
// ═══════════════════════════════════════════════════════════════════════════

/** Synthèse générale du parc automobile. */
export function useVehiculesVueGlobale(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: STATS_QUERY_KEYS.vehicules(filter),
    queryFn: () => getVehiculesVueGlobale(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Arbre hiérarchique Région → Département → Arrondissement des véhicules. */
export function useVehiculesRepartitionGeographique(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.vehicules(filter), "geo"],
    queryFn: () => getVehiculesRepartitionGeographique(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Répartition des véhicules par source de financement. */
export function useVehiculesRepartitionFinancement(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.vehicules(filter), "financement"],
    queryFn: () => getVehiculesRepartitionFinancement(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Répartition par type de véhicule et sous-types. */
export function useVehiculesRepartitionType(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.vehicules(filter), "type"],
    queryFn: () => getVehiculesRepartitionType(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Âge moyen et pyramide d'ancienneté du parc automobile. */
export function useVehiculesAnciennete(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.vehicules(filter), "anciennete"],
    queryFn: () => getVehiculesAnciennete(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Tableau croisé état × département pour les véhicules. */
export function useVehiculesCroisementEtatDept(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.vehicules(filter), "croisement-etat-dept"],
    queryFn: () => getVehiculesCroisementEtatDepartement(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Classement des régions par volume de véhicules. */
export function useVehiculesClassementRegions(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.vehicules(filter), "classement-regions"],
    queryFn: () => getVehiculesClassementRegions(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/**
 * Véhicules affectés à un agent spécifique.
 * @param params matricule ou user_id — au moins un requis pour déclencher la requête.
 */
export function useVehiculesDetenteur(
  params: { matricule?: string; user_id?: number },
  filter?: StatisticsFilter,
) {
  const enabled = Boolean(params.matricule || params.user_id);
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.vehicules(filter), "detenteur", params],
    queryFn: () => getVehiculesDetenteur(params, filter),
    enabled,
    staleTime: 5 * 60 * 1000,
    retry: 0, // 404 si aucun agent trouvé — ne pas réessayer
  });
}

// ═══════════════════════════════════════════════════════════════════════════
// 3. TERRAINS (DOMAINE FONCIER)
// ═══════════════════════════════════════════════════════════════════════════

/** Synthèse générale du patrimoine foncier. */
export function useTerrainsVueGlobale(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: STATS_QUERY_KEYS.terrains(filter),
    queryFn: () => getTerrainsVueGlobale(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Arbre hiérarchique Région → Département → Arrondissement des terrains. */
export function useTerrainsRepartitionGeographique(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.terrains(filter), "geo"],
    queryFn: () => getTerrainsRepartitionGeographique(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Parcelles foncières en situation de litige. */
export function useTerrainsLitiges(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.terrains(filter), "en-litige"],
    queryFn: () => getTerrainsEnLitige(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Historique des acquisitions foncières par année. */
export function useTerrainsAcquisitionsParAnnee(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.terrains(filter), "acquisitions-annee"],
    queryFn: () => getTerrainsAcquisitionsParAnnee(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Tableau croisé Bâti / Non bâti / Sans info par département. */
export function useTerrainsCroisementBati(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.terrains(filter), "croisement-bati"],
    queryFn: () => getTerrainsCroisementBatiDepartement(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Tableau croisé Occupation (Régulière / Irrégulière / Sans info) par département. */
export function useTerrainsCroisementOccupation(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.terrains(filter), "croisement-occupation"],
    queryFn: () => getTerrainsCroisementOccupationDepartement(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Classement des régions selon le nombre de terrains. */
export function useTerrainsClassementRegions(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.terrains(filter), "classement-regions"],
    queryFn: () => getTerrainsClassementRegions(filter),
    staleTime: 5 * 60 * 1000,
  });
}

// ═══════════════════════════════════════════════════════════════════════════
// 4. BÂTIMENTS (PATRIMOINE BÂTI)
// ═══════════════════════════════════════════════════════════════════════════

/** Synthèse générale du parc immobilier. */
export function useBatimentsVueGlobale(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: STATS_QUERY_KEYS.batiments(filter),
    queryFn: () => getBatimentsVueGlobale(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Arbre hiérarchique Région → Département → Arrondissement des bâtiments. */
export function useBatimentsRepartitionGeographique(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.batiments(filter), "geo"],
    queryFn: () => getBatimentsRepartitionGeographique(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Ventilation des investissements bâtiments par source / projet. */
export function useBatimentsRepartitionFinancement(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.batiments(filter), "financement"],
    queryFn: () => getBatimentsRepartitionFinancement(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Édifices en situation de litige. */
export function useBatimentsEnLitige(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.batiments(filter), "en-litige"],
    queryFn: () => getBatimentsEnLitige(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Répartition des édifices par année de construction. */
export function useBatimentsAnneeConstruction(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.batiments(filter), "annee-construction"],
    queryFn: () => getBatimentsAnneeConstruction(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Répartition des bâtiments par année de dernière réfection. */
export function useBatimentsAnneeRefection(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.batiments(filter), "annee-refection"],
    queryFn: () => getBatimentsAnneeRefection(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Tableau croisé état physique × département pour les bâtiments. */
export function useBatimentsCroisementEtatDept(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.batiments(filter), "croisement-etat-dept"],
    queryFn: () => getBatimentsCroisementEtatDepartement(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Classement des régions selon le parc immobilier. */
export function useBatimentsClassementRegions(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.batiments(filter), "classement-regions"],
    queryFn: () => getBatimentsClassementRegions(filter),
    staleTime: 5 * 60 * 1000,
  });
}

// ═══════════════════════════════════════════════════════════════════════════
// 5. STRUCTURES (ORGANISATION / SERVICES)
// ═══════════════════════════════════════════════════════════════════════════

/** Synthèse des structures (bâtiments, plans types, devis, réfections). */
export function useStructuresVueGlobale(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: STATS_QUERY_KEYS.structuresOrg(filter),
    queryFn: () => getStructuresVueGlobale(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Répartition géographique des services du MINEPIA. */
export function useStructuresRepartitionGeographique(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.structuresOrg(filter), "geo"],
    queryFn: () => getStructuresRepartitionGeographique(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Estimation budgétaire des coûts de réfection par région. */
export function useStructuresCoutRefection(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.structuresOrg(filter), "cout-refection"],
    queryFn: () => getStructuresCoutRefection(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Historique des années de construction des structures. */
export function useStructuresAnneeConstruction(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.structuresOrg(filter), "annee-construction"],
    queryFn: () => getStructuresAnneeConstruction(filter),
    staleTime: 5 * 60 * 1000,
  });
}

// ═══════════════════════════════════════════════════════════════════════════
// 6. SUIVI, ÉVOLUTION ET ALERTES
// ═══════════════════════════════════════════════════════════════════════════

/** Sites et emprises prioritaires à sécuriser. */
export function useSuiviAlertes(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: STATS_QUERY_KEYS.suivi(filter),
    queryFn: () => getSuiviPointsAttentionPrioritaires(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Séries chronologiques pluriannuelles par catégorie et région. */
export function useSuiviEvolutionPluriannuelle(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.suivi(filter), "evolution-pluriannuelle"],
    queryFn: () => getSuiviEvolutionPluriannuelle(filter),
    staleTime: 5 * 60 * 1000,
  });
}

/** Classement annuel des régions pour un exercice donné. */
export function useSuiviClassementAnnuel(
  annee?: number,
  filter?: StatisticsFilter,
) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.suivi(filter), "classement-annuel", annee],
    queryFn: () => getSuiviClassementAnnuelRegions(annee, filter),
    staleTime: 5 * 60 * 1000,
  });
}

/**
 * Départements n'ayant transmis aucun bien pour une catégorie donnée.
 * @param categorieId Requis par le backend — la requête ne part pas si absent.
 */
export function useSuiviDeptsSansDeclaration(
  categorieId?: number,
  filter?: StatisticsFilter,
) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.suivi(filter), "depts-sans-declaration", categorieId],
    queryFn: () => getSuiviDepartementsSansDeclaration(categorieId!, filter),
    enabled: categorieId !== undefined && categorieId > 0,
    staleTime: 5 * 60 * 1000,
  });
}

/**
 * Suivi hebdomadaire de la cadence de collecte des données.
 * @param depuis Date de début au format YYYY-MM-DD (défaut : 26 dernières semaines).
 */
export function useSuiviCollecteDonnees(
  depuis?: string,
  filter?: StatisticsFilter,
) {
  return useQuery({
    queryKey: [...STATS_QUERY_KEYS.suivi(filter), "collecte-donnees", depuis],
    queryFn: () => getSuiviCollecteDonnees(depuis, filter),
    staleTime: 5 * 60 * 1000,
  });
}

// ═══════════════════════════════════════════════════════════════════════════
// 7. EXPORT (MUTATION)
// ═══════════════════════════════════════════════════════════════════════════

export function useExportStatistiques() {
  return useMutation({
    mutationFn: async ({
      endpoint,
      filter,
      format,
      filename,
    }: {
      endpoint: string;
      filter?: StatisticsFilter;
      format: "xlsx" | "pdf";
      filename?: string;
    }) => {
      await exportStatistiques(endpoint, filter, format, filename);
    },
    onSuccess: (_, variables) => {
      toast.success(
        `Export ${variables.format.toUpperCase()} téléchargé avec succès.`,
      );
    },
    onError: (err) => {
      toast.error(
        "Échec du téléchargement de l'export. Veuillez vérifier la connexion au serveur.",
      );
      console.error("Erreur téléchargement export:", err);
    },
  });
}

// ═══════════════════════════════════════════════════════════════════════════
// HOOK CENTRALISÉ — useDashboardStats
// Un seul appel vers /api/stats/dashboard remplace tous les hooks individuels.
// Les sous-hooks ci-dessus sont conservés pour compatibilité et usage ponctuel.
// ═══════════════════════════════════════════════════════════════════════════

import { getDashboardStats } from "@/api/statistiques";
import type { DashboardStats } from "@/api/statistiques";

/**
 * Hook principal du module statistiques.
 * Charge tout le payload dashboard en un seul appel réseau.
 *
 * Usage :
 *   const { data, isLoading, error } = useDashboardStats(apiFilter);
 *   const totalBiens = data?.patrimoine.vue_globale.total_biens ?? 0;
 *   const vehicules  = data?.vehicules.vue_globale;
 *   const batiments  = data?.batiments.vue_globale;
 *   const structures = data?.structures.vue_globale;
 *   const suivi      = data?.suivi;
 */
export function useDashboardStats(filter?: StatisticsFilter) {
  return useQuery({
    queryKey: ["stats", "dashboard", filter],
    queryFn: () => getDashboardStats(filter ?? {}),
    staleTime: 5 * 60 * 1000, // 5 minutes
    retry: 1,
    select: (data): DashboardStats => data,
  });
}
