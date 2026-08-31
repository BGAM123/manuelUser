/**
 * Appels API — BSP (Bons de Sortie Provisoire).
 *
 * Documents de traçabilité rattachés à une sortie de bien (AssetExit) : un
 * AssetExit peut être matérialisé par plusieurs BSP, un par bénéficiaire.
 *
 * Le "bénéficiaire" (beneficiaire_id) et le "service" (service_id) sont deux
 * champs indépendants côté backend — service_id retombe sur celui de la
 * sortie si non précisé. Les deux sont facultatifs, y compris en création
 * (seul quantiteServie est obligatoire).
 */

import api from "@/api/axios";
import type { ApiResponse } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiBspPieceJointe {
  id: number;
  nom: string;
  chemin: string;
}

export interface ApiBspBeneficiaire {
  id: number;
  nom: string;
  prenom: string;
}

export interface ApiBsp {
  id: number;
  numero: string;
  quantiteDemandee?: number | null;
  quantiteAccordee?: number | null;
  quantiteServie?: number;
  observations?: string;
  dateEtablissement?: string;
  service?: { id: number; nom: string } | null;
  beneficiaire?: ApiBspBeneficiaire | null;
  piecesJointes?: ApiBspPieceJointe[];
  createdAt?: string;
}

export interface CreateBspPayload {
  service_id?: number;
  beneficiaire_id?: number;
  quantiteDemandee?: number;
  quantiteAccordee?: number;
  quantiteServie: number;
  observations?: string;
  dateEtablissement?: string;
  piecesJointes?: File[];
  /** Un nom par fichier, même index que piecesJointes (optionnel). */
  piecesJointesNoms?: string[];
}

export type UpdateBspPayload = Partial<CreateBspPayload>;

// ─── Fonctions API ──────────────────────────────────────────────────────────

function buildFormData(payload: CreateBspPayload | UpdateBspPayload): FormData {
  const fd = new FormData();
  if (payload.service_id != null) fd.append("service_id", String(payload.service_id));
  if (payload.beneficiaire_id != null) fd.append("beneficiaire_id", String(payload.beneficiaire_id));
  if (payload.quantiteDemandee != null) fd.append("quantiteDemandee", String(payload.quantiteDemandee));
  if (payload.quantiteAccordee != null) fd.append("quantiteAccordee", String(payload.quantiteAccordee));
  if (payload.quantiteServie != null) fd.append("quantiteServie", String(payload.quantiteServie));
  if (payload.observations) fd.append("observations", payload.observations);
  if (payload.dateEtablissement) fd.append("dateEtablissement", payload.dateEtablissement);
  (payload.piecesJointes ?? []).forEach((file, i) => {
    fd.append(`piecesJointes[${i}]`, file);
    const nom = payload.piecesJointesNoms?.[i];
    if (nom) fd.append(`piecesJointesNoms[${i}]`, nom);
  });
  return fd;
}

/** GET /asset-exits/{id}/bsps — BSP actifs rattachés à une sortie. */
export async function listBspsByExit(exitId: number): Promise<ApiBsp[]> {
  const response = await api.get<ApiResponse<ApiBsp[]>>(`/asset-exits/${exitId}/bsps`);
  return response.data.data ?? [];
}

/** POST /asset-exits/{id}/bsps — Créer un BSP pour une sortie (multipart/form-data). */
export async function createBsp(exitId: number, payload: CreateBspPayload): Promise<ApiBsp> {
  const response = await api.post<ApiResponse<ApiBsp>>(
    `/asset-exits/${exitId}/bsps`,
    buildFormData(payload),
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data.data;
}

export async function getBspById(id: number): Promise<ApiBsp> {
  const response = await api.get<ApiResponse<ApiBsp>>(`/bsps/${id}`);
  return response.data.data;
}

/** PUT /bsps/{id} — tous les champs sont facultatifs (mise à jour partielle). */
export async function updateBsp(id: number, payload: UpdateBspPayload): Promise<ApiBsp> {
  const response = await api.put<ApiResponse<ApiBsp>>(
    `/bsps/${id}`,
    buildFormData(payload),
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data.data;
}

/** DELETE /bsps/{id} — Soft-delete, la sortie parente n'est pas affectée. */
export async function deleteBsp(id: number): Promise<void> {
  await api.delete(`/bsps/${id}`);
}

/** DELETE /bsps/{bspId}/attachments/{attachmentId} */
export async function deleteBspAttachment(bspId: number, attachmentId: number): Promise<void> {
  await api.delete(`/bsps/${bspId}/attachments/${attachmentId}`);
}
