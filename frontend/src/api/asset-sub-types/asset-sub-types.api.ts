/**
 * API — Gestion des sous-types de biens.
 * Endpoints: GET/POST /asset-sub-types, PATCH /asset-sub-types/{id},
 *            POST /asset-sub-types/{id}/restore, DELETE /asset-sub-types/{id}/soft-delete
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ──────────────────────────────────────────────────────────────────

export interface ApiAssetSubType {
  id: number;
  nom: string;
  asset_type_id: number;
  description?: string | null;
  is_delete: boolean;
}

export interface ListAssetSubTypesParams extends PaginationParams {
  search?: string;
  asset_type_id?: number;
  is_delete?: boolean;
}

export interface AssetSubTypePayload {
  nom: string;
  asset_type_id: number;
  description?: string;
}

// ─── Fonctions ───────────────────────────────────────────────────────────────

export async function listAssetSubTypes(
  params: ListAssetSubTypesParams = {},
): Promise<ApiResponse<PaginatedData<ApiAssetSubType>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiAssetSubType>>>(
    "/asset-sub-types",
    { params },
  );
  return response.data;
}

export async function getAssetSubTypeById(id: number): Promise<ApiResponse<ApiAssetSubType>> {
  const response = await api.get<ApiResponse<ApiAssetSubType>>(`/asset-sub-types/${id}`);
  return response.data;
}

export async function createAssetSubType(
  payload: AssetSubTypePayload,
): Promise<ApiResponse<ApiAssetSubType>> {
  const response = await api.post<ApiResponse<ApiAssetSubType>>("/asset-sub-types", payload);
  return response.data;
}

export async function updateAssetSubType(
  id: number,
  payload: Partial<AssetSubTypePayload>,
): Promise<ApiResponse<ApiAssetSubType>> {
  const response = await api.patch<ApiResponse<ApiAssetSubType>>(
    `/asset-sub-types/${id}`,
    payload,
  );
  return response.data;
}

export async function softDeleteAssetSubType(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(
    `/asset-sub-types/${id}/soft-delete`,
  );
  return response.data;
}

export async function restoreAssetSubType(id: number): Promise<ApiResponse<ApiAssetSubType>> {
  const response = await api.post<ApiResponse<ApiAssetSubType>>(
    `/asset-sub-types/${id}/restore`,
    {},
  );
  return response.data;
}
