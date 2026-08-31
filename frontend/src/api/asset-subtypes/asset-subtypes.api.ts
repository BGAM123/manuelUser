/**
 * Appels API — Gestion des sous-types de biens patrimoniaux.
 * Structure paginée : ApiResponse<PaginatedData<T>> (même format que /asset-types).
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiAssetSubtype {
  id: number;
  nom: string;
  description?: string | null;
  asset_type_id: number;
  is_delete: boolean;
}

export interface ListAssetSubtypesParams extends PaginationParams {
  search?: string;
  asset_type_id?: number;
  include_deleted?: boolean;
}

export interface CreateAssetSubtypePayload {
  nom: string;
  description?: string;
  asset_type_id: number;
}

export interface UpdateAssetSubtypePayload {
  nom?: string;
  description?: string;
  asset_type_id?: number;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function listAssetSubtypes(
  params: ListAssetSubtypesParams = {},
): Promise<ApiResponse<PaginatedData<ApiAssetSubtype>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiAssetSubtype>>>(
    "/asset-sub-types",
    { params },
  );
  return response.data;
}

export async function getAssetSubtypeById(
  id: number,
): Promise<ApiResponse<ApiAssetSubtype>> {
  const response = await api.get<ApiResponse<ApiAssetSubtype>>(
    `/asset-sub-types/${id}`,
  );
  return response.data;
}

export async function createAssetSubtype(
  payload: CreateAssetSubtypePayload,
): Promise<ApiResponse<ApiAssetSubtype>> {
  const response = await api.post<ApiResponse<ApiAssetSubtype>>(
    "/asset-sub-types",
    payload,
  );
  return response.data;
}

export async function updateAssetSubtype(
  id: number,
  payload: UpdateAssetSubtypePayload,
): Promise<ApiResponse<ApiAssetSubtype>> {
  const response = await api.put<ApiResponse<ApiAssetSubtype>>(
    `/asset-sub-types/${id}`,
    payload,
  );
  return response.data;
}

export async function softDeleteAssetSubtype(
  id: number,
): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(
    `/asset-sub-types/${id}/soft-delete`,
  );
  return response.data;
}

export async function restoreAssetSubtype(
  id: number,
): Promise<ApiResponse<ApiAssetSubtype>> {
  const response = await api.post<ApiResponse<ApiAssetSubtype>>(
    `/asset-sub-types/${id}/restore`,
    {},
  );
  return response.data;
}
