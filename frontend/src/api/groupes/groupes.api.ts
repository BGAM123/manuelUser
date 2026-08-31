/**
 * Appels API — Gestion des groupes d'utilisateurs.
 *
 * Un groupe regroupe des permissions (définies à la création, ajustables
 * ensuite). L'appartenance à un groupe se gère côté utilisateur (voir
 * assignGroupeToUser/removeGroupeFromUser dans users.api.ts) — pas ici.
 *
 * IMPORTANT — GET /groupes (liste) ne documente pas s'il inclut les
 * permissions par item. Ne jamais faire confiance à cette liste pour
 * connaître les permissions d'un groupe : toujours recharger la fiche
 * détail (getGroupeById), seule à garantir "avec ses permissions".
 *
 * Les changements de permissions se font en incrémental (endpoints dédiés
 * ci-dessous), jamais en renvoyant la liste complète via PUT/PATCH — un
 * remplacement intégral basé sur des données potentiellement incomplètes
 * écraserait silencieusement des permissions existantes.
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";
import type { ApiPermission } from "@/api/permissions/permissions.api";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiGroupe {
  id: number;
  nom: string;
  description?: string;
  is_active: boolean;
  permissions?: (ApiPermission | number)[];
}

export interface ListGroupesParams extends PaginationParams {
  q?: string;
  is_active?: boolean;
}

/** Champs de base du groupe — jamais les permissions (voir endpoints dédiés). */
export interface GroupeBasePayload {
  nom?: string;
  description?: string;
  is_active?: boolean;
}

// ─── Fonctions API — CRUD groupe ────────────────────────────────────────────

export async function listGroupes(
  params: ListGroupesParams = {},
): Promise<ApiResponse<PaginatedData<ApiGroupe>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiGroupe>>>("/groupes", { params });
  return response.data;
}

/** GET /groupes/{id} — Seul endpoint garantissant les permissions du groupe. */
export async function getGroupeById(id: number): Promise<ApiResponse<ApiGroupe>> {
  const response = await api.get<ApiResponse<ApiGroupe>>(`/groupes/${id}`);
  return response.data;
}

/**
 * POST /groupes — Crée un groupe (nom, description, is_active).
 * Les permissions initiales s'affectent ensuite via assignPermissionsToGroupe.
 */
export async function createGroupe(payload: GroupeBasePayload & { nom: string }): Promise<ApiResponse<ApiGroupe>> {
  const response = await api.post<ApiResponse<ApiGroupe>>("/groupes", payload);
  return response.data;
}

/** PATCH /groupes/{id} — Met à jour nom/description/is_active uniquement. */
export async function updateGroupe(
  id: number,
  payload: GroupeBasePayload,
): Promise<ApiResponse<ApiGroupe>> {
  const response = await api.patch<ApiResponse<ApiGroupe>>(`/groupes/${id}`, payload);
  return response.data;
}

/**
 * DELETE /groupes/{id}/soft-delete — Suppression logique.
 * Aucun blocage même si des utilisateurs actifs sont rattachés : leurs droits
 * hérités du groupe disparaissent simplement de leurs permissions effectives.
 */
export async function softDeleteGroupe(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/groupes/${id}/soft-delete`);
  return response.data;
}

// ─── Fonctions API — Privilèges du groupe (incrémental) ────────────────────

/** POST /groupes/{id}/permissions — Affecte une ou plusieurs permissions d'un coup. */
export async function assignPermissionsToGroupe(
  groupeId: number,
  permissionIds: number[],
): Promise<ApiResponse<null>> {
  const response = await api.post<ApiResponse<null>>(`/groupes/${groupeId}/permissions`, {
    permission_ids: permissionIds,
  });
  return response.data;
}

/** DELETE /groupes/{id}/permissions/{permissionId} — Retire une permission du groupe. */
export async function removePermissionFromGroupe(
  groupeId: number,
  permissionId: number,
): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(
    `/groupes/${groupeId}/permissions/${permissionId}`,
  );
  return response.data;
}
