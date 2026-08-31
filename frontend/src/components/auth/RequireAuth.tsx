/**
 * Gardien des routes privées.
 *
 * - Vérifie la présence et la validité du token JWT.
 * - Si non authentifié → redirige vers /authentification et nettoie le localStorage.
 * - Active useHeartbeat() et useAutoLogout() uniquement pour les pages protégées.
 */

import { type ReactNode } from "react";
import { Navigate } from "react-router-dom";
import { TOKEN_KEY } from "@/api/axios";
import { isTokenExpired } from "@/utils/jwt";
import { logoutApi } from "@/api/authentication/auth.api";
import { useAutoLogout } from "@/hooks/useAutoLogout";
import { useHeartbeat } from "@/hooks/useHeartbeat";

function SessionGuard({ children }: { children: ReactNode }) {
  useHeartbeat();
  useAutoLogout();
  return <>{children}</>;
}

export function RequireAuth({ children }: { children: ReactNode }) {
  const token = localStorage.getItem(TOKEN_KEY);

  if (!token || isTokenExpired(token)) {
    logoutApi();
    return <Navigate to="/authentification" replace />;
  }

  return <SessionGuard>{children}</SessionGuard>;
}
