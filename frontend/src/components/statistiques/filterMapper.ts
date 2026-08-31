/**
 * Mappeur entre l'état des filtres de l'interface (FilterState)
 * et le format attendu par les paramètres de requêtes API Symfony (StatisticsFilter).
 *
 * Règles :
 *  - Les champs *Id (number | null | undefined) sont mappés directement — pas de résolution nom→ID ici.
 *  - Les champs string d'affichage (region, category, etc.) ne sont PAS envoyés à l'API ;
 *    ce sont les *Id correspondants qui portent l'information pour le backend.
 *  - Un champ à null, undefined ou "all" n'est pas inclus dans le filtre API.
 */

import type { FilterState } from "./types";
import type { StatisticsFilter } from "@/api/statistiques";

export function mapFilterStateToApiFilter(filterState: FilterState): StatisticsFilter {
  const apiFilter: StatisticsFilter = {};

  // ── 1. Période ─────────────────────────────────────────────────────────
  // N'envoyer les années QUE si l'utilisateur a explicitement choisi une plage.
  // period vide ou "all" = pas de filtre → le backend retourne toutes les années.
  if (filterState.period && filterState.period.trim() !== "" && filterState.period !== "all") {
    const matchYears = filterState.period.match(/\b(19\d\d|20\d\d)\b/g);
    if (matchYears && matchYears.length >= 1) {
      const parsedYears = matchYears.map(Number).sort((a, b) => a - b);
      apiFilter.annee_debut = parsedYears[0];
      apiFilter.annee_fin = parsedYears[parsedYears.length - 1];
    }
  }

  // Dates ISO optionnelles (startDate / endDate) si présentes
  if (filterState.startDate) apiFilter.date_debut = filterState.startDate;
  if (filterState.endDate) apiFilter.date_fin = filterState.endDate;

  // ── 2. Statut de gestion ────────────────────────────────────────────────
  // Correction du bug : les valeurs mock sont "actif", "maintenance", "sorti"
  // mais l'API attend "ACTIF", "EN_MAINTENANCE", "SORTIS"
  if (filterState.statutGestion && filterState.statutGestion !== "all") {
    const statutMap: Record<string, "ACTIF" | "EN_MAINTENANCE" | "SORTIS"> = {
      actif: "ACTIF",
      active: "ACTIF",
      actif_service: "ACTIF",
      maintenance: "EN_MAINTENANCE",
      en_maintenance: "EN_MAINTENANCE",
      sorti: "SORTIS",
      sortis: "SORTIS",
      sorti_patrimoine: "SORTIS",
    };
    const normalizedKey = filterState.statutGestion.toLowerCase();
    const mapped = statutMap[normalizedKey];
    if (mapped) {
      apiFilter.statuts = [mapped];
    }
  }

  // ── 3. Organigramme / Service (multi-sélection) ─────────────────────────
  // Paramètre API réel : `services_id`, accepte une liste (vérifié sur le Swagger de
  // /api/stats/dashboard, 2026-08-28 puis 2026-08-29 pour le multi). L'ancien nom
  // `organigramme_service_id` n'est pas reconnu par le backend et était silencieusement
  // ignoré (filtre inopérant). Pour un non-admin, le backend applique de toute façon une
  // barrière de sécurité (son service + descendants, cf. StatisticsService::buildSecuredFilters) —
  // ce filtre ne peut que RESTREINDRE davantage ce périmètre, jamais l'élargir.
  if (filterState.organigrammeServiceIds.length > 0) {
    apiFilter.services_id = filterState.organigrammeServiceIds;
  } else if (filterState.organigrammeServiceId) {
    apiFilter.services_id = [filterState.organigrammeServiceId];
  }

  // ── 4-6. Région / Département / Arrondissement (multi-sélection) ───────
  if (filterState.regionIds.length > 0) apiFilter.region_ids = filterState.regionIds;
  if (filterState.departementIds.length > 0) apiFilter.departement_ids = filterState.departementIds;
  if (filterState.arrondissementIds.length > 0) apiFilter.arrondissement_ids = filterState.arrondissementIds;

  // ── 7-8. Catégorie / Type de bien ────────────────────────────────────────
  // Le backend n'accepte qu'UN SEUL categorie_id (pas une liste) — vérifié sur
  // le Swagger de /api/stats/dashboard, 2026-08-28 (évolution vers une liste
  // annoncée mais pas encore livrée côté serveur). L'UI reste en multi-sélection
  // (demande explicite), donc on envoie la catégorie la PLUS RÉCEMMENT cochée
  // (dernier élément du tableau — voir toggleCategorie dans
  // FilterCategorieTreeMulti.tsx) ; les autres restent cochées visuellement
  // mais sans effet API tant que le backend ne supporte pas la liste. Passer
  // ce dernier élément à une liste dès que `categorie_id` acceptera un CSV.
  if (filterState.categorieIds.length > 0) {
    apiFilter.categorie_id = filterState.categorieIds[filterState.categorieIds.length - 1];
  }
  if (filterState.assetTypeIds.length > 0) apiFilter.type_bien_ids = filterState.assetTypeIds;

  // ── 9. Projet / Donateur (multi-sélection) ──────────────────────────────
  if (filterState.projectIds.length > 0) apiFilter.projet_ids = filterState.projectIds;

  // ── 10. États du bien (IDs directs) ────────────────────────────────────
  if (filterState.etatBienIds && filterState.etatBienIds.length > 0) {
    apiFilter.etat_bien_ids = filterState.etatBienIds;
  }

  // ── 11. Recherche par détenteur / matricule ─────────────────────────────
  if (filterState.searchHolder && filterState.searchHolder.trim() !== "") {
    apiFilter.matricule = filterState.searchHolder.trim();
  }

  // ── 12. En litige uniquement ────────────────────────────────────────────
  if (filterState.enLitigeOnly === true) {
    apiFilter.en_litige = true;
  }

  // ── 13. Exercice — toujours actif (année en cours par défaut, cf. YearStepper) ──
  apiFilter.exercice = filterState.exercice;

  return apiFilter;
}
