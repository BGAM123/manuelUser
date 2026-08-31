/**
 * Appels API — Gestion des états des biens.
 * Endpoints: GET/POST /etat-biens, PATCH /etat-biens/{id},
 *            POST /etat-biens/{id}/restore, DELETE /etat-biens/{id}/soft-delete
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiEtatBien {
  id: number;
  nom: string;
  /** Numéro d'ordre pour le tri (optionnel). */
  numeroOrdre?: number;
  /** Description détaillée de l'état (optionnel). */
  description?: string;
  is_delete: boolean;
  assetTypes: Array<{ id: number; nom: string }>;
}

export interface ListEtatBiensParams extends PaginationParams {
  search?: string;
  is_delete?: boolean;
  asset_type_id?: number;
}

export interface EtatBienPayload {
  nom: string;
  /** Numéro d'ordre pour le tri (optionnel). */
  numeroOrdre?: number;
  /** Description détaillée de l'état (optionnel). */
  description?: string;
  /** Remplace complètement la liste — envoi de la liste entière à jour. */
  asset_type_ids: number[];
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

export async function listEtatBiens(
  params: ListEtatBiensParams = {},
): Promise<ApiResponse<PaginatedData<ApiEtatBien>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiEtatBien>>>("/etat-biens", { params });
  return response.data;
}

export async function getEtatBienById(id: number): Promise<ApiResponse<ApiEtatBien>> {
  const response = await api.get<ApiResponse<ApiEtatBien>>(`/etat-biens/${id}`);
  return response.data;
}

export async function createEtatBien(
  payload: EtatBienPayload,
): Promise<ApiResponse<ApiEtatBien>> {
  const response = await api.post<ApiResponse<ApiEtatBien>>("/etat-biens", payload);
  return response.data;
}

/** PATCH — synchronise complètement asset_type_ids (remplacement, pas ajout). */
export async function updateEtatBien(
  id: number,
  payload: EtatBienPayload,
): Promise<ApiResponse<ApiEtatBien>> {
  const response = await api.patch<ApiResponse<ApiEtatBien>>(`/etat-biens/${id}`, payload);
  return response.data;
}

export async function restoreEtatBien(id: number): Promise<ApiResponse<ApiEtatBien>> {
  const response = await api.post<ApiResponse<ApiEtatBien>>(`/etat-biens/${id}/restore`, {});
  return response.data;
}

export async function softDeleteEtatBien(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/etat-biens/${id}/soft-delete`);
  return response.data;
}
