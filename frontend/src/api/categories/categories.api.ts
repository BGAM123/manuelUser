/**
 * Appels API — Gestion des catégories de biens patrimoniaux.
 * Structure paginée : ApiResponse<PaginatedData<T>> (même format que /services).
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiAssetTypeSummary {
  id: number;
  nom: string;
}

export interface ApiCategory {
  id: number;
  nom: string;
  description: string | null;
  is_delete: boolean;
  assetTypes?: ApiAssetTypeSummary[];
  /** Seuil de maintenance (nombre d'interventions avant alerte) — facultatif. */
  seuil?: number | null;
  /** true = catégorie de consomptibles, false/absent = catégorie de biens. Mutuellement exclusif. */
  consommable?: boolean;
}

export interface ListCategoriesParams extends PaginationParams {
  search?: string;
  include_deleted?: boolean;
  /** "false" (défaut, catégories de biens) | "true" (consomptibles) | "all" */
  consommable?: "false" | "true" | "all";
}

export interface CreateCategoryPayload {
  nom: string;
  description?: string;
  seuil?: number;
  consommable?: boolean;
}

export interface UpdateCategoryPayload {
  nom?: string;
  description?: string;
  seuil?: number;
  consommable?: boolean;
}

export interface ApiCategoryThreshold {
  id: number;
  nom: string;
  seuil: number | null;
}

export interface ListCategoryThresholdsParams {
  page?: number;
  limit?: number;
  /** IDs de catégories séparés par des virgules (ex: "1,2,3") */
  category_ids?: string;
}

/** GET /categories/thresholds/list — seuils de maintenance des catégories (paginé). */
export async function listCategoryThresholds(
  params: ListCategoryThresholdsParams = {},
): Promise<ApiResponse<PaginatedData<ApiCategoryThreshold>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiCategoryThreshold>>>(
    "/categories/thresholds/list",
    { params },
  );
  return response.data;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function listCategories(
  params: ListCategoriesParams = {},
): Promise<ApiResponse<PaginatedData<ApiCategory>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiCategory>>>("/categories", { params });
  return response.data;
}

export async function getCategoryById(id: number): Promise<ApiResponse<ApiCategory>> {
  const response = await api.get<ApiResponse<ApiCategory>>(`/categories/${id}`);
  return response.data;
}

export async function createCategory(
  payload: CreateCategoryPayload,
): Promise<ApiResponse<ApiCategory>> {
  const response = await api.post<ApiResponse<ApiCategory>>("/categories", payload);
  return response.data;
}

export async function updateCategory(
  id: number,
  payload: UpdateCategoryPayload,
): Promise<ApiResponse<ApiCategory>> {
  const response = await api.put<ApiResponse<ApiCategory>>(`/categories/${id}`, payload);
  return response.data;
}

export async function softDeleteCategory(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/categories/${id}/soft-delete`);
  return response.data;
}

export async function restoreCategory(id: number): Promise<ApiResponse<ApiCategory>> {
  const response = await api.post<ApiResponse<ApiCategory>>(`/categories/${id}/restore`, {});
  return response.data;
}

/** POST /categories/{id}/champs — associe plusieurs champs à une catégorie (remplacement total). */
export async function assignChampsToCategory(
  categoryId: number,
  champ_ids: number[],
): Promise<ApiResponse<null>> {
  const response = await api.post<ApiResponse<null>>(`/categories/${categoryId}/champs`, { champ_ids });
  return response.data;
}

// ─── Hiérarchie complète ────────────────────────────────────────────────────

// Le backend ne documente pas de façon fiable le nom exact du champ de statut
// sur ces noeuds (is_delete vs isActive selon l'entité) — on garde les deux
// en optionnel et isDeletedHierarchyNode() ci-dessous tolère les deux formes.
export interface HierarchySubtype {
  id: number;
  nom: string;
  description: string | null;
  is_delete?: boolean;
  isActive?: boolean;
}

export interface HierarchyType {
  id: number;
  nom: string;
  description: string | null;
  dureeVie: number | null;
  taux: number | null;
  sousTypes: HierarchySubtype[];
  is_delete?: boolean;
  isActive?: boolean;
}

export interface HierarchyCategory {
  id: number;
  nom: string;
  description: string | null;
  types: HierarchyType[];
  is_delete?: boolean;
  isActive?: boolean;
  /** Peut être absent de la hiérarchie selon le backend — voir GET /categories/{id} pour la valeur fiable. */
  seuil?: number | null;
  consommable?: boolean;
}

/** Un noeud de la hiérarchie catégories/types/sous-types est-il archivé ? */
export function isDeletedHierarchyNode(node: { is_delete?: boolean; isActive?: boolean }): boolean {
  return node.is_delete === true || node.isActive === false;
}

export interface HierarchyMeta {
  current_page: number;
  limit: number;
  total_items: number;
  total_pages: number;
  search: string | null;
}

export interface HierarchyData {
  meta: HierarchyMeta;
  items: HierarchyCategory[];
}

export interface HierarchyParams {
  page?: number;
  limit?: number;
  search?: string;
  include_inactive?: boolean;
}

/** GET /organigramme_categorie/hierarchie — arbre complet Catégories → Types → Sous-types */
export async function getCategorieHierarchie(
  params: HierarchyParams = {},
): Promise<ApiResponse<HierarchyData>> {
  const response = await api.get<ApiResponse<HierarchyData>>(
    "/organigramme_categorie/hierarchie",
    { params },
  );
  return response.data;
}
