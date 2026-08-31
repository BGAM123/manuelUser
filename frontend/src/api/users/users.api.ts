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
  is_active: boolean;
  createdAt: string;
  service?: ApiUserService;
}

export interface ListUsersParams extends PaginationParams {
  is_active?: boolean;
  is_dlet?: boolean;
}

export interface CreateUserPayload {
  firstName: string;
  lastName: string;
  email: string;
  password: string;
  is_active?: boolean;
  service_id?: number;
}

export interface UpdateUserPayload {
  firstName?: string;
  lastName?: string;
  email?: string;
  is_active?: boolean;
  service_id?: number;
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
 * DELETE /users/{id} — Supprimer un utilisateur.
 */
export async function deleteUser(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/users/${id}`);
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
