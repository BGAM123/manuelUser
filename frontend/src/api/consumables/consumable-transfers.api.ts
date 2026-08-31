/**
 * Appels API — Transferts de consomptibles (Transfert Direct / BSP).
 *
 * Une seule ressource `ConsumableTransfer` couvre les deux méthodes : le
 * champ `type` ("TRANSFERT_DIRECT" | "BSP") détermine quels champs
 * supplémentaires le backend attend à l'écriture.
 *
 * ⚠️ Écriture ≠ lecture, vérifié en direct le 2026-08-19 (POST/PATCH testés
 * avec de vraies données, nettoyées ensuite) :
 *   - Le détail lu (GET) ne renvoie PAS les champs BSP à plat
 *     (beneficiaire/quantiteDemandee/etc.) — seulement un objet imbriqué
 *     `consumableBsp: { id, bsp: { id, numero, retour, dateRetourEffective,
 *     validateurRetour } }`. Pour obtenir bénéficiaire/quantités d'un BSP
 *     donné, il faut un second appel : GET /bsps/{consumableBsp.bsp.id}
 *     (réutilise bsps.api.ts, même ressource que les BSP de biens).
 *   - Le champ pièces jointes se nomme `pieceJointes` (sans "s" après
 *     "piece") en lecture, `piecesJointes[]` (avec "s") en écriture — même
 *     incohérence que sur /consumables.
 *   - `statut` ("TRANSFERE" pour TRANSFERT_DIRECT, "SORTI" pour BSP) n'est
 *     présent qu'en lecture, jamais envoyé en écriture.
 *   - `by-consumable`/`by-service`/la liste paginée ne renvoient PAS
 *     `consumableBsp` (seul `GET /consumable-transfers/{id}` l'inclut) —
 *     donc tout affichage basé sur ces listes ne peut pas connaître le
 *     numéro de BSP ni son statut de retour sans un appel détail séparé.
 *
 * 🔴 Deux bugs backend confirmés en direct le 2026-08-20 (les 21 endpoints
 * du module consomptibles ont été testés un par un) :
 *   - `PATCH /consumable-transfers/{id}` est un NO-OP TOTAL : répond
 *     "succès", `updatedAt` avance, mais aucun champ n'est réellement
 *     écrit (testé isolément sur consumable_id, service_destination_id,
 *     quantite). Non exposé dans l'UI actuellement (aucune page n'appelle
 *     `updateConsumableTransfer`), donc pas d'impact utilisateur pour
 *     l'instant — à garder en tête si un formulaire d'édition est ajouté.
 *   - `PATCH /consumable-transfers/{id}/bsp/retour` renvoie une 500 :
 *     "Call to undefined method
 *     App\Controller\ConsumableTransfers\ConfirmBspRetourController::
 *     getDoctrine()" — méthode Symfony obsolète (retirée des versions
 *     récentes, le contrôleur doit injecter EntityManagerInterface).
 *     Casse à 100 % pour tout appelant authentifié, pas un souci d'auth.
 *     Rien à corriger côté frontend — `confirmConsumableTransferBspRetour`
 *     reste tel quel, seul le message d'erreur affiché en `onError` a été
 *     rendu réel (voir ConsumableDetailDialog.tsx) pour pointer vers ce
 *     bug plutôt que d'afficher un texte générique.
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export type ConsumableTransferType = "TRANSFERT_DIRECT" | "BSP";
export type ConsumableTransferStatut = "TRANSFERE" | "SORTI";

export interface ApiConsumableTransferPieceJointe {
  id: number;
  nom: string;
  chemin?: string;
}

export interface ApiConsumableTransferBsp {
  id: number;
  bsp: {
    id: number;
    numero: string;
    retour: boolean;
    dateRetourEffective?: string | null;
    validateurRetour?: { id: number; firstName?: string; lastName?: string } | null;
  };
}

export interface ApiConsumableTransferAccuseReception {
  effectue: boolean;
  date?: string | null;
  par?: { id: number; firstName?: string; lastName?: string } | null;
  commentaire?: string | null;
}

export interface ApiConsumableTransfer {
  id: number;
  type: ConsumableTransferType;
  statut?: ConsumableTransferStatut;
  consumable?: { id: number; nom: string };
  serviceDestination?: { id: number; nom: string };
  quantite: number;
  /** Quantité déjà consommée sur ce transfert — renvoyée par l'API, jamais recalculée côté client. */
  quantityConsumed: number;
  /** Accusé de réception confirmé — renvoyé par l'API (voir aussi accuseReception pour le détail). */
  isAcknowledged: boolean;
  accuseReception?: ApiConsumableTransferAccuseReception;
  dateTransfert?: string;
  observations?: string | null;
  pieceJointes: ApiConsumableTransferPieceJointe[];
  consumableBsp?: ApiConsumableTransferBsp | null;
  createdAt?: string;
  updatedAt?: string;
}

export interface CreateConsumableTransferPayload {
  type: ConsumableTransferType;
  consumable_id: number;
  service_destination_id: number;
  quantite: number;
  dateTransfert?: string;
  observations?: string;
  // Requis seulement si type === "BSP"
  service_id?: number;
  beneficiaire_id?: number;
  quantiteDemandee?: number;
  quantiteAccordee?: number;
  quantiteServie?: number;
  dateEtablissement?: string;
  piecesJointes?: File[];
  /** Un nom par fichier, même index que piecesJointes (optionnel). */
  piecesJointesNoms?: string[];
}

/** PATCH /consumable-transfers/{id} — le type et le statut ne sont pas modifiables. */
export interface UpdateConsumableTransferPayload {
  consumable_id?: number;
  service_destination_id?: number;
  quantite?: number;
  dateTransfert?: string;
  observations?: string;
  piecesJointes?: File[];
  piecesJointesNoms?: string[];
}

export interface ListConsumableTransfersParams extends PaginationParams {
  search?: string;
  consumable_id?: number;
  service_id?: number;
  statut?: ConsumableTransferStatut;
}

// ─── Normalisation ───────────────────────────────────────────────────────────

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function normalizeTransfer(raw: any): ApiConsumableTransfer {
  return {
    ...raw,
    quantite: raw.quantite != null ? Number(raw.quantite) : 0,
    quantityConsumed: raw.quantityConsumed != null ? Number(raw.quantityConsumed) : 0,
    isAcknowledged: raw.isAcknowledged ?? raw.accuseReception?.effectue ?? false,
    pieceJointes: raw.pieceJointes ?? raw.piecesJointes ?? [],
  };
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

function buildFormData(
  payload: CreateConsumableTransferPayload | UpdateConsumableTransferPayload,
): FormData {
  const fd = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null) return;
    if (key === "piecesJointes" && Array.isArray(value)) {
      value.forEach((file: File) => fd.append("piecesJointes[]", file));
    } else if (key === "piecesJointesNoms" && Array.isArray(value)) {
      value.forEach((nom: string) => fd.append("piecesJointesNoms[]", nom));
    } else {
      fd.append(key, String(value));
    }
  });
  return fd;
}

export async function listConsumableTransfers(
  params: ListConsumableTransfersParams = {},
): Promise<ApiResponse<PaginatedData<ApiConsumableTransfer>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiConsumableTransfer>>>(
    "/consumable-transfers",
    { params },
  );
  return {
    ...response.data,
    data: {
      ...response.data.data,
      data: (response.data.data?.data ?? []).map(normalizeTransfer),
    },
  };
}

/** GET /consumable-transfers/by-consumable/{id} — tableau brut, pas d'enveloppe paginée. */
export async function listConsumableTransfersByConsumable(
  consumableId: number,
): Promise<ApiConsumableTransfer[]> {
  const response = await api.get<ApiResponse<unknown[]>>(
    `/consumable-transfers/by-consumable/${consumableId}`,
  );
  return (response.data.data ?? []).map(normalizeTransfer);
}

/** GET /consumable-transfers/by-service/{id} — tableau brut, pas d'enveloppe paginée. */
export async function listConsumableTransfersByService(
  serviceId: number,
): Promise<ApiConsumableTransfer[]> {
  const response = await api.get<ApiResponse<unknown[]>>(
    `/consumable-transfers/by-service/${serviceId}`,
  );
  return (response.data.data ?? []).map(normalizeTransfer);
}

export async function getConsumableTransferById(
  id: number,
): Promise<ApiResponse<ApiConsumableTransfer>> {
  const response = await api.get<ApiResponse<ApiConsumableTransfer>>(`/consumable-transfers/${id}`);
  return { ...response.data, data: normalizeTransfer(response.data.data) };
}

/** POST /consumable-transfers — multipart/form-data. */
export async function createConsumableTransfer(
  payload: CreateConsumableTransferPayload,
): Promise<ApiResponse<ApiConsumableTransfer>> {
  const response = await api.post<ApiResponse<ApiConsumableTransfer>>(
    "/consumable-transfers",
    buildFormData(payload),
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data;
}

export async function updateConsumableTransfer(
  id: number,
  payload: UpdateConsumableTransferPayload,
): Promise<ApiResponse<ApiConsumableTransfer>> {
  const response = await api.patch<ApiResponse<ApiConsumableTransfer>>(
    `/consumable-transfers/${id}`,
    buildFormData(payload),
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data;
}

/** DELETE /consumable-transfers/{id} — soft-delete. */
export async function deleteConsumableTransfer(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/consumable-transfers/${id}`);
  return response.data;
}

/** DELETE /consumable-transfers/{id}/force — suppression physique, irréversible. */
export async function forceDeleteConsumableTransfer(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/consumable-transfers/${id}/force`);
  return response.data;
}

export async function restoreConsumableTransfer(
  id: number,
): Promise<ApiResponse<ApiConsumableTransfer>> {
  const response = await api.patch<ApiResponse<ApiConsumableTransfer>>(
    `/consumable-transfers/${id}/restore`,
    {},
  );
  return response.data;
}

/** PATCH /consumable-transfers/{id}/bsp/retour — confirme le retour d'un BSP (uniquement type BSP). */
export async function confirmConsumableTransferBspRetour(
  id: number,
): Promise<ApiResponse<ApiConsumableTransfer>> {
  const response = await api.patch<ApiResponse<ApiConsumableTransfer>>(
    `/consumable-transfers/${id}/bsp/retour`,
    {},
  );
  return response.data;
}

/**
 * PATCH /consumable-transfers/{id}/consume — pose la quantité consommée sur
 * un transfert (valeur ABSOLUE, pas un incrément). Contrainte serveur :
 * quantityConsumed <= quantite (la quantité reçue). Confirmé via le schéma
 * OpenAPI réel (doc.json) le 2026-08-25 — l'ancien nom/verbe/payload utilisé
 * ici (POST .../consommation, { quantite }) n'existait pas côté backend.
 */
export async function consumeConsumableTransfer(
  id: number,
  quantityConsumed: number,
): Promise<ApiResponse<ApiConsumableTransfer>> {
  const response = await api.patch<ApiResponse<ApiConsumableTransfer>>(
    `/consumable-transfers/${id}/consume`,
    { quantityConsumed },
  );
  return response.data;
}

/**
 * POST /consumable-transfers/acknowledge-batch — accuse réception de
 * plusieurs transferts en une seule transaction (tout ou rien : si un seul
 * id échoue, aucun n'est validé). À préférer à des appels un par un.
 */
export async function acknowledgeConsumableTransfersBatch(
  transferIds: number[],
  commentaire?: string,
): Promise<ApiResponse<{ acknowledged: number[] }>> {
  const response = await api.post<ApiResponse<{ acknowledged: number[] }>>(
    "/consumable-transfers/acknowledge-batch",
    { transfer_ids: transferIds, commentaire },
  );
  return response.data;
}

export async function deleteConsumableTransferPieceJointe(
  id: number,
  pieceJointeId: number,
): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(
    `/consumable-transfers/${id}/piece-jointe/${pieceJointeId}`,
  );
  return response.data;
}
