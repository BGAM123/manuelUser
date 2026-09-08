/**
 * Préchargement des chunks de pages.
 *
 * Chaque page est chargée en `lazy()` : au premier clic sur un menu, le
 * navigateur doit télécharger le fichier JS de la page avant de pouvoir
 * l'afficher — d'où l'impression de lenteur ("Chargement…" plusieurs
 * secondes). Ici on centralise les importateurs :
 *
 *   - `router.tsx` les utilise pour ses `lazy()` (aucun changement de
 *     comportement) ;
 *   - `prefetchRoute()` permet de déclencher le téléchargement au survol
 *     d'un lien, avant même le clic ;
 *   - `warmRoutes()` précharge toutes les pages quand le navigateur est
 *     inactif, si bien qu'après quelques secondes la navigation devient
 *     instantanée.
 *
 * Les imports dynamiques sont mis en cache par le navigateur/Vite : appeler
 * plusieurs fois le même importateur ne relance pas de téléchargement.
 */

type Importer = () => Promise<unknown>;

export const routeImporters: Record<string, Importer> = {
  "/": () => import("@/pages/Index"),
  "/authentification": () => import("@/pages/Authentification"),
  "/statistiques": () => import("@/pages/Statistiques"),
  "/dashboard": () => import("@/pages/Statistiques"),
  "/biens": () => import("@/pages/Biens"),
  "/biens/amortissements": () => import("@/pages/Amortissements"),
  "/biens/maintenances-en-cours": () => import("@/pages/MaintenancesEnCours"),
  "/consomptibles": () => import("@/pages/Consomptibles"),
  "/consomptibles/bilan-global": () => import("@/pages/ConsomptiblesBilanGlobal"),
  "/inventaires": () => import("@/pages/Inventaires"),
  "/maintenance": () => import("@/pages/Maintenance"),
  "/programmation": () => import("@/pages/Programmation"),
  "/projets": () => import("@/pages/Projets"),
  "/configuration": () => import("@/pages/Configuration"),
  "/profile": () => import("@/pages/Profile"),
};

const started = new Set<string>();

/** Précharge le code d'une route (idempotent, sans effet visible). */
export function prefetchRoute(to: string) {
  // On ignore la query string / le hash : "/configuration?s=users" → "/configuration"
  const path = to.split(/[?#]/)[0];
  if (started.has(path)) return;
  const importer = routeImporters[path];
  if (!importer) return;
  started.add(path);
  importer().catch(() => {
    // Échec réseau : on autorise une nouvelle tentative plus tard.
    started.delete(path);
  });
}

/**
 * Précharge progressivement toutes les pages pendant les temps morts.
 * Une page à la fois pour ne pas saturer la bande passante au démarrage.
 */
export function warmRoutes(delay = 1500) {
  const paths = Object.keys(routeImporters).filter((p) => p !== "/authentification");
  let i = 0;
  const next = () => {
    if (i >= paths.length) return;
    const path = paths[i++];
    prefetchRoute(path);
    schedule(next);
  };
  const schedule = (fn: () => void) => {
    const ric = (window as unknown as { requestIdleCallback?: (cb: () => void) => void })
      .requestIdleCallback;
    if (ric) ric(fn);
    else window.setTimeout(fn, 300);
  };
  window.setTimeout(() => schedule(next), delay);
}
