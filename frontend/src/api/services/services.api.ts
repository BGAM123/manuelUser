/**
 * Appels API - Gestion de l'organigramme (services) et types d'organigramme.
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// --- Types ---

export interface ApiServiceParent {
  id: number;
  nom: string;
}

export interface ApiTypeOrganigramme {
  id: number;
  nom: string;
  description?: string;
}

/**
 * Normalise typeOrganigrammes qui peut etre number[] OU {id,nom}[]
 * selon l'endpoint (B.2 de la spec - incoherence cote backend).
 */
export function normalizeTypeOrganigrammes(
  raw: (number | ApiTypeOrganigramme)[],
): ApiTypeOrganigramme[] {
  return raw.map((item) =>
    typeof item === "number" ? { id: item, nom: String(item) } : item,
  );
}

/** Utilisateur rattaché à un noeud de type "Poste" (voir GET /organigramme) */
export interface ApiOrgNodeUser {
  id: number;
  firstName: string;
  lastName: string;
  matricule?: string | null;
}

/** Noeud de l'organigramme hierarchique (GET /organigramme) */
export interface ApiOrgNode {
  id: number;
  nom: string;
  sigle: string;
  code?: string;
  type_service: string;
  typeService?: string;
  ordre: number;
  is_active: boolean;
  parent_id: { id: number; nom: string } | null;
  typeOrganigrammes: (number | ApiTypeOrganigramme)[]; // see normalizeTypeOrganigrammes
  utilisateur: ApiOrgNodeUser | null;
  children: ApiOrgNode[];
}

export interface ApiService {
  id: number;
  nom: string;
  sigle?: string;
  code?: string;
  type_service?: string;
  typeService?: string;
  type?: string | { nom?: string; name?: string };
  ordre: number;
  is_active: boolean;
  parent_id?: ApiServiceParent | null;
  typeOrganigrammes?: ApiTypeOrganigramme[];
  localisation_id?: number | null;
}

export interface ListServicesParams extends PaginationParams {
  parent_id?: number;
  is_active?: boolean;
  type_organigramme_id?: number;
}

export interface CreateServicePayload {
  nom: string;
  sigle?: string;
  code?: string;
  type_service?: string;
  ordre?: number;
  is_active?: boolean;
  parent_id?: number | null;
  typeOrganigrammes?: number[];
  localisation_id?: number | null;
}

export interface UpdateServicePayload {
  nom?: string;
  // null explicite pour effacer le champ — string vide/undefined ne suffit
  // pas car JSON.stringify supprime les clés undefined, donc le backend ne
  // reçoit pas la clé et laisse la valeur existante inchangée.
  sigle?: string | null;
  code?: string | null;
  type_service?: string;
  ordre?: number;
  is_active?: boolean;
  parent_id?: number | null;
  typeOrganigrammes?: number[];
  localisation_id?: number | null;
}

export interface CreateTypeOrganigrammePayload {
  nom: string;
  description?: string;
}

export interface UpdateTypeOrganigrammePayload {
  nom?: string;
  description?: string;
}

// --- Fonctions Services ---

export async function listServices(
  params: ListServicesParams = {},
): Promise<ApiResponse<PaginatedData<ApiService>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiService>>>("/services", { params });
  return response.data;
}

export async function getServiceById(id: number): Promise<ApiResponse<ApiService>> {
  const response = await api.get<ApiResponse<ApiService>>(`/services/${id}`);
  return response.data;
}

export async function createService(
  payload: CreateServicePayload,
): Promise<ApiResponse<ApiService>> {
  const response = await api.post<ApiResponse<ApiService>>("/services", payload);
  return response.data;
}

export async function updateService(
  id: number,
  payload: UpdateServicePayload,
): Promise<ApiResponse<ApiService>> {
  const response = await api.put<ApiResponse<ApiService>>(`/services/${id}`, payload);
  return response.data;
}

export async function deleteService(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/services/${id}`);
  return response.data;
}

export async function patchService(
  id: number,
  payload: UpdateServicePayload,
): Promise<ApiResponse<ApiService>> {
  const response = await api.patch<ApiResponse<ApiService>>(`/services/${id}`, payload);
  return response.data;
}

/**
 * GET /organigramme - arbre hierarchique complet, ou recherche à plat.
 *
 * Sans `search` : renvoie l'arbre complet (nœuds imbriqués via `children`).
 * Avec `search` : le backend renvoie une liste À PLAT des seuls nœuds
 * correspondants (nom de poste, matricule OU nom d'utilisateur) — on peut
 * combiner avec `typeService` (ex: "POSTE") pour ne chercher que les postes.
 */
export async function getOrganigramme(
  params?: { typeOrganigrammeId?: number; typeService?: string; search?: string },
): Promise<ApiResponse<PaginatedData<ApiOrgNode>>> {
  const search = params?.search?.trim();
  const response = await api.get<ApiResponse<PaginatedData<ApiOrgNode>>>("/organigramme", {
    params: {
      page: 1,
      limit: search ? 50 : 200,
      ...(params?.typeOrganigrammeId ? { type_organigramme_id: params.typeOrganigrammeId } : {}),
      // L'API attend le type en majuscules dans ce filtre de recherche
      // (ex: "POSTE"), alors que type_service dans les réponses est "Poste".
      ...(params?.typeService ? { type_service: params.typeService.toUpperCase() } : {}),
      ...(search ? { search } : {}),
    },
  });
  return response.data;
}

// --- Fonctions Types d'organigramme ---

export async function listTypeOrganigrammes(): Promise<ApiResponse<PaginatedData<ApiTypeOrganigramme>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiTypeOrganigramme>>>("/type-organigrammes", {
    params: { page: 1, limit: 200 },
  });
  return response.data;
}

export async function createTypeOrganigramme(
  payload: CreateTypeOrganigrammePayload,
): Promise<ApiResponse<ApiTypeOrganigramme>> {
  const response = await api.post<ApiResponse<ApiTypeOrganigramme>>("/type-organigrammes", payload);
  return response.data;
}

export async function updateTypeOrganigramme(
  id: number,
  payload: UpdateTypeOrganigrammePayload,
): Promise<ApiResponse<ApiTypeOrganigramme>> {
  const response = await api.patch<ApiResponse<ApiTypeOrganigramme>>(`/type-organigrammes/${id}`, payload);
  return response.data;
}

export async function deleteTypeOrganigramme(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/type-organigrammes/${id}`);
  return response.data;
}
