import { lazy, Suspense } from "react";
import { createBrowserRouter, useRouteError } from "react-router-dom";
import RootLayout from "@/layouts/RootLayout";
import { RequireAuth } from "@/components/auth/RequireAuth";

const IndexPage = lazy(() => import("@/pages/Index"));
const Biens = lazy(() => import("@/pages/Biens"));
const Amortissements = lazy(() => import("@/pages/Amortissements"));
const MaintenancesEnCours = lazy(() => import("@/pages/MaintenancesEnCours"));
const Consomptibles = lazy(() => import("@/pages/Consomptibles"));
const ConsomptiblesBilanGlobal = lazy(() => import("@/pages/ConsomptiblesBilanGlobal"));
const Inventaires = lazy(() => import("@/pages/Inventaires"));
const Maintenance = lazy(() => import("@/pages/Maintenance"));
const Programmation = lazy(() => import("@/pages/Programmation"));
const Projets = lazy(() => import("@/pages/Projets"));
const Statistiques = lazy(() => import("@/pages/Statistiques"));
const Configuration = lazy(() => import("@/pages/Configuration"));
const Authentification = lazy(() => import("@/pages/Authentification"));
const NotFound = lazy(() => import("@/pages/NotFound"));
const Profile = lazy(() => import("@/pages/Profile"));

const withSuspense = (element: React.ReactNode) => (
  <Suspense
    fallback={
      <div className="grid min-h-screen place-items-center text-sm text-muted-foreground">
        Chargement…
      </div>
    }
  >
    {element}
  </Suspense>
);

// Filet de sécurité générique pour toute route qui échoue à charger ou à
// rendre (ex : chunk JS obsolète après un déploiement, si jamais le
// rechargement automatique de vite:preloadError n'a pas suffi) — évite
// l'écran d'erreur brut par défaut de React Router.
function RouteErrorBoundary() {
  const error = useRouteError();
  const isChunkError = error instanceof Error && /dynamically imported module|Failed to fetch/i.test(error.message);
  return (
    <div className="grid min-h-screen place-items-center px-4 text-center">
      <div className="max-w-md space-y-3">
        <p className="text-base font-semibold text-foreground">Une erreur est survenue</p>
        <p className="text-sm text-muted-foreground">
          {isChunkError
            ? "Une nouvelle version de l'application a été déployée. Rechargez la page pour continuer."
            : "Impossible d'afficher cette page. Rechargez la page ou réessayez plus tard."}
        </p>
        <button
          type="button"
          onClick={() => window.location.reload()}
          className="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
        >
          Recharger la page
        </button>
      </div>
    </div>
  );
}

export const router = createBrowserRouter([
  {
    element: <RootLayout />,
    errorElement: <RouteErrorBoundary />,
    children: [
      { path: "/authentification", element: withSuspense(<Authentification />) },
      { path: "/", element: withSuspense(<IndexPage />) },
      {
        path: "/dashboard",
        element: <RequireAuth>{withSuspense(<Statistiques />)}</RequireAuth>,
      },
      {
        path: "/statistiques",
        element: <RequireAuth>{withSuspense(<Statistiques />)}</RequireAuth>,
      },
      {
        path: "/biens",
        element: <RequireAuth>{withSuspense(<Biens />)}</RequireAuth>,
      },
      {
        path: "/biens/amortissements",
        element: <RequireAuth>{withSuspense(<Amortissements />)}</RequireAuth>,
      },
      {
        path: "/biens/maintenances-en-cours",
        element: <RequireAuth>{withSuspense(<MaintenancesEnCours />)}</RequireAuth>,
      },
      {
        path: "/consomptibles",
        element: <RequireAuth>{withSuspense(<Consomptibles />)}</RequireAuth>,
      },
      {
        path: "/consomptibles/bilan-global",
        element: <RequireAuth>{withSuspense(<ConsomptiblesBilanGlobal />)}</RequireAuth>,
      },
      {
        path: "/inventaires",
        element: <RequireAuth>{withSuspense(<Inventaires />)}</RequireAuth>,
      },
      {
        path: "/maintenance",
        element: <RequireAuth>{withSuspense(<Maintenance />)}</RequireAuth>,
      },
      {
        path: "/programmation",
        element: <RequireAuth>{withSuspense(<Programmation />)}</RequireAuth>,
      },
      {
        path: "/projets",
        element: <RequireAuth>{withSuspense(<Projets />)}</RequireAuth>,
      },
      {
        path: "/statistiques/rapports",
        element: <RequireAuth>{withSuspense(<Statistiques />)}</RequireAuth>,
      },
      {
        path: "/configuration",
        element: <RequireAuth>{withSuspense(<Configuration />)}</RequireAuth>,
      },
      {
        path: "/profile",
        element: <RequireAuth>{withSuspense(<Profile />)}</RequireAuth>,
      },
      { path: "*", element: withSuspense(<NotFound />) },
    ],
  },
]);
