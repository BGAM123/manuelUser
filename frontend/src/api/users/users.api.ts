/**
 * Appels API — Gestion des utilisateurs.
 * Tous les appels passent par l'instance api (axios.ts) qui gère
 * automatiquement les tokens JWT.
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiUserService {
  id: number;
  nom: string;
  sigle?: string;
  type_service?: string;
  ordre: number;
  is_active: boolean;
}

export interface ApiUser {
  id: number;
  firstName: string;
  lastName: string;
  email: string;
  matricule?: string | null;
  cni?: string | null;        // champ retourné par le backend
  numeroCNI?: string | null;  // alias frontend
  is_active: boolean;
  twoFactorEnabled?: boolean;
  createdAt: string;
  service?: ApiUserService;
  assignedRoles?: { id: number; nom: string }[];
  // Non documenté par le Swagger (forme exacte incertaine, objets ou IDs bruts)
  // — utilisé uniquement pour déduire un statut "déjà membre" dans l'écran
  // Groupes > Gérer les membres ; à défaut, on montre juste "Ajouter" partout.
  assignedGroupes?: ({ id: number; nom: string } | number)[];
}

export interface ListUsersParams extends PaginationParams {
  is_active?: boolean;
  is_dlet?: boolean;
  service_id?: number;
}

export interface CreateUserPayload {
  firstName: string;
  lastName: string;
  email: string;
  password: string;
  matricule?: string;
  cni?: string;           // backend field name
  is_active?: boolean;
  twoFactorEnabled?: boolean;
  service_id?: number;
  role_ids?: number[];
  group_ids?: number[];
  granted_permission_ids?: number[];
  revoked_permission_ids?: number[];
}

export interface UpdateUserPayload {
  firstName?: string;
  lastName?: string;
  email?: string;
  matricule?: string;
  cni?: string;           // backend field name
  is_active?: boolean;
  twoFactorEnabled?: boolean;
  service_id?: number | null;
  role_ids?: number[];
  granted_permission_ids?: number[];
  revoked_permission_ids?: number[];
}

export interface UpdatePasswordPayload {
  password: string;
  new_password: string;
}

export interface ResetUserPasswordPayload {
  password: string;
  passwordConfirm: string;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

/**
 * GET /users — Liste paginée des utilisateurs.
 */
export async function listUsers(
  params: ListUsersParams = {},
): Promise<ApiResponse<PaginatedData<ApiUser>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiUser>>>("/users", { params });
  return response.data;
}

/**
 * Charge TOUS les utilisateurs en paginant automatiquement (max 20/page côté serveur).
 * Utilisé par les selects qui ont besoin de la liste complète.
 */
export async function listAllUsers(): Promise<ApiUser[]> {
  const PAGE_SIZE = 20;
  const first = await api.get<ApiResponse<PaginatedData<ApiUser>>>("/users", {
    params: { page: 1, limit: PAGE_SIZE },
  });
  const payload = first.data.data;
  const items: ApiUser[] = [...(payload.data ?? [])];
  // Le backend retourne pagination.pages dans meta ou dans un champ pagination séparé
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const meta = (payload as any).pagination ?? payload.meta;
  const totalPages: number = meta?.pages ?? meta?.total_pages ?? 1;
  if (totalPages > 1) {
    const rest = await Promise.all(
      Array.from({ length: totalPages - 1 }, (_, i) =>
        api.get<ApiResponse<PaginatedData<ApiUser>>>("/users", {
          params: { page: i + 2, limit: PAGE_SIZE },
        })
      )
    );
    for (const r of rest) {
      items.push(...(r.data.data?.data ?? []));
    }
  }
  return items;
}

/**
 * GET /users/{id} — Détail d'un utilisateur.
 */
export async function getUserById(id: number): Promise<ApiResponse<ApiUser>> {
  const response = await api.get<ApiResponse<ApiUser>>(`/users/${id}`);
  return response.data;
}

/**
 * POST /users — Créer un utilisateur.
 */
export async function createUser(
  payload: CreateUserPayload,
): Promise<ApiResponse<ApiUser>> {
  const response = await api.post<ApiResponse<ApiUser>>("/users", payload);
  return response.data;
}

/**
 * PUT /users/{id} — Mettre à jour un utilisateur.
 */
export async function updateUser(
  id: number,
  payload: UpdateUserPayload,
): Promise<ApiResponse<ApiUser>> {
  const response = await api.put<ApiResponse<ApiUser>>(`/users/${id}`, payload);
  return response.data;
}

/**
 * DELETE /users/{id} — Suppression logique d'un utilisateur.
 */
export async function deleteUser(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/users/${id}`);
  return response.data;
}

/**
 * POST /users/restore — Restaure un ou plusieurs utilisateurs supprimés
 * logiquement (isDelete = true). Les utilisateurs inexistants ou non
 * supprimés sont simplement ignorés (`skipped`).
 */
export interface RestoreUsersResult {
  restored: number[];
  skipped: number[];
  skipped_reasons?: Record<string, string>;
}

export async function restoreUsers(
  ids: number[],
): Promise<ApiResponse<RestoreUsersResult>> {
  const response = await api.post<ApiResponse<RestoreUsersResult>>("/users/restore", {
    ids,
  });
  return response.data;
}

/**
 * DELETE /users/{id}/force — Suppression physique définitive d'un
 * utilisateur. Action irréversible.
 */
export async function forceDeleteUser(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/users/${id}/force`);
  return response.data;
}


/**
 * PUT /users/{id}/password — Changer le mot de passe (profil perso).
 */
export async function updateUserPassword(
  id: number,
  payload: UpdatePasswordPayload,
): Promise<ApiResponse<null>> {
  const response = await api.put<ApiResponse<null>>(
    `/users/${id}/password`,
    payload,
  );
  return response.data;
}

/**
 * PATCH /users/{id}/password — Réinitialiser le mot de passe (admin).
 * Payload : { password, passwordConfirm } — pas besoin de l'ancien mot de passe.
 */
export async function resetUserPassword(
  id: number,
  payload: ResetUserPasswordPayload,
): Promise<ApiResponse<null>> {
  const response = await api.patch<ApiResponse<null>>(
    `/users/${id}/password`,
    payload,
  );
  return response.data;
}

/**
 * POST /users/{userId}/groupes/{groupeId} — Affecte un utilisateur à un groupe.
 * 409 si déjà membre de ce groupe.
 */
export async function assignGroupeToUser(
  userId: number,
  groupeId: number,
): Promise<ApiResponse<null>> {
  const response = await api.post<ApiResponse<null>>(
    `/users/${userId}/groupes/${groupeId}`,
    {},
  );
  return response.data;
}

/** DELETE /users/{userId}/groupes/{groupeId} — Retire un utilisateur d'un groupe. */
export async function removeGroupeFromUser(
  userId: number,
  groupeId: number,
): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(
    `/users/${userId}/groupes/${groupeId}`,
  );
  return response.data;
}

export interface UpdateUserPermissionsPayload {
  /** Permissions accordées explicitement à l'utilisateur. */
  granted_permission_ids?: number[];
  /** Permissions explicitement révoquées pour l'utilisateur. */
  revoked_permission_ids?: number[];
}

export interface ApiUserPermissionsResult {
  id: number;
  firstName: string;
  lastName: string;
  assignedRoles?: { id: number; nom: string }[];
  permissions?: { id: number; nom: string }[];
  grantedPermissions?: { id: number; nom: string }[];
  revokedPermissions?: { id: number; nom: string }[];
}

/**
 * PATCH /users/{id}/permissions — Accorde et/ou révoque des permissions
 * directement sur un utilisateur, en conservant l'héritage des permissions
 * de ses rôles/groupes (ex : retirer une permission d'un groupe à ce membre
 * précis, sans le retirer du groupe ni affecter les autres membres). Pas de
 * groupe/rôle scopé côté backend — mécanisme global à l'utilisateur.
 */
export async function updateUserPermissions(
  id: number,
  payload: UpdateUserPermissionsPayload,
): Promise<ApiResponse<ApiUserPermissionsResult>> {
  const response = await api.patch<ApiResponse<ApiUserPermissionsResult>>(
    `/users/${id}/permissions`,
    payload,
  );
  return response.data;
}

/** Une entrée de permission telle que renvoyée par GET /users/{id}/permissions. */
export interface ApiUserPermissionEntry {
  id: number;
  nom: string;
  description?: string;
  is_active?: boolean;
}

/**
 * GET /users/{id}/permissions — état réel des permissions d'un utilisateur :
 * héritées (rôles, groupes) + accordées/révoquées explicitement + l'effectif
 * final. Confirmé fonctionnel en direct le 2026-08-21 — cet endpoint
 * n'existait pas (ou n'était pas documenté) auparavant, d'où le fait que
 * GroupMembersManager devait jusqu'ici deviner l'état initial des switches.
 */
export interface ApiUserPermissionsState {
  inheritedFromRoles: ApiUserPermissionEntry[];
  inheritedFromGroupes: ApiUserPermissionEntry[];
  granted: ApiUserPermissionEntry[];
  revoked: ApiUserPermissionEntry[];
  effective: ApiUserPermissionEntry[];
}

export async function getUserPermissions(id: number): Promise<ApiResponse<ApiUserPermissionsState>> {
  const response = await api.get<ApiResponse<ApiUserPermissionsState>>(`/users/${id}/permissions`);
  return response.data;
}
