/**
 * GET /asset-assignments — listing des biens affectés à l'utilisateur
 * connecté (utilisateurs non-administrateurs uniquement). Remplace GET
 * /assets comme source de données pour BiensList quand l'utilisateur
 * connecté n'a pas de rôle admin (demande explicite, 2026-08-29) :
 *   - Un utilisateur simple ne voit plus un bien après l'avoir cédé (le
 *     backend ne retourne que les affectations dont il est encore le
 *     détenteur actuel).
 *   - Chaque item embarque `assignmentId` (id de l'affectation — nécessaire
 *     pour POST /asset-assignments/{assignmentId}/acknowledge) en plus de
 *     `id` (id du bien — utilisé pour toutes les AUTRES actions, comme sur
 *     GET /assets), `detenteur` (bool) et `received` (bool | null).
 *
 * Les items bruts utilisent exactement les mêmes noms de champs que
 * GET /assets (categorie/typeBien/structure/responsable/etc.) — confirmé en
 * comparant les deux réponses documentées — donc normalizeBien() (exportée
 * depuis biens.api.ts) s'applique ici sans changement.
 *
 * Filtres : mêmes paramètres que GET /assets (confirmé explicitement,
 * 2026-08-29) — voir ListBiensParams pour le détail de chacun.
 */

import api from "../axios";
import type { ApiResponse, PaginatedData, PaginatedMeta } from "../types";
import { normalizeBien, type ApiBien, type ListBiensParams } from "./biens.api";

export async function listAssetAssignmentsPage(
  params: ListBiensParams = {},
): Promise<{ data: ApiBien[]; meta: PaginatedMeta }> {
  const response = await api.get<ApiResponse<PaginatedData<Record<string, unknown> & { assignmentId: number; detenteur?: boolean }>>>(
    "/asset-assignments",
    { params: { page: 1, limit: 10, is_delete: false, ...params } },
  );
  const payload = response.data.data;
  const rawItems = payload?.data ?? [];
  return {
    data: rawItems.map((raw) => ({
      ...normalizeBien(raw),
      assignmentId: raw.assignmentId,
      detenteur: raw.detenteur ?? undefined,
    })),
    meta: payload?.meta ?? { current_page: params.page ?? 1, limit: params.limit ?? 10, total_items: 0, total_pages: 1 },
  };
}
