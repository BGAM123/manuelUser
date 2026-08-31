/**
 * Hook — maintien de session active (heartbeat).
 *
 * Vérifie à intervalles réguliers si le token expire bientôt.
 * Si c'est le cas, déclenche un refresh préventif via l'instance axios
 * (l'intercepteur de requête gère automatiquement le refresh).
 */

import { useEffect } from "react";
import { TOKEN_KEY } from "@/api/axios";
import { isTokenExpiringSoon } from "@/utils/jwt";
import api from "@/api/axios";

const HEARTBEAT_INTERVAL_MS = 60 * 1000; // Vérifie toutes les 60 secondes
const REFRESH_THRESHOLD_S = 300; // Rafraîchit si expiration < 5 minutes

export function useHeartbeat() {
  useEffect(() => {
    const tick = async () => {
      const token = localStorage.getItem(TOKEN_KEY);
      if (!token) return;
      if (isTokenExpiringSoon(token, REFRESH_THRESHOLD_S)) {
        // Un appel léger suffit pour déclencher l'intercepteur de refresh
        try {
          await api.get("/profile");
        } catch {
          // Erreur gérée par l'intercepteur (forceLogout si 401 non récupérable)
        }
      }
    };

    const id = setInterval(tick, HEARTBEAT_INTERVAL_MS);
    return () => clearInterval(id);
  }, []);
}
