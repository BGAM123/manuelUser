/**
 * Hook — déconnexion automatique après inactivité.
 *
 * Écoute les événements souris/clavier/tactile.
 * Si aucun événement n'est détecté pendant `timeoutMs`, appelle logoutApi
 * et redirige vers la page de connexion.
 */

import { useEffect, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { useQueryClient } from "@tanstack/react-query";
import { logoutApi } from "@/api/authentication/auth.api";

const DEFAULT_TIMEOUT_MS = 14 * 60 * 1000; // 14 minutes

export function useAutoLogout(timeoutMs = DEFAULT_TIMEOUT_MS) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    const reset = () => {
      if (timerRef.current) clearTimeout(timerRef.current);
      timerRef.current = setTimeout(() => {
        logoutApi();
        // Purge le cache React Query — voir AppShell.handleLogout : sans ça,
        // les données du compte déconnecté restent en mémoire pour le
        // prochain utilisateur qui se connecte dans le même onglet.
        queryClient.clear();
        navigate("/authentification", { replace: true });
      }, timeoutMs);
    };

    const events: (keyof WindowEventMap)[] = [
      "mousemove",
      "mousedown",
      "keydown",
      "touchstart",
      "scroll",
      "click",
    ];

    events.forEach((e) => window.addEventListener(e, reset, { passive: true }));
    reset(); // Démarrer le timer

    return () => {
      if (timerRef.current) clearTimeout(timerRef.current);
      events.forEach((e) => window.removeEventListener(e, reset));
    };
  }, [navigate, queryClient, timeoutMs]);
}
