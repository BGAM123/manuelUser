/**
 * Appels API — Gestion des projets patrimoniaux.
 * Note: l'endpoint /projects retourne une structure différente de l'enveloppe standard
 * ({data: [...], pagination: {...}} au lieu de {data: {meta: ..., data: [...]}}).
 */

import api from "@/api/axios";
import type { ApiResponse } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiProjectResponsable {
  id: number;
  firstName: string;
  lastName: string;
  email?: string;
}

export type ProjectStatut = "PLANIFIE" | "EN_COURS" | "TERMINE";

export interface ApiProject {
  id: number;
  nom: string;
  description?: string;
  exercice: number | string;
  /** Champ retourné en camelCase par l'API Symfony */
  dateDebut: string;
  dateFinPrevue: string | null;
  statut: ProjectStatut;
  responsables: ApiProjectResponsable[];
  createdAt?: string;
  updatedAt?: string;
}

export interface ProjectPagination {
  page: number;
  limit: number;
  total: number;
  pages: number;
}

/** Structure interne retournée par PaginationFactory (champ `data` de l'enveloppe ApiResponse). */
export interface ProjectsListResponse {
  data: ApiProject[];
  pagination: ProjectPagination;
}

export interface ListProjectsParams {
  page?: number;
  limit?: number;
  exercice?: number | string;
  q?: string;
  statut?: ProjectStatut;
  is_delete?: boolean;
}

export interface CreateProjectPayload {
  nom: string;
  description?: string;
  exercice: number;
  date_debut: string;
  date_fin_prevue?: string;
  statut?: ProjectStatut;
  responsable_ids?: number[];
}

export interface UpdateProjectPayload {
  nom?: string;
  description?: string;
  exercice?: number;
  date_debut?: string;
  date_fin_prevue?: string;
  statut?: ProjectStatut;
  responsable_ids?: number[];
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function listProjects(
  params: ListProjectsParams = {},
): Promise<ProjectsListResponse> {
  const response = await api.get<ApiResponse<ProjectsListResponse>>("/projects", { params });
  return response.data.data;
}

export async function getProjectById(id: number): Promise<ApiResponse<ApiProject>> {
  const response = await api.get<ApiResponse<ApiProject>>(`/projects/${id}`);
  return response.data;
}

export async function createProject(
  payload: CreateProjectPayload,
): Promise<ApiResponse<ApiProject>> {
  const response = await api.post<ApiResponse<ApiProject>>("/projects", payload);
  return response.data;
}

export async function updateProject(
  id: number,
  payload: UpdateProjectPayload,
): Promise<ApiResponse<ApiProject>> {
  const response = await api.put<ApiResponse<ApiProject>>(`/projects/${id}`, payload);
  return response.data;
}

export async function deleteProject(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/projects/${id}`);
  return response.data;
}

export async function softDeleteProject(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/projects/${id}/soft-delete`);
  return response.data;
}

/** PUT /projects/{id}/restore — confirmé le 2026-08-21 (405 sur PATCH/POST, "Allow: PUT"). */
export async function restoreProject(id: number): Promise<ApiResponse<null>> {
  const response = await api.put<ApiResponse<null>>(`/projects/${id}/restore`, {});
  return response.data;
}

export async function updateProjectStatus(
  id: number,
  statut: ProjectStatut,
): Promise<ApiResponse<{ id: number; nom: string; statut: ProjectStatut }>> {
  const response = await api.patch<
    ApiResponse<{ id: number; nom: string; statut: ProjectStatut }>
  >(`/projects/${id}/status`, { statut });
  return response.data;
}
