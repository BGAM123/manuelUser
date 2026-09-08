import { useEffect } from "react";
import { QueryClientProvider } from "@tanstack/react-query";
import { RouterProvider } from "react-router-dom";
import { Toaster } from "@/components/ui/sonner";
import { ThemeProvider } from "@/components/theme/ThemeProvider";
import { I18nProvider } from "@/utils/i18n";
import { PermissionProvider } from "@/contexts/PermissionContext";
import { queryClient } from "@/api/queryClient";
import { warmRoutes } from "@/utils/routePrefetch";
import { router } from "./router";

export default function App() {
  // Précharge le code de toutes les pages pendant les temps morts du
  // navigateur : la première navigation vers un menu n'a alors plus de
  // téléchargement à attendre.
  useEffect(() => {
    warmRoutes();
  }, []);


  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <I18nProvider>
          <PermissionProvider>
            <RouterProvider router={router} />
            <Toaster richColors position="top-right" />
          </PermissionProvider>
        </I18nProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}
