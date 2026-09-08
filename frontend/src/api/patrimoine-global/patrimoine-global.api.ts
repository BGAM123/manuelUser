/**
 * Couche API pour l'endpoint GET /patrimoine_global.
 *
 * Vue consolidée et structurée de l'ensemble du patrimoine et des
 * consommables, avec des regroupements par différents critères
 * (état, service, projet, catégorie, type...).
 *
 * NB: chaque regroupement renvoyé par le back-end est paginé
 * indépendamment (page/limit). Pour un export "complet" (document Excel),
 * on interroge l'API avec une limite volontairement très haute afin de
 * récupérer l'intégralité des lignes de chaque regroupement en un seul appel.
 */
import api from "@/api/axios";
import type { ApiResponse } from "@/api/types";

export interface PatrimoineGlobalFilters {
  service?: string; // ids séparés par virgule
  categorie?: string; // ids séparés par virgule
  type?: string; // ids séparés par virgule
  statut?: string; // valeurs séparées par virgule
  etat?: string; // ids séparés par virgule
  sousType?: string; // ids séparés par virgule
}

export interface PatrimoineGlobalPagination {
  page: number;
  limit: number;
  total: number;
  pages: number;
}

export interface PatrimoineGlobalGroup {
  data: Record<string, any>[];
  pagination?: PatrimoineGlobalPagination;
  [key: string]: any;
}

/**
 * Contenu utile de la réponse (déjà déballé de l'enveloppe
 * `{success, status, message, data}` par `getPatrimoineGlobal`).
 * Forme réelle : `{ PATRIMOINE_GLOBAL: { BIENS: {...}, CONSOMMABLES: {...} } }`.
 */
export interface PatrimoineGlobalResponse {
  PATRIMOINE_GLOBAL: {
    BIENS: Record<string, any>;
    CONSOMMABLES: Record<string, any>;
  };
  [key: string]: any;
}

// Limite haute utilisée pour récupérer l'intégralité des données de chaque
// regroupement en un seul appel (export complet, pas de pagination UI).
export const PATRIMOINE_GLOBAL_EXPORT_LIMIT = 100000;

export async function getPatrimoineGlobal(
  filters: PatrimoineGlobalFilters = {},
  limit: number = PATRIMOINE_GLOBAL_EXPORT_LIMIT
): Promise<PatrimoineGlobalResponse> {
  const params: Record<string, string | number> = { page: 1, limit };
  if (filters.service) params.service = filters.service;
  if (filters.categorie) params.categorie = filters.categorie;
  if (filters.type) params.type = filters.type;
  if (filters.statut) params.statut = filters.statut;
  if (filters.etat) params.etat = filters.etat;
  if (filters.sousType) params.sousType = filters.sousType;

  // L'API enveloppe toujours la charge utile dans { success, status, message, data }.
  const response = await api.get<ApiResponse<PatrimoineGlobalResponse>>("/patrimoine_global", { params });
  return response.data.data;
}
