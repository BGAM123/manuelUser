/**
 * Appels API — Gestion des consomptibles.
 *
 * ⚠️ Champs vérifiés contre le Swagger réel (2026-08-19), plusieurs champs
 * précédemment déclarés ici n'existent nulle part côté backend (ni en
 * lecture, ni en écriture) : unite, seuilAlerte, categorie (string),
 * dateAcquisition, valeur, fournisseur. Le vrai contrat utilise category_id/
 * asset_type_id/asset_sub_type_id (relations, comme pour les biens), pas de
 * champs texte libres.
 *
 * ⚠️ quantite : saisie UNIQUEMENT à la création (POST). Absente du schéma
 * PATCH — le backend documente explicitement qu'elle n'est plus modifiable
 * après création (confirmé aussi en pratique : un PATCH avec quantite
 * répond 200 mais n'écrit rien).
 *
 * ⚠️ pieceJointes (sans "s" après "piece") est le nom du champ tel que
 * renvoyé par GET — différent de piecesJointes[] (avec "s") utilisé côté
 * écriture (POST/PATCH). normalizeConsumable() absorbe cette incohérence.
 *
 * 🔴 POST /consumables/{id} (mise à jour) est un NO-OP pour nom/description/
 * category_id/asset_type_id/asset_sub_type_id/pièces jointes — confirmé en
 * re-testant en direct le 2026-08-20 : la réponse dit "succès", `updatedAt`
 * avance, mais rien de tout ça n'est réellement écrit. ConsomptiblesSection.tsx
 * compense en revérifiant la fiche après chaque update plutôt que de faire
 * confiance à la réponse.
 *
 * ✅ EXCEPTION confirmée le 2026-08-25 : `service_id` sur cette même
 * update N'EST PAS un no-op — il alloue réellement du stock du consomptible
 * au service donné (même effet qu'à la création, voir CreateConsumablePayload
 * ci-dessous), et un transfert vers ce service devient alors possible.
 * Testé en direct : consomptible créé sans service → transfert refusé
 * (stockDisponible: 0) → POST /consumables/{id} avec service_id seul →
 * transfert vers ce service accepté. C'est le seul champ qui persiste sur cet
 * endpoint — ne pas généraliser aux autres champs ci-dessus.
 *
 * ⚠️ asset_type_id / asset_sub_type_id : le bug findActiveById (500 sur
 * POST) documenté le 2026-08-19 est CONFIRMÉ CORRIGÉ (re-testé le
 * 2026-08-20 : POST avec category_id=9, asset_type_id=20, asset_sub_type_id=4
 * → 200, les trois relations bien enregistrées et renvoyées). Réactivés
 * dans ConsomptiblesSection.tsx pour la création. Pas testables en édition
 * vu le no-op sur ces champs précis (voir ci-dessus).
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiConsumableRef {
  id: number;
  nom: string;
}

export interface ApiConsumablePieceJointe {
  id: number;
  nom: string;
  chemin?: string;
}

export interface ApiConsumable {
  id: number;
  nom: string;
  description?: string | null;
  quantite: number;
  stockActuel?: number;
  /** Prix unitaire — confirmé en direct (2026-08-20) : `prixInitial` est le vrai nom du champ en écriture (POST), persiste correctement. */
  prixInitial?: number | null;
  /** Prix total — JAMAIS calculé côté backend (confirmé en direct : reste null même avec prixInitial ET quantite renseignés). Calculé côté client dans l'UI (prixInitial × quantite), pas une valeur qu'on envoie au serveur. */
  prixTotal?: number | null;
  category?: ApiConsumableRef | null;
  assetType?: ApiConsumableRef | null;
  assetSubType?: ApiConsumableRef | null;
  service?: { id: number; nom: string; sigle?: string } | null;
  piecesJointes: ApiConsumablePieceJointe[];
  is_delete?: boolean;
  createdAt?: string;
  updatedAt?: string;
}

export interface ListConsumablesParams extends PaginationParams {
  search?: string;
  is_delete?: boolean;
  service_id?: number;
  /**
   * Lève la restriction par défaut de GET /consumables au service de
   * l'utilisateur connecté. Le backend l'ignore silencieusement si
   * l'utilisateur n'a pas un rôle admin (Administrateur / Administrateur
   * patrimonial / Administrateur système) — sûr à envoyer systématiquement,
   * pas besoin de vérifier le rôle côté client.
   */
  all_services?: boolean;
  /** Filtre par catégorie(s) — confirmé en direct (2026-08-29) : GET /consumables?category_ids=26. */
  category_ids?: number | string;
}

/** GET /consumables/{id}/bilan — forme réelle vérifiée en direct (2026-08-19). */
export interface ApiConsumableBilan {
  totalEntrees: number;
  totalTransfere: number;
  stockActuel: number;
  parService: Array<{ serviceId: number; serviceNom: string; quantite: number }>;
}

/** GET /consumables/bilan-global — un bilan par consomptible. */
export interface ApiConsumableBilanGlobalEntry {
  consumable: ApiConsumableRef;
  bilan: ApiConsumableBilan;
}

/** POST /consumables — quantite fixe le stock de départ, jamais modifiable ensuite. */
export interface CreateConsumablePayload {
  nom: string;
  description?: string;
  quantite: number;
  /** Prix unitaire — prixTotal n'existe pas côté écriture (jamais calculé par le backend, voir ApiConsumable.prixTotal). */
  prixInitial?: number;
  category_id?: number;
  asset_type_id?: number;
  asset_sub_type_id?: number;
  service_id?: number;
  piecesJointes?: File[];
  piecesJointesNoms?: string[];
}

/** PATCH /consumables/{id} — quantite et prixInitial envoyés si renseignés (le backend peut les ignorer selon version). */
export interface UpdateConsumablePayload {
  nom?: string;
  description?: string;
  quantite?: number;
  prixInitial?: number;
  category_id?: number | null;
  asset_type_id?: number | null;
  asset_sub_type_id?: number | null;
  service_id?: number | null;
  piecesJointes?: File[];
  piecesJointesNoms?: string[];
}

// ─── Normalisation ───────────────────────────────────────────────────────────

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function normalizeConsumable(raw: any): ApiConsumable {
  return {
    ...raw,
    quantite: raw.quantite != null ? Number(raw.quantite) : 0,
    // Stock réellement disponible (tient compte des transferts, contrairement
    // à `quantite` qui ne bouge qu'à la consommation) — renvoyé en string par
    // le backend (ex. "4800"), d'où le cast explicite.
    stockActuel: raw.stockActuel != null ? Number(raw.stockActuel) : undefined,
    prixInitial: raw.prixInitial != null ? Number(raw.prixInitial) : null,
    prixTotal: raw.prixTotal != null ? Number(raw.prixTotal) : null,
    piecesJointes: raw.pieceJointes ?? raw.piecesJointes ?? [],
  };
}

// ─── Fonctions API ──────────────────────────────────────────────────────────
// Chemins relatifs : passent par l'instance axios partagée (baseURL =
// VITE_API_URL, proxy /api en dev) — cf. src/api/etat-biens/etat-biens.api.ts.

const CONSUMABLES_BASE_URL = "/consumables";

export async function listConsumables(
  params: ListConsumablesParams = {},
): Promise<ApiResponse<PaginatedData<ApiConsumable>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiConsumable>>>(CONSUMABLES_BASE_URL, {
    params,
  });
  return {
    ...response.data,
    data: {
      ...response.data.data,
      data: (response.data.data?.data ?? []).map(normalizeConsumable),
    },
  };
}

export async function getConsumableById(id: number): Promise<ApiResponse<ApiConsumable>> {
  const response = await api.get<ApiResponse<ApiConsumable>>(`${CONSUMABLES_BASE_URL}/${id}`);
  return { ...response.data, data: normalizeConsumable(response.data.data) };
}

export async function getConsumableBilan(
  id: number,
  params: { dateDebut?: string; dateFin?: string } = {},
): Promise<ApiResponse<ApiConsumableBilan>> {
  const response = await api.get<ApiResponse<ApiConsumableBilan>>(
    `${CONSUMABLES_BASE_URL}/${id}/bilan`,
    { params },
  );
  return response.data;
}

export async function getConsumablesBilanGlobal(
  params: { dateDebut?: string; dateFin?: string } = {},
): Promise<ApiResponse<ApiConsumableBilanGlobalEntry[]>> {
  const response = await api.get<ApiResponse<ApiConsumableBilanGlobalEntry[]>>(
    `${CONSUMABLES_BASE_URL}/bilan-global`,
    { params },
  );
  return response.data;
}

function buildFormData(payload: CreateConsumablePayload | UpdateConsumablePayload): FormData {
  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null) return;
    if (key === "piecesJointes" && Array.isArray(value)) {
      value.forEach((file) => formData.append("piecesJointes[]", file));
    } else if (key === "piecesJointesNoms" && Array.isArray(value)) {
      value.forEach((nom) => formData.append("piecesJointesNoms[]", nom));
    } else {
      formData.append(key, String(value));
    }
  });
  return formData;
}
export async function createConsumable(
  payload: CreateConsumablePayload,
): Promise<ApiResponse<ApiConsumable>> {
  const response = await api.post<ApiResponse<ApiConsumable>>(
    CONSUMABLES_BASE_URL,
    buildFormData(payload),
    {
      headers: { "Content-Type": "multipart/form-data" },
    },
  );
  return response.data;
}

export async function updateConsumable(
  id: number,
  payload: UpdateConsumablePayload,
): Promise<ApiResponse<ApiConsumable>> {
  const response = await api.post<ApiResponse<ApiConsumable>>(
    `${CONSUMABLES_BASE_URL}/${id}`,
    buildFormData(payload),
    {
      headers: { "Content-Type": "multipart/form-data" },
    },
  );
  return response.data;
}

/** DELETE /consumables/{id} — soft-delete (is_delete=true), récupérable via restoreConsumable. */
export async function deleteConsumable(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`${CONSUMABLES_BASE_URL}/${id}`);
  return response.data;
}

/** DELETE /consumables/{id}/force — suppression physique, irréversible. */
export async function forceDeleteConsumable(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`${CONSUMABLES_BASE_URL}/${id}/force`);
  return response.data;
}

export async function restoreConsumable(id: number): Promise<ApiResponse<ApiConsumable>> {
  const response = await api.patch<ApiResponse<ApiConsumable>>(
    `${CONSUMABLES_BASE_URL}/${id}/restore`,
    {},
  );
  return response.data;
}

export async function deleteConsumablePieceJointe(
  id: number,
  pieceJointeId: number,
): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(
    `${CONSUMABLES_BASE_URL}/${id}/piece-jointe/${pieceJointeId}`,
  );
  return response.data;
}
