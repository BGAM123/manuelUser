/**
 * Appels API — Localisations géographiques d'un bien (terrain ou bâtiment).
 *
 * Chaque appel à createLocationPoint/createLocationFile AJOUTE à l'historique
 * du bien, rien n'est jamais écrasé :
 *   - GET /assets/{id}/location         → position la plus récente
 *   - GET /assets/{id}/locations-history → historique complet
 *
 * Deux modes de création :
 *   - JSON (createLocationPoint)  : un point saisi directement (lat/lng) → 1 localisation créée
 *   - multipart (createLocationFile) : upload d'un fichier géospatial (CSV, Excel,
 *     GeoJSON, KML, GPX, Shapefile) → 1 localisation créée par géométrie détectée
 *     dans le fichier. Le frontend n'a pas besoin d'interpréter le fichier, le
 *     backend détecte le format et extrait les géométries automatiquement.
 * Limites backend : fichier ≤ 20 Mo, 5000 géométries max par import.
 */

import api from "@/api/axios";
import type { ApiResponse } from "@/api/types";

// ─── Types ─────────────────────────────────────────────────────────────────

export type GeometryType =
  | "Point" | "MultiPoint" | "LineString" | "MultiLineString" | "Polygon" | "MultiPolygon";

export interface ApiAssetLocation {
  id: number;
  /** Coordonnées du point, ou centroïde pour une géométrie non ponctuelle */
  latitude: number;
  longitude: number;
  geometry_type: GeometryType;
  /** Géométrie complète (GeoJSON) — null pour un simple Point */
  geometry: { type: string; coordinates: unknown } | null;
  createdAt?: string;
}

export interface CreateLocationPointPayload {
  latitude: number;
  longitude: number;
}

// ─── Fonctions API ──────────────────────────────────────────────────────────

/** POST /assets/{id}/locations (application/json) — un point saisi directement. */
export async function createLocationPoint(
  assetId: number,
  payload: CreateLocationPointPayload,
): Promise<ApiResponse<ApiAssetLocation>> {
  const response = await api.post<ApiResponse<ApiAssetLocation>>(
    `/assets/${assetId}/locations`,
    payload,
  );
  return response.data;
}

/**
 * POST /assets/{id}/locations (multipart/form-data) — upload d'un fichier
 * géospatial. Retourne un tableau : une entrée par géométrie détectée.
 */
export async function createLocationFile(
  assetId: number,
  file: File,
): Promise<ApiResponse<ApiAssetLocation[]>> {
  const fd = new FormData();
  fd.append("file", file);
  const response = await api.post<ApiResponse<ApiAssetLocation[]>>(
    `/assets/${assetId}/locations`,
    fd,
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data;
}

/** GET /assets/{id}/location — position la plus récente (404 si aucune). */
export async function getCurrentLocation(
  assetId: number,
): Promise<ApiAssetLocation | null> {
  try {
    const response = await api.get<ApiResponse<ApiAssetLocation>>(`/assets/${assetId}/location`);
    return response.data.data;
  } catch (err) {
    const status = (err as { response?: { status?: number } })?.response?.status;
    if (status === 404) return null;
    throw err;
  }
}

/** GET /assets/{id}/locations-history — historique complet, plus récent en premier. */
export async function getLocationsHistory(
  assetId: number,
): Promise<ApiAssetLocation[]> {
  const response = await api.get<ApiResponse<ApiAssetLocation[]>>(`/assets/${assetId}/locations-history`);
  return response.data.data ?? [];
}
