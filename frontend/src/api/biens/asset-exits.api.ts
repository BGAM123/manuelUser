/**
 * Appels API — Sorties de biens (vente, don, réforme, destruction, etc.).
 *
 * Un bien ne peut avoir qu'UNE seule sortie (400 "Ce bien possède déjà une
 * sortie" en cas de doublon). `reforme: true` est un raccourci backend qui
 * force automatiquement statut=SORTIS, état=Réformé et motifSortie=REFORME.
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData, PaginationParams } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export interface ApiPieceJointeExit {
  id: number;
  nom: string;
  chemin: string;
}

export interface ApiAssetExit {
  id: number;
  exitType?: { id: number; nom: string; code: string };
  motifSortie?: string;
  dateSortie?: string;
  protocoleReference?: string;
  observations?: string;
  asset?: { id: number; reference: string; nom: string };
  service?: { id: number; nom: string };
  piecesJointes?: ApiPieceJointeExit[];
  bspsCount?: number;
  createdAt?: string;
}

export interface CreateAssetExitPayload {
  asset_id: number;
  service_id?: number;
  /** Texte libre côté backend malgré la liste suggérée (REFORME, DON, CESSION, PERTE, VOL, DESTRUCTION, LITIGE, MISE_AU_REBUT, AUTRE) */
  motifSortie?: string;
  exit_type_id?: number;
  /** Si true : statut du bien -> SORTIS, état -> Réformé, motifSortie -> REFORME (automatique côté backend) */
  reforme?: boolean;
  dateSortie?: string;
  protocoleReference?: string;
  observations?: string;
  piecesJointes?: File[];
  /** Un nom par fichier, même index que piecesJointes (optionnel — sinon nom du fichier utilisé) */
  piecesJointesNoms?: string[];
}

export type UpdateAssetExitPayload = Omit<CreateAssetExitPayload, "asset_id">;

// ─── Fonctions API ──────────────────────────────────────────────────────────

export interface ListAssetExitsParams extends PaginationParams {
  asset_id?: number;
}

/**
 * GET /asset-exits — Liste les sorties enregistrées (table AssetExit).
 * Utilisé par le panneau admin des demandes de réforme : les demandes en
 * attente sont les sorties REFORME dont le bien n'est pas encore "Réformé".
 *
 * Le backend peut renvoyer la liste sous différents formats :
 *   - tableau nu,
 *   - PaginatedData standard { meta, data },
 *   - wrapper { items, pagination } (comme /exit-types),
 *   - wrapper { data } imbriqué.
 * On tente toutes les formes connues, puis on scanne le payload à la
 * recherche du premier tableau d'objets (dernier recours). En cas
 * d'incompréhension, on log la réponse brute pour faciliter le diagnostic
 * (visible dans la console DevTools, F12).
 */
export async function listAssetExits(
  params: ListAssetExitsParams = {},
): Promise<ApiAssetExit[]> {
  const response = await api.get<ApiResponse<unknown>>(
    "/asset-exits",
    { params: { page: 1, limit: 200, ...params } },
  );
  const payload = response.data.data;

  // Forme 1 : tableau nu.
  if (Array.isArray(payload)) return payload as ApiAssetExit[];

  if (payload && typeof payload === "object") {
    // Forme 2 : wrapper { items: [...] } (comme /exit-types).
    const items = (payload as { items?: unknown }).items;
    if (Array.isArray(items)) return items as ApiAssetExit[];

    // Forme 3 : wrapper { data: [...] } (autre format paginé).
    const data = (payload as { data?: unknown }).data;
    if (Array.isArray(data)) return data as ApiAssetExit[];

    // Forme 4 : PaginatedData { meta, data }.
    const paginatedData = (payload as PaginatedData<ApiAssetExit>).data;
    if (Array.isArray(paginatedData)) return paginatedData;

    // Dernier recours : scanner le payload à la recherche du premier
    // tableau d'objets (au cas où le backend imbrique encore plus).
    for (const value of Object.values(payload as Record<string, unknown>)) {
      if (Array.isArray(value) && value.every((v) => v && typeof v === "object")) {
        return value as ApiAssetExit[];
      }
    }
  }

  // Diagnostic : log la réponse brute pour comprendre le format réel.
  // Visible dans la console DevTools (F12 → onglet Console).
  // eslint-disable-next-line no-console
  console.warn("[listAssetExits] Format de réponse non reconnu :", { payload, raw: response.data });
  return [];
}

function buildFormData(payload: CreateAssetExitPayload | UpdateAssetExitPayload): FormData {
  const fd = new FormData();
  if ("asset_id" in payload && payload.asset_id) fd.append("asset_id", String(payload.asset_id));
  if (payload.service_id != null) fd.append("service_id", String(payload.service_id));
  if (payload.motifSortie) fd.append("motifSortie", payload.motifSortie);
  if (payload.exit_type_id != null) fd.append("exit_type_id", String(payload.exit_type_id));
  if (payload.reforme != null) fd.append("reforme", String(payload.reforme));
  if (payload.dateSortie) fd.append("dateSortie", payload.dateSortie);
  if (payload.protocoleReference) fd.append("protocoleReference", payload.protocoleReference);
  if (payload.observations) fd.append("observations", payload.observations);
  (payload.piecesJointes ?? []).forEach((file, i) => {
    fd.append(`piecesJointes[${i}]`, file);
    const nom = payload.piecesJointesNoms?.[i];
    if (nom) fd.append(`piecesJointesNoms[${i}]`, nom);
  });
  return fd;
}

/** POST /asset-exits — Créer une sortie de bien (multipart/form-data). */
export async function createAssetExit(
  payload: CreateAssetExitPayload,
): Promise<ApiResponse<ApiAssetExit>> {
  const response = await api.post<ApiResponse<ApiAssetExit>>(
    "/asset-exits",
    buildFormData(payload),
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data;
}

/** PUT /asset-exits/{id} — Modifier une sortie de bien (multipart/form-data). */
export async function updateAssetExit(
  id: number,
  payload: UpdateAssetExitPayload,
): Promise<ApiResponse<ApiAssetExit>> {
  const response = await api.put<ApiResponse<ApiAssetExit>>(
    `/asset-exits/${id}`,
    buildFormData(payload),
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data;
}

export async function getAssetExitById(id: number): Promise<ApiResponse<ApiAssetExit>> {
  const response = await api.get<ApiResponse<ApiAssetExit>>(`/asset-exits/${id}`);
  return response.data;
}

/** GET /assets/{id}/exit — Sortie (éventuelle) d'un bien donné. */
export async function getAssetExit(
  assetId: number,
): Promise<ApiResponse<{ asset: { id: number; reference: string; nom: string }; sortie: ApiAssetExit | null }>> {
  const response = await api.get(`/assets/${assetId}/exit`);
  return response.data;
}

export async function deleteAssetExit(id: number): Promise<ApiResponse<null>> {
  const response = await api.delete<ApiResponse<null>>(`/asset-exits/${id}`);
  return response.data;
}
