/**
 * Instance Axios centralisée avec gestion des tokens JWT.
 *
 * Intercepteur de requête :
 *   - Injecte le Bearer token dans chaque requête.
 *   - Si le token expire bientôt → refresh préventif avant l'envoi.
 *
 * Intercepteur de réponse :
 *   - En cas de 401 → tente un refresh puis rejoue la requête originale.
 *   - Si le refresh échoue → déconnexion forcée.
 *
 * refreshToken() utilise un système de subscribers pour éviter les
 * appels parallèles (une seule requête de refresh à la fois).
 */

import axios, {
  type AxiosRequestConfig,
  type InternalAxiosRequestConfig,
} from "axios";
import { isTokenExpiringSoon, isTokenExpired } from "@/utils/jwt";
import { queryClient } from "@/api/queryClient";

// ─── Clés localStorage ─────────────────────────────────────────────────────
export const TOKEN_KEY = "minepia_token";
export const REFRESH_TOKEN_KEY = "minepia_refresh_token";
export const USER_KEY = "minepia_user";

// ─── Instance Axios ────────────────────────────────────────────────────────
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL as string,
  headers: { "Content-Type": "application/json" },
});

// ─── État interne du refresh ───────────────────────────────────────────────
let isRefreshing = false;
let subscribers: Array<(token: string) => void> = [];

function addSubscriber(cb: (token: string) => void) {
  subscribers.push(cb);
}

function notifySubscribers(token: string) {
  subscribers.forEach((cb) => cb(token));
  subscribers = [];
}

// ─── Logique de rafraîchissement ───────────────────────────────────────────
async function refreshToken(): Promise<string> {
  const storedRefresh = localStorage.getItem(REFRESH_TOKEN_KEY);
  if (!storedRefresh) throw new Error("No refresh token");

  const response = await axios.post<{
    success: boolean;
    data: { token: string; refresh_token: string };
  }>(`${import.meta.env.VITE_API_URL as string}/refresh_token`, {
    refresh_token: storedRefresh,
  });

  const { token, refresh_token } = response.data.data;
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(REFRESH_TOKEN_KEY, refresh_token);

  // Mettre à jour également les données user (optionnel — déclenche un /profile si nécessaire)
  return token;
}

function forceLogout() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(REFRESH_TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
  window.location.replace("/authentification");
}

// ─── Intercepteur de requête ───────────────────────────────────────────────
api.interceptors.request.use(
  async (config: InternalAxiosRequestConfig) => {
    // Routes publiques — ne pas injecter de token
    const publicPaths = ["/login_check", "/refresh_token"];
    if (publicPaths.some((p) => config.url?.includes(p))) return config;

    const token = localStorage.getItem(TOKEN_KEY);

    if (token && isTokenExpiringSoon(token)) {
      // Refresh préventif
      if (!isRefreshing) {
        isRefreshing = true;
        try {
          const newToken = await refreshToken();
          isRefreshing = false;
          notifySubscribers(newToken);
          config.headers.Authorization = `Bearer ${newToken}`;
        } catch {
          isRefreshing = false;
          subscribers = [];
          forceLogout();
          return Promise.reject(new Error("Session expirée."));
        }
      } else {
        // Attendre que le refresh en cours se termine
        const newToken = await new Promise<string>((resolve) => {
          addSubscriber(resolve);
        });
        config.headers.Authorization = `Bearer ${newToken}`;
      }
    } else if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
  },
  (error) => Promise.reject(error),
);

// Méthodes qui modifient des données côté serveur — après chaque
// enregistrement réussi (création/modification/suppression), on invalide
// toutes les requêtes actives pour que les pages affichées se rafraîchissent
// automatiquement, sans dépendre d'un invalidateQueries() ciblé oublié dans
// tel ou tel appelant (demande explicite 2026-08-29 : "actualiser les
// données et les pages après chaque enregistrement dans le système").
// N'invalide réellement (refetch) que les requêtes actuellement à l'écran —
// les autres sont juste marquées périmées, refetchées à leur prochain
// montage grâce à refetchOnMount: "always" (voir queryClient.ts).
const MUTATING_METHODS = new Set(["post", "put", "patch", "delete"]);

// ─── Intercepteur de réponse ───────────────────────────────────────────────
api.interceptors.response.use(
  (response) => {
    const method = response.config.method?.toLowerCase();
    if (method && MUTATING_METHODS.has(method)) {
      queryClient.invalidateQueries();
    }
    return response;
  },
  async (error) => {
    const originalRequest = error.config as AxiosRequestConfig & { _retry?: boolean };

    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;

      if (!isRefreshing) {
        isRefreshing = true;
        try {
          const newToken = await refreshToken();
          isRefreshing = false;
          notifySubscribers(newToken);
          if (originalRequest.headers) {
            (originalRequest.headers as Record<string, string>).Authorization = `Bearer ${newToken}`;
          }
          return api(originalRequest);
        } catch {
          isRefreshing = false;
          subscribers = [];
          forceLogout();
          return Promise.reject(error);
        }
      }

      // Attendre le refresh en cours puis rejouer
      return new Promise((resolve, reject) => {
        addSubscriber((newToken) => {
          if (originalRequest.headers) {
            (originalRequest.headers as Record<string, string>).Authorization = `Bearer ${newToken}`;
          }
          resolve(api(originalRequest));
        });
        // Timeout de sécurité
        setTimeout(() => reject(new Error("Refresh timeout")), 10_000);
      });
    }

    return Promise.reject(error);
  },
);

export default api;
