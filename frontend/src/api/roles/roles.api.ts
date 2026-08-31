/**
 * Appels API — Gestion des rôles.
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";
import type { ApiPermission } from "@/api/permissions/permissions.api";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiRole {
  id: number;
  nom: string;
  description: string;
  is_active: boolean;
  permissions?: ApiPermission[];
}

export interface ListRolesParams extends PaginationParams {
  q?: string;
  is_active?: boolean;
  /** false (défaut) = rôles actifs uniquement. true = rôles supprimés (corbeille) uniquement. "all" = tous. */
  is_delete?: boolean | "all";
}

export interface CreateRolePayload {
  nom: string;
  description?: string;
  is_active?: boolean;
  /** Permissions à associer dès la création (POST /roles accepte un tableau de rôles, chacun avec ses permissions). */
  permissions?: number[];
}

export interface UpdateRolePayload {
  nom?: string;
  description?: string;
  is_active?: boolean;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function listRoles(
  params: ListRolesParams = {},
): Promise<ApiResponse<PaginatedData<ApiRole>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiRole>>>("/roles", { params });
  return response.data;
}

export async function getRoleById(id: number): Promise<ApiResponse<ApiRole>> {
  const response = await api.get<ApiResponse<ApiRole>>(`/roles/${id}`);
  return response.data;
}

export async function createRole(payload: CreateRolePayload): Promise<ApiResponse<ApiRole>> {
  const response = await api.post<ApiResponse<ApiRole>>("/roles", payload);
  return response.data;
}

/**
 * POST /roles avec un tableau — crée un ou plusieurs rôles en un seul appel,
 * chacun avec ses permissions éventuelles directement incluses (pas besoin
 * d'un appel séparé à assignPermissionsToRole ensuite). Utilisée par le
 * formulaire de création (répétable), même pour un seul rôle.
 */
export async function createRoles(
  payloads: CreateRolePayload[],
): Promise<ApiResponse<ApiRole[]>> {
  const response = await api.post<ApiResponse<ApiRole[] | ApiRole>>("/roles", payloads);
  const data = response.data.data;
  return { ...response.data, data: Array.isArray(data) ? data : data ? [data] : [] };
}

export async function updateRole(
  id: number,
  payload: UpdateRolePayload,
): Promise<ApiResponse<ApiRole>> {
  const response = await api.put<ApiResponse<ApiRole>>(`/roles/${id}`, payload);
  return response.data;
}

/** DELETE /roles/{id} — suppression physique, irréversible. */
export async function deleteRole(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/roles/${id}`);
  return response.data;
}

/** DELETE /roles/{id}/soft-delete — suppression logique (corbeille). */
export async function softDeleteRole(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/roles/${id}/soft-delete`);
  return response.data;
}

/** PUT /roles/{id}/restore — restaure un rôle précédemment supprimé logiquement. */
export async function restoreRole(id: number): Promise<ApiResponse<null>> {
  const response = await api.put<ApiResponse<null>>(`/roles/${id}/restore`, {});
  return response.data;
}

export async function getRolePermissions(roleId: number): Promise<ApiResponse<ApiPermission[]>> {
  const response = await api.get<ApiResponse<ApiPermission[]>>(`/roles/${roleId}/permissions`);
  return response.data;
}

export async function assignPermissionsToRole(
  roleId: number,
  permissionIds: number[],
): Promise<ApiResponse<null>> {
  const response = await api.post<ApiResponse<null>>(`/roles/${roleId}/permissions`, {
    permission_ids: permissionIds,
  });
  return response.data;
}

export async function assignPermissionToRole(
  roleId: number,
  permissionId: number,
): Promise<ApiResponse<null>> {
  const response = await api.post<ApiResponse<null>>(
    `/roles/${roleId}/permissions/${permissionId}`,
    {},
  );
  return response.data;
}

export async function removePermissionFromRole(
  roleId: number,
  permissionId: number,
): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(
    `/roles/${roleId}/permissions/${permissionId}`,
  );
  return response.data;
}
