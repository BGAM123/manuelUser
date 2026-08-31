/**
 * Appels API — Gestion des arrondissements du Cameroun.
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiArrondissement {
  id: number;
  nom: string;
  code: string;
  departement_id: number;
  is_delete: boolean;
}

export interface CreateArrondissementResult {
  created: number;
  restored: number;
  arrondissements: ApiArrondissement[];
}

export interface ListArrondissementsParams extends PaginationParams {
  search?: string;
  departement_id?: number;
  is_delete?: boolean;
}

export interface CreateArrondissementPayload {
  nom: string;
  code: string;
  departement_id: number;
}

export interface UpdateArrondissementPayload {
  nom?: string;
  code?: string;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function listArrondissements(
  params: ListArrondissementsParams = {},
): Promise<ApiResponse<PaginatedData<ApiArrondissement>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiArrondissement>>>("/arrondissements", { params });
  return response.data;
}

export async function getArrondissementById(id: number): Promise<ApiResponse<ApiArrondissement>> {
  const response = await api.get<ApiResponse<ApiArrondissement>>(`/arrondissements/${id}`);
  return response.data;
}

export async function createArrondissement(
  payload: CreateArrondissementPayload,
): Promise<ApiResponse<CreateArrondissementResult>> {
  const response = await api.post<ApiResponse<CreateArrondissementResult>>("/arrondissements", payload);
  return response.data;
}

export async function updateArrondissement(
  id: number,
  payload: UpdateArrondissementPayload,
): Promise<ApiResponse<ApiArrondissement>> {
  const response = await api.put<ApiResponse<ApiArrondissement>>(`/arrondissements/${id}`, payload);
  return response.data;
}

export async function softDeleteArrondissement(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/arrondissements/${id}/soft-delete`);
  return response.data;
}

export async function restoreArrondissement(id: number): Promise<ApiResponse<ApiArrondissement>> {
  const response = await api.post<ApiResponse<ApiArrondissement>>(`/arrondissements/${id}/restore`, {});
  return response.data;
}
