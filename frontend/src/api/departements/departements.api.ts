/**
 * Appels API — Gestion des départements du Cameroun.
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiDepartement {
  id: number;
  nom: string;
  code: string;
  region_id: number;
  is_delete: boolean;
}

export interface CreateDepartementResult {
  created: number;
  restored: number;
  departements: ApiDepartement[];
}

export interface ListDepartementsParams extends PaginationParams {
  search?: string;
  region_id?: number;
  is_delete?: boolean;
}

export interface CreateDepartementPayload {
  nom: string;
  code: string;
  region_id: number;
}

export interface UpdateDepartementPayload {
  nom?: string;
  code?: string;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function listDepartements(
  params: ListDepartementsParams = {},
): Promise<ApiResponse<PaginatedData<ApiDepartement>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiDepartement>>>("/departements", { params });
  return response.data;
}

export async function getDepartementById(id: number): Promise<ApiResponse<ApiDepartement>> {
  const response = await api.get<ApiResponse<ApiDepartement>>(`/departements/${id}`);
  return response.data;
}

export async function createDepartement(
  payload: CreateDepartementPayload,
): Promise<ApiResponse<CreateDepartementResult>> {
  const response = await api.post<ApiResponse<CreateDepartementResult>>("/departements", payload);
  return response.data;
}

export async function updateDepartement(
  id: number,
  payload: UpdateDepartementPayload,
): Promise<ApiResponse<ApiDepartement>> {
  const response = await api.put<ApiResponse<ApiDepartement>>(`/departements/${id}`, payload);
  return response.data;
}

export async function softDeleteDepartement(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/departements/${id}/soft-delete`);
  return response.data;
}

export async function restoreDepartement(id: number): Promise<ApiResponse<ApiDepartement>> {
  const response = await api.post<ApiResponse<ApiDepartement>>(`/departements/${id}/restore`, {});
  return response.data;
}
