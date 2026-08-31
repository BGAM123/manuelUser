/**
 * Appels API — Gestion des sécurisations de biens patrimoniaux (table securities).
 * Un ou plusieurs biens (asset_ids) peuvent être sécurisés en une seule
 * opération (mode Physique ou Juridique), avec date, position (lat/lng
 * optionnelle) et pièces jointes.
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export type SecurityMode = "Physique" | "Juridique";

export interface ApiSecurityLocation {
  id: number;
  latitude: number;
  longitude: number;
}

export interface ApiSecurityAsset {
  id: number;
  reference?: string;
  nom?: string;
  code?: string;
}

export interface ApiSecurityDocument {
  id?: number;
  nom: string;
  chemin: string;
}

export interface ApiSecurity {
  id: number;
  securityMode: SecurityMode | string;
  dateSecurisation?: string;
  location?: ApiSecurityLocation | null;
  assets: ApiSecurityAsset[];
  documents?: ApiSecurityDocument[];
  createdAt?: string;
  updatedAt?: string;
}

export interface ListSecuritiesParams extends PaginationParams {
  search?: string;
}

export interface CreateSecurityPayload {
  asset_ids: number[];
  security_mode: SecurityMode;
  date_securisation?: string;
  latitude?: number;
  longitude?: number;
  piecesJointes?: File[];
  /** Un nom par fichier, même index que piecesJointes */
  piecesJointesNoms?: string[];
}

export type UpdateSecurityPayload = Partial<CreateSecurityPayload>;

// ─── Fonctions API ──────────────────────────────────────────────────────────
// Chemin relatif — passe par l'instance `api` (baseURL = VITE_API_URL, "/api"
// en dev → proxifié par Vite vers le VPS pour éviter les erreurs CORS, cf.
// vite.config.ts et src/api/biens/asset-exits.api.ts). Ne PAS coder en dur
// l'URL absolue du VPS ici : en dev cela contourne le proxy et le navigateur
// bloque la requête cross-origin (CORS), ce qui provoquait l'échec silencieux
// de la sécurisation.

const SECURITIES_PATH = "/securities";

function buildFormData(payload: CreateSecurityPayload | UpdateSecurityPayload): FormData {
  const fd = new FormData();
  payload.asset_ids?.forEach((id) => fd.append("asset_ids[]", String(id)));
  if (payload.security_mode) fd.append("security_mode", payload.security_mode);
  if (payload.date_securisation) fd.append("date_securisation", payload.date_securisation);
  if (payload.latitude != null) fd.append("latitude", String(payload.latitude));
  if (payload.longitude != null) fd.append("longitude", String(payload.longitude));
  (payload.piecesJointes ?? []).forEach((file) => fd.append("piecesJointes[]", file));
  (payload.piecesJointesNoms ?? []).forEach((nom) => fd.append("piecesJointesNoms[]", nom));
  return fd;
}

/** GET /securities — Liste les sécurisations. */
export async function listSecurities(
  params: ListSecuritiesParams = {},
): Promise<ApiResponse<PaginatedData<ApiSecurity>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiSecurity>>>(SECURITIES_PATH, { params });
  return response.data;
}

/** GET /securities/{id} — Détail d'une sécurisation. */
export async function getSecurityById(id: number): Promise<ApiResponse<ApiSecurity>> {
  const response = await api.get<ApiResponse<ApiSecurity>>(`${SECURITIES_PATH}/${id}`);
  return response.data;
}

/** POST /securities — Créer une sécurisation (multipart/form-data). */
export async function createSecurity(payload: CreateSecurityPayload): Promise<ApiResponse<ApiSecurity>> {
  const response = await api.post<ApiResponse<ApiSecurity>>(SECURITIES_PATH, buildFormData(payload), {
    headers: { "Content-Type": "multipart/form-data" },
  });
  return response.data;
}

/** POST /securities/{id} — Modifier une sécurisation (multipart/form-data). */
export async function updateSecurity(
  id: number,
  payload: UpdateSecurityPayload,
): Promise<ApiResponse<ApiSecurity>> {
  const response = await api.post<ApiResponse<ApiSecurity>>(`${SECURITIES_PATH}/${id}`, buildFormData(payload), {
    headers: { "Content-Type": "multipart/form-data" },
  });
  return response.data;
}

/** DELETE /securities/{id} — Supprimer une sécurisation. */
export async function deleteSecurity(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`${SECURITIES_PATH}/${id}`);
  return response.data;
}
