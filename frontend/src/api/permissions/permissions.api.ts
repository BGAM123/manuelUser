/**
 * Appels API — Gestion des permissions.
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiPermission {
  id: number;
  nom: string;
  description: string;
  is_active: boolean;
}

export interface ListPermissionsParams extends PaginationParams {
  q?: string;
  is_active?: boolean;
  /** false (défaut) = permissions actives uniquement. true = permissions supprimées (corbeille) uniquement. "all" = toutes. */
  is_delete?: boolean | "all";
}

export interface CreatePermissionPayload {
  nom: string;
  description?: string;
  is_active?: boolean;
}

export interface UpdatePermissionPayload {
  nom?: string;
  description?: string;
  is_active?: boolean;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function listPermissions(
  params: ListPermissionsParams = {},
): Promise<ApiResponse<PaginatedData<ApiPermission>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiPermission>>>("/permissions", { params });
  return response.data;
}

export async function getPermissionById(id: number): Promise<ApiResponse<ApiPermission>> {
  const response = await api.get<ApiResponse<ApiPermission>>(`/permissions/${id}`);
  return response.data;
}

export async function createPermission(
  payload: CreatePermissionPayload,
): Promise<ApiResponse<ApiPermission>> {
  const response = await api.post<ApiResponse<ApiPermission>>("/permissions", payload);
  return response.data;
}

/**
 * POST /permissions avec un tableau — crée une ou plusieurs permissions en
 * un seul appel. Utilisée par le formulaire de création (répétable), même
 * pour une seule permission.
 */
export async function createPermissions(
  payloads: CreatePermissionPayload[],
): Promise<ApiResponse<ApiPermission[]>> {
  const response = await api.post<ApiResponse<ApiPermission[] | ApiPermission>>("/permissions", payloads);
  const data = response.data.data;
  return { ...response.data, data: Array.isArray(data) ? data : data ? [data] : [] };
}

export async function updatePermission(
  id: number,
  payload: UpdatePermissionPayload,
): Promise<ApiResponse<ApiPermission>> {
  const response = await api.put<ApiResponse<ApiPermission>>(`/permissions/${id}`, payload);
  return response.data;
}

/** DELETE /permissions/{id} — suppression physique, irréversible. */
export async function deletePermission(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/permissions/${id}`);
  return response.data;
}

/** DELETE /permissions/{id}/soft-delete — suppression logique (corbeille). */
export async function softDeletePermission(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/permissions/${id}/soft-delete`);
  return response.data;
}

/** PUT /permissions/{id}/restore — restaure une permission précédemment supprimée logiquement. */
export async function restorePermission(id: number): Promise<ApiResponse<null>> {
  const response = await api.put<ApiResponse<null>>(`/permissions/${id}/restore`, {});
  return response.data;
}
