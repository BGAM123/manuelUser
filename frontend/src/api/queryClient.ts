/**
 * Instance QueryClient partagée — extraite de App.tsx pour être importable
 * depuis axios.ts sans dépendance circulaire (voir l'intercepteur de réponse
 * dans axios.ts, qui invalide les requêtes actives après chaque enregistrement
 * réussi : demande explicite 2026-08-29, "actualiser les données et les pages
 * après chaque enregistrement dans le système").
 */
import { QueryClient } from "@tanstack/react-query";

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 5,
      retry: 1,
      // "always" (pas juste le comportement par défaut, qui ne refetch que
      // si les données sont périmées) — quand l'utilisateur quitte une page
      // puis y revient, le composant remonte et on veut des données à jour
      // systématiquement. Les données en cache s'affichent immédiatement
      // (pas d'écran de chargement), le refetch se fait en arrière-plan.
      refetchOnMount: "always",
    },
  },
});
