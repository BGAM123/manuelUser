import { type ReactNode } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import {
  Package,
  BarChart3,
  Settings,
  LogOut,
  MoreVertical,
} from "lucide-react";
import { cn } from "@/utils/utils";
import { useT } from "@/utils/i18n";
import { logoutApi } from "@/api/authentication/auth.api";
import { useConnectedUser } from "@/hooks/useConnectedUser";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const tabs = [
  { to: "/statistiques", label: "Statistiques", icon: BarChart3, matches: ["/statistiques", "/dashboard"] },
  { to: "/biens", label: "Biens", icon: Package, matches: ["/biens", "/inventaires", "/maintenance", "/programmation", "/projets"] },
  { to: "/configuration", label: "Administration", icon: Settings, matches: ["/configuration"] },
] as const;

export function AppShell({ children }: { children: ReactNode }) {
  const t = useT();
  const navigate = useNavigate();
  const pathname = useLocation().pathname;
  const { fullName, serviceName, avatarInitials } = useConnectedUser();

  const isActive = (matches: readonly string[]) =>
    matches.some((m) => pathname === m || pathname.startsWith(m + "/"));

  const handleLogout = () => {
    logoutApi();
    navigate("/authentification", { replace: true });
  };

  return (
    <div className="flex min-h-screen w-full flex-col overflow-x-hidden bg-background text-foreground">
      <header className="sticky top-0 z-30 border-b border-border bg-white">
        {/* Top row: brand + title + profile */}
        <div className="flex items-center gap-3 px-3 py-3 sm:gap-4 sm:px-4 sm:py-4 lg:px-8">
          <Link to="/statistiques" className="flex min-w-0 items-center gap-3">
            <img
              src="/minepia-logo.png"
              alt="MINEPIA"
              className="h-10 w-10 shrink-0 rounded-full border border-border bg-white object-contain p-0.5 sm:h-14 sm:w-14"
            />
            <div className="hidden min-w-0 leading-tight sm:block">
              <p className="truncate text-lg font-extrabold text-primary">MINEPIA</p>
              <p className="text-[11px] text-muted-foreground sm:text-xs">
                Ministère de l'Elevage, des Pêches
                <br />
                et des Industries Animales
              </p>
            </div>
          </Link>

          <div className="hidden flex-1 items-center justify-center md:flex">
            <h1 className="truncate text-2xl font-extrabold text-primary lg:text-3xl">
              Gestion du Patrimoine
            </h1>
          </div>

          <div className="ml-auto flex items-center gap-2 sm:gap-3">
            <div className="hidden h-11 w-11 shrink-0 items-center justify-center rounded-full border border-border bg-primary/10 text-sm font-bold text-primary sm:flex">
              {avatarInitials}
            </div>
            <div className="hidden min-w-0 flex-col leading-tight sm:flex">
              <span className="truncate text-sm font-bold text-foreground">
                {fullName || "Utilisateur"}
              </span>
              <span className="truncate text-xs text-muted-foreground">
                {serviceName || "MINEPIA"}
              </span>
            </div>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <button
                  type="button"
                  aria-label="Menu profil"
                  className="inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/70 hover:bg-muted"
                >
                  <MoreVertical className="h-5 w-5" />
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>
                  <div className="text-sm font-semibold">{fullName || "Utilisateur"}</div>
                  <div className="text-xs font-normal text-muted-foreground">
                    {serviceName || "MINEPIA"}
                  </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => navigate("/configuration")}>
                  <Settings className="mr-2 h-4 w-4" /> {t("nav.configuration")}
                </DropdownMenuItem>
                <DropdownMenuItem onClick={handleLogout}>
                  <LogOut className="mr-2 h-4 w-4" /> {t("nav.logout")}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        {/* Mobile centered title */}
        <div className="border-t border-border/60 px-4 py-2 md:hidden">
          <h1 className="text-center text-base font-bold text-primary">Gestion du Patrimoine</h1>
        </div>

        {/* Tabs row: 3 boutons pleine largeur */}
        <nav className="border-t border-border bg-white px-2 py-2 sm:px-3 sm:py-3 lg:px-8">
          <div className="grid grid-cols-3 gap-2 sm:gap-3">
            {tabs.map((tab) => {
              const Icon = tab.icon;
              const active = isActive(tab.matches);
              return (
                <Link
                  key={tab.to}
                  to={tab.to}
                  className={cn(
                    "inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md px-2 py-2.5 text-xs font-semibold transition-colors sm:gap-2 sm:px-4 sm:py-3 sm:text-base",
                    active
                      ? "bg-primary text-primary-foreground shadow-sm"
                      : "border border-border bg-white text-foreground/80 hover:bg-muted"
                  )}
                >
                  <Icon className="h-4 w-4 sm:h-5 sm:w-5" />
                  <span>{tab.label}</span>
                </Link>
              );
            })}
          </div>
        </nav>
      </header>

      <main className="flex-1 px-3 py-5 sm:px-4 sm:py-7 lg:px-6 lg:py-8">
        <div className="mx-auto w-full max-w-[1720px]">{children}</div>
      </main>
    </div>
  );
}