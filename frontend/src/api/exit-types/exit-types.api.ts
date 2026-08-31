/**
 * Appels API — Gestion des types de sortie.
 * Structure réponse différente des autres modules :
 *   - liste : { items: [], pagination: { page, limit, total, pages } }
 *   - item  : { id, nom, code, description, isActive, createdAt, updatedAt }
 */

import api from "@/api/axios";
import type { ApiResponse } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiExitType {
  id: number;
  nom: string;
  code: string;
  description: string | null;
  isActive: boolean;
  /** Si true, la sortie d'un bien avec ce type exige un bénéficiaire (service/poste). */
  beneficiaire: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface ExitTypePagination {
  page: number;
  limit: number;
  total: number;
  pages: number;
}

export interface ExitTypeListData {
  items: ApiExitType[];
  pagination: ExitTypePagination;
  filters: {
    search: string | null;
    include_inactive: boolean;
    order_by: string;
    order_dir: string;
  };
}

export interface ListExitTypesParams {
  page?: number;
  limit?: number;
  include_inactive?: boolean;
  search?: string;
  order_by?: "nom" | "code" | "createdAt";
  order_dir?: "ASC" | "DESC";
}

export interface CreateExitTypePayload {
  nom: string;
  code: string;
  description?: string;
  isActive?: boolean;
  beneficiaire?: boolean;
}

export interface UpdateExitTypePayload {
  nom?: string;
  code?: string;
  description?: string;
  isActive?: boolean;
  beneficiaire?: boolean;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function listExitTypes(
  params: ListExitTypesParams = {},
): Promise<ApiResponse<ExitTypeListData>> {
  const response = await api.get<ApiResponse<ExitTypeListData>>("/exit-types", { params });
  return response.data;
}

export async function getExitTypeById(id: number): Promise<ApiResponse<ApiExitType>> {
  const response = await api.get<ApiResponse<ApiExitType>>(`/exit-types/${id}`);
  return response.data;
}

export async function createExitType(
  payload: CreateExitTypePayload,
): Promise<ApiResponse<ApiExitType>> {
  const response = await api.post<ApiResponse<ApiExitType>>("/exit-types", payload);
  return response.data;
}

export async function updateExitType(
  id: number,
  payload: UpdateExitTypePayload,
): Promise<ApiResponse<ApiExitType>> {
  const response = await api.put<ApiResponse<ApiExitType>>(`/exit-types/${id}`, payload);
  return response.data;
}

export async function deleteExitType(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/exit-types/${id}`);
  return response.data;
}
