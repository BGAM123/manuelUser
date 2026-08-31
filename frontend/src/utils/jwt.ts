/**
 * Utilitaires purs pour la manipulation des tokens JWT.
 * Aucun effet de bord — facile à tester.
 */

interface JwtPayload {
  exp?: number;
  iat?: number;
  [key: string]: unknown;
}

/**
 * Décode le payload d'un token JWT sans vérification de signature.
 */
export function parseJwt(token: string): JwtPayload | null {
  try {
    const base64Url = token.split(".")[1];
    if (!base64Url) return null;
    const base64 = base64Url.replace(/-/g, "+").replace(/_/g, "/");
    const jsonPayload = decodeURIComponent(
      atob(base64)
        .split("")
        .map((c) => "%" + ("00" + c.charCodeAt(0).toString(16)).slice(-2))
        .join(""),
    );
    return JSON.parse(jsonPayload) as JwtPayload;
  } catch {
    return null;
  }
}

/**
 * Retourne true si le token est expiré (ou invalide).
 */
export function isTokenExpired(token: string): boolean {
  const payload = parseJwt(token);
  if (!payload?.exp) return true;
  return payload.exp * 1000 < Date.now();
}

/**
 * Retourne true si le token expire dans moins de `thresholdSeconds` secondes.
 * Utilisé par axios.ts pour le rafraîchissement préventif.
 * @param thresholdSeconds - Seuil en secondes avant expiration (défaut : 120s)
 */
export function isTokenExpiringSoon(token: string, thresholdSeconds = 120): boolean {
  const payload = parseJwt(token);
  if (!payload?.exp) return true;
  const expiresInMs = payload.exp * 1000 - Date.now();
  return expiresInMs < thresholdSeconds * 1000;
}
