/**
 * Appels API — Gestion des régions du Cameroun.
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiRegion {
  id: number;
  nom: string;
  code: string;
  is_delete: boolean;
}

export interface CreateRegionResult {
  created: number;
  restored: number;
  regions: ApiRegion[];
}

export interface ListRegionsParams extends PaginationParams {
  search?: string;
  is_delete?: boolean;
}

export interface RegionPayload {
  nom: string;
  code: string;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function listRegions(
  params: ListRegionsParams = {},
): Promise<ApiResponse<PaginatedData<ApiRegion>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiRegion>>>("/regions", { params });
  return response.data;
}

export async function getRegionById(id: number): Promise<ApiResponse<ApiRegion>> {
  const response = await api.get<ApiResponse<ApiRegion>>(`/regions/${id}`);
  return response.data;
}

export async function createRegion(
  payload: RegionPayload,
): Promise<ApiResponse<CreateRegionResult>> {
  const response = await api.post<ApiResponse<CreateRegionResult>>("/regions", payload);
  return response.data;
}

export async function updateRegion(
  id: number,
  payload: Partial<RegionPayload>,
): Promise<ApiResponse<ApiRegion>> {
  const response = await api.put<ApiResponse<ApiRegion>>(`/regions/${id}`, payload);
  return response.data;
}

export async function softDeleteRegion(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/regions/${id}/soft-delete`);
  return response.data;
}

export async function restoreRegion(id: number): Promise<ApiResponse<ApiRegion>> {
  const response = await api.post<ApiResponse<ApiRegion>>(`/regions/${id}/restore`, {});
  return response.data;
}

// ─── Cartographie hiérarchique ─────────────────────────────────────────────

export interface CartographieArrondissement {
  id: number;
  nom: string;
  code?: string;
}

export interface CartographieDepartement {
  id: number;
  nom: string;
  code?: string;
  arrondissements: CartographieArrondissement[];
}

export interface CartographieRegion {
  id: number;
  nom: string;
  code?: string;
  departements: CartographieDepartement[];
}

export async function getCartographie(): Promise<ApiResponse<CartographieRegion[]>> {
  const response = await api.get<ApiResponse<CartographieRegion[]>>("/cartographie");
  return response.data;
}
