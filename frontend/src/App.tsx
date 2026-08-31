import { QueryClientProvider } from "@tanstack/react-query";
import { RouterProvider } from "react-router-dom";
import { Toaster } from "@/components/ui/sonner";
import { ThemeProvider } from "@/components/theme/ThemeProvider";
import { I18nProvider } from "@/utils/i18n";
import { PermissionProvider } from "@/contexts/PermissionContext";
import { queryClient } from "@/api/queryClient";
import { router } from "./router";

export default function App() {
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
