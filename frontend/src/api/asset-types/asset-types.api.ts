/**
 * Appels API — Gestion des types de biens patrimoniaux.
 * Structure paginée : ApiResponse<PaginatedData<T>> (même format que /services).
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiAssetType {
  id: number;
  nom: string;
  description?: string | null;
  category_id: number;
  dureeVie?: number | null;
  taux?: number | null;
  is_delete: boolean;
}

export interface ListAssetTypesParams extends PaginationParams {
  search?: string;
  category_id?: number;
  include_deleted?: boolean;
}

export interface CreateAssetTypePayload {
  nom: string;
  description?: string;
  category_id: number;
  dureeVie?: number;
  taux?: number;
}

export interface UpdateAssetTypePayload {
  nom?: string;
  description?: string;
  category_id?: number;
  dureeVie?: number;
  taux?: number;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function listAssetTypes(
  params: ListAssetTypesParams = {},
): Promise<ApiResponse<PaginatedData<ApiAssetType>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiAssetType>>>("/asset-types", { params });
  return response.data;
}

export async function getAssetTypeById(id: number): Promise<ApiResponse<ApiAssetType>> {
  const response = await api.get<ApiResponse<ApiAssetType>>(`/asset-types/${id}`);
  return response.data;
}

export async function createAssetType(
  payload: CreateAssetTypePayload,
): Promise<ApiResponse<ApiAssetType>> {
  const response = await api.post<ApiResponse<ApiAssetType>>("/asset-types", payload);
  return response.data;
}

export async function updateAssetType(
  id: number,
  payload: UpdateAssetTypePayload,
): Promise<ApiResponse<ApiAssetType>> {
  const response = await api.put<ApiResponse<ApiAssetType>>(`/asset-types/${id}`, payload);
  return response.data;
}

export async function softDeleteAssetType(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/asset-types/${id}/soft-delete`);
  return response.data;
}

export async function restoreAssetType(id: number): Promise<ApiResponse<ApiAssetType>> {
  const response = await api.post<ApiResponse<ApiAssetType>>(`/asset-types/${id}/restore`, {});
  return response.data;
}

/** GET /asset-types/{id}/etat-biens — états de bien autorisés pour ce type de bien. */
export async function getEtatBiensForAssetType(
  id: number,
): Promise<ApiResponse<Array<{ id: number; nom: string }>>> {
  const response = await api.get<ApiResponse<Array<{ id: number; nom: string }>>>(`/asset-types/${id}/etat-biens`);
  return response.data;
}
