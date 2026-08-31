import { createBrowserRouter } from "react-router-dom";
import RootLayout from "@/layouts/RootLayout";
import IndexPage from "@/pages/Index";
import Dashboard from "@/pages/Dashboard";
import Biens from "@/pages/Biens";
import Inventaires from "@/pages/Inventaires";
import Maintenance from "@/pages/Maintenance";
import Programmation from "@/pages/Programmation";
import Projets from "@/pages/Projets";
import Statistiques from "@/pages/Statistiques";
import Configuration from "@/pages/Configuration";
import Authentification from "@/pages/Authentification";
import NotFound from "@/pages/NotFound";
import { RequireAuth } from "@/components/auth/RequireAuth";

export const router = createBrowserRouter([
  {
    element: <RootLayout />,
    children: [
      { path: "/authentification", element: <Authentification /> },
      { path: "/", element: <IndexPage /> },
      {
        path: "/dashboard",
        element: <RequireAuth><Dashboard /></RequireAuth>,
      },
      {
        path: "/statistiques",
        element: <RequireAuth><Dashboard /></RequireAuth>,
      },
      {
        path: "/biens",
        element: <RequireAuth><Biens /></RequireAuth>,
      },
      {
        path: "/inventaires",
        element: <RequireAuth><Inventaires /></RequireAuth>,
      },
      {
        path: "/maintenance",
        element: <RequireAuth><Maintenance /></RequireAuth>,
      },
      {
        path: "/programmation",
        element: <RequireAuth><Programmation /></RequireAuth>,
      },
      {
        path: "/projets",
        element: <RequireAuth><Projets /></RequireAuth>,
      },
      {
        path: "/statistiques/rapports",
        element: <RequireAuth><Statistiques /></RequireAuth>,
      },
      {
        path: "/configuration",
        element: <RequireAuth><Configuration /></RequireAuth>,
      },
      { path: "*", element: <NotFound /> },
    ],
  },
]);
