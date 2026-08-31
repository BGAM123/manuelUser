/**
 * Helpers et utilitaires pour les appels API et exports du module Statistiques.
 */

import api from "@/api/axios";
import type { StatisticsFilter } from "./statistiques.types";

/**
 * Sérialise un objet de filtres en chaîne de requête URL (query string).
 * Les tableaux sont convertis en listes séparées par des virgules (format attendu par Symfony).
 */
export function toQueryString(filter: StatisticsFilter = {}): string {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(filter)) {
    if (value === undefined || value === null || value === "") continue;

    if (Array.isArray(value)) {
      if (value.length > 0) {
        params.set(key, value.join(","));
      }
    } else {
      params.set(key, String(value));
    }
  }

  return params.toString();
}

/**
 * Télécharge un export statistique binaire (XLSX ou PDF) depuis l'API Symfony.
 * Gère le blob, l'en-tête Content-Disposition et le déclenchement du téléchargement navigateur.
 *
 * @param path Chemin de l'endpoint (ex: '/api/stats/vue-globale')
 * @param filter Filtres appliqués
 * @param format Format de sortie : 'xlsx' | 'pdf'
 * @param defaultFilename Nom de secours si non fourni par le serveur
 */
export async function downloadStatsExport(
  path: string,
  filter: StatisticsFilter = {},
  format: "xlsx" | "pdf",
  defaultFilename?: string,
): Promise<void> {
  const qs = toQueryString({ ...filter });
  const separator = path.includes("?") ? "&" : "?";
  const url = `${path}${separator}${qs ? `${qs}&` : ""}format=${format}`;

  const response = await api.get(url, {
    responseType: "blob",
  });

  const contentDisposition = response.headers["content-disposition"] as
    | string
    | undefined;

  let filename = defaultFilename;
  if (contentDisposition) {
    const match = contentDisposition.match(/filename="?([^";]+)"?/i);
    if (match?.[1]) {
      filename = match[1];
    }
  }

  if (!filename) {
    const timestamp = new Date().toISOString().slice(0, 10);
    filename = `Statistiques_MINEPIA_${timestamp}.${format}`;
  }

  const blob = new Blob([response.data as BlobPart], {
    type:
      format === "xlsx"
        ? "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        : "application/pdf",
  });

  const blobUrl = window.URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = blobUrl;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  window.URL.revokeObjectURL(blobUrl);
}

/**
 * Valide et formate une date au format requis YYYY-MM-DD.
 */
export function sanitizeDateYYYYMMDD(dateInput?: string | Date | null): string | undefined {
  if (!dateInput) return undefined;
  if (dateInput instanceof Date) {
    return dateInput.toISOString().slice(0, 10);
  }
  if (typeof dateInput === "string") {
    // Vérification du format strict YYYY-MM-DD
    const regex = /^\d{4}-\d{2}-\d{2}$/;
    if (regex.test(dateInput)) return dateInput;
    const parsed = new Date(dateInput);
    if (!isNaN(parsed.getTime())) {
      return parsed.toISOString().slice(0, 10);
    }
  }
  return undefined;
}
