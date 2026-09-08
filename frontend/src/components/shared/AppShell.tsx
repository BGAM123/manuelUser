import { useState, useEffect, type ReactNode } from "react";
import { Link, useLocation, useNavigate, useSearchParams } from "react-router-dom";
import { useQueryClient } from "@tanstack/react-query";
import {
  Package,
  BarChart3,
  Settings,
  LogOut,
  User,
  MoreVertical,
  Menu,
  X,
  Network,
  ShieldCheck,
  UserCog,
  Users,
  Box,
  FolderOpen,
  Briefcase,
  Map,
  Tag,
  Sliders,
  Users2,
  GitBranch,
  Tags,
  ChevronDown,
  Bell,
  ScrollText,
  PanelLeftClose,
  PanelLeftOpen,
  type LucideIcon,
} from "lucide-react";
import { cn } from "@/utils/utils";
import { useT, useI18n, type Key } from "@/utils/i18n";
import { logoutApi } from "@/api/authentication/auth.api";
import { useConnectedUser } from "@/hooks/useConnectedUser";
import { useCanAccess } from "@/hooks/useCanAccess";
import { TOP_NAV_PERMISSIONS, ADMIN_ITEM_PERMISSIONS } from "@/utils/navPermissions";
import { NotificationsBell } from "./NotificationsBell";
import { prefetchRoute } from "@/utils/routePrefetch";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const navItems = [
  {
    to: "/statistiques",
    labelKey: "nav.statistiques",
    icon: BarChart3,
    matches: ["/statistiques", "/dashboard"],
  },
  {
    to: "/biens",
    labelKey: "nav.biens",
    icon: Package,
    matches: ["/biens", "/inventaires", "/maintenance", "/programmation", "/projets"],
  },
  { to: "/consomptibles", labelKey: "nav.consomptibles", icon: Box, matches: ["/consomptibles"] },
  { to: "/configuration", labelKey: "nav.administration", icon: Settings, matches: ["/configuration"] },
] as const;

type AdminItem = { key: string; tab?: string; labelKey: Key; icon: LucideIcon };
type AdminGroup = { id: string; labelKey: Key; icon: LucideIcon; items: AdminItem[] };

const adminGroups: AdminGroup[] = [
  {
    id: "orga",
    labelKey: "nav.group.orga",
    icon: Network,
    items: [
      { key: "orga", tab: "schema", labelKey: "nav.group.orga.schema", icon: GitBranch },
      { key: "orga", tab: "types", labelKey: "nav.group.orga.types", icon: Tags },
    ],
  },
  {
    id: "gestion-users",
    labelKey: "nav.group.users",
    icon: Users,
    items: [
      { key: "users", labelKey: "nav.group.users.users", icon: Users },
      { key: "permissions", labelKey: "nav.group.users.permissions", icon: ShieldCheck },
      { key: "roles", labelKey: "nav.group.users.roles", icon: UserCog },
      { key: "groupes", labelKey: "nav.group.users.groupes", icon: Users2 },
      { key: "logs", labelKey: "nav.group.users.logs", icon: ScrollText },
    ],
  },
  {
    id: "categories",
    labelKey: "nav.group.categories",
    icon: FolderOpen,
    items: [
      { key: "categories", labelKey: "nav.group.categories.assetCategories", icon: FolderOpen },
      { key: "exitTypes", labelKey: "nav.group.categories.exitTypes", icon: LogOut },
      { key: "etatBiens", labelKey: "nav.group.categories.etatBiens", icon: Tag },
      { key: "champs", labelKey: "nav.group.categories.champs", icon: Sliders },
    ],
  },
];

// Éléments standalone sous Administration (sans accordéon)
const adminStandaloneItems: AdminItem[] = [
  { key: "cartographie", labelKey: "nav.standalone.cartographie", icon: Map },
  { key: "projets", labelKey: "nav.standalone.sourcesFinancement", icon: Briefcase },
  { key: "notifications", labelKey: "nav.standalone.notifications", icon: Bell },
];

/** Bouton visible partout pour basculer l'interface entre français et anglais. */
function LanguageSwitcher({ className }: { className?: string }) {
  const { lang, setLang, t } = useI18n();
  return (
    <div
      className={cn(
        "inline-flex shrink-0 overflow-hidden rounded-full border border-border text-[11px] font-bold",
        className,
      )}
      role="group"
      aria-label={t("nav.languageSwitcher")}
    >
      <button
        type="button"
        onClick={() => setLang("fr")}
        aria-pressed={lang === "fr"}
        title="Français"
        className={cn(
          "px-2 py-1 transition-colors",
          lang === "fr" ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-muted",
        )}
      >
        FR
      </button>
      <button
        type="button"
        onClick={() => setLang("en")}
        aria-pressed={lang === "en"}
        title="English"
        className={cn(
          "px-2 py-1 transition-colors",
          lang === "en" ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-muted",
        )}
      >
        EN
      </button>
    </div>
  );
}

function SidebarContent({
  onNavigate,
  onCollapse,
}: {
  onNavigate?: () => void;
  onCollapse?: () => void;
}) {
  const t = useT();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const pathname = useLocation().pathname;
  const [searchParams] = useSearchParams();
  const { fullName, serviceName, avatarInitials } = useConnectedUser();

  // RBAC — filtre le menu selon les permissions réelles du rôle connecté
  // (voir navPermissions.ts et useCanAccess pour le filet de sécurité admin
  // / chargement / rôle sans permission).
  const { canAny, isUnfiltered } = useCanAccess();

  const visibleAdminGroups = adminGroups
    .map((group) => ({ ...group, items: group.items.filter((item) => canAny(ADMIN_ITEM_PERMISSIONS[item.key] ?? [])) }))
    .filter((group) => group.items.length > 0);
  const visibleAdminStandaloneItems = adminStandaloneItems.filter((item) =>
    canAny(ADMIN_ITEM_PERMISSIONS[item.key] ?? []),
  );
  const configVisible = isUnfiltered || visibleAdminGroups.length > 0 || visibleAdminStandaloneItems.length > 0;
  const visibleNavItems = navItems.filter((item) =>
    item.to === "/configuration" ? configVisible : canAny([...(TOP_NAV_PERMISSIONS[item.to] ?? [])]),
  );

  const isActive = (matches: readonly string[]) =>
    matches.some((m) => pathname === m || pathname.startsWith(m + "/"));

  const adminActive = isActive(["/configuration"]);
  const activeSection = searchParams.get("s") ?? "";
  const activeTab = searchParams.get("tab") ?? "";

  const isItemActive = (item: AdminItem) => {
    if (item.key !== activeSection) return false;
    if (item.tab) return item.tab === activeTab;
    // Non-orga items: active when no tab in URL (or any tab)
    return !item.tab;
  };

  const activeGroupId = visibleAdminGroups.find((g) => g.items.some(isItemActive))?.id ?? null;

  const [openGroups, setOpenGroups] = useState<Set<string>>(() => {
    const s = new Set<string>();
    if (activeGroupId) s.add(activeGroupId);
    return s;
  });

  useEffect(() => {
    if (activeGroupId) {
      setOpenGroups((prev) => {
        if (prev.has(activeGroupId)) return prev;
        return new Set([...prev, activeGroupId]);
      });
    }
  }, [activeGroupId]);

  const toggleGroup = (id: string) =>
    setOpenGroups((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });

  const getItemLink = (item: AdminItem) => {
    let url = `/configuration?s=${item.key}`;
    if (item.tab) url += `&tab=${item.tab}`;
    return url;
  };

  const handleLogout = () => {
    logoutApi();
    // Purge le cache React Query — sans ça, les données du compte qui se
    // déconnecte (ex : liste complète des biens d'un admin) restent en
    // mémoire et peuvent s'afficher un instant pour le prochain utilisateur
    // qui se connecte dans le même onglet, avant qu'un refetch ne corrige.
    queryClient.clear();
    navigate("/authentification", { replace: true });
  };

  return (
    <div className="flex h-full flex-col bg-white">
      {/* Logo / marque */}
      <div className="flex flex-col items-center gap-2 border-b border-border px-4 py-5">
        <div className="flex w-full items-start justify-between">
          {onCollapse ? (
            <button
              type="button"
              onClick={onCollapse}
              aria-label={t("nav.collapseSidebar")}
              title={t("nav.collapseSidebar")}
              className="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-muted"
            >
              <PanelLeftClose className="h-5 w-5" />
            </button>
          ) : (
            <div className="w-9" />
          )}
          <Link
            to="/statistiques"
            onClick={onNavigate}
            className="flex flex-col items-center gap-2 text-center"
          >
            <img
              src="/minepia-logo.png"
              alt="MINEPIA"
              className="h-14 w-14 rounded-full border border-border bg-white object-contain p-0.5 shadow-sm"
            />
            <div>
              <p className="text-sm font-extrabold leading-none text-primary">MINEPIA</p>
              <p className="mt-0.5 max-w-[120px] text-[10px] leading-snug text-muted-foreground">
                {t("app.tagline")}
              </p>
            </div>
          </Link>
          <NotificationsBell />
        </div>
        <LanguageSwitcher />
      </div>

      {/* Navigation */}
      <nav className="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-4">
        {visibleNavItems.map((item) => {
          const Icon = item.icon;
          const active = isActive(item.matches);
          const label = t(item.labelKey);
          const letter = label.charAt(0).toUpperCase();
          const isAdmin = item.to === "/configuration";
          return (
            <div key={item.to}>
              <Link
                to={item.to}
                onClick={onNavigate}
                onMouseEnter={() => prefetchRoute(item.to)}
                onFocus={() => prefetchRoute(item.to)}
                className={cn(
                  "group flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition-all",
                  active
                    ? "bg-primary text-primary-foreground shadow-sm"
                    : "text-foreground/70 hover:bg-muted hover:text-foreground",
                )}
              >
                <span
                  className={cn(
                    "flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-extrabold transition-colors",
                    active
                      ? "bg-white/20 text-primary-foreground"
                      : "bg-primary/10 text-primary group-hover:bg-primary/20",
                  )}
                >
                  {letter}
                </span>
                <span className="leading-tight">{label.toUpperCase()}</span>
              </Link>

              {/* Sous-menus Administration — accordéons + liens directs */}
              {isAdmin && adminActive && (
                <div className="mt-1 flex flex-col gap-0.5 pl-2 pr-1">
                  {visibleAdminGroups.map((group) => {
                    const GroupIcon = group.icon;
                    const isOpen = openGroups.has(group.id);
                    const groupHasActive = group.items.some(isItemActive);

                    return (
                      <div key={group.id}>
                        {/* En-tête du groupe (bouton accordéon) */}
                        <button
                          type="button"
                          onClick={() => toggleGroup(group.id)}
                          className={cn(
                            "flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-semibold transition-colors",
                            groupHasActive
                              ? "text-primary"
                              : "text-foreground/70 hover:bg-muted hover:text-foreground",
                          )}
                        >
                          <GroupIcon className="h-3.5 w-3.5 shrink-0" />
                          <span className="flex-1 truncate text-left">{t(group.labelKey)}</span>
                          <ChevronDown
                            className={cn(
                              "h-3 w-3 shrink-0 transition-transform duration-300",
                              isOpen && "rotate-180",
                            )}
                          />
                        </button>

                        {/* Contenu coulissant */}
                        <div
                          className="overflow-hidden"
                          style={{
                            maxHeight: isOpen ? `${group.items.length * 34 + 8}px` : "0px",
                            transition: "max-height 0.3s ease-in-out",
                          }}
                        >
                          <div className="flex flex-col gap-0.5 pb-1 pl-4">
                            {group.items.map((item) => {
                              const ItemIcon = item.icon;
                              const itemActive = isItemActive(item);
                              return (
                                <Link
                                  key={`${item.key}-${item.tab ?? ""}`}
                                  to={getItemLink(item)}
                                  onClick={onNavigate}
                                  className={cn(
                                    "flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs font-medium transition-colors",
                                    itemActive
                                      ? "bg-primary/10 text-primary"
                                      : "text-foreground/60 hover:bg-muted hover:text-foreground",
                                  )}
                                >
                                  <ItemIcon className="h-3 w-3 shrink-0" />
                                  <span className="truncate">{t(item.labelKey)}</span>
                                </Link>
                              );
                            })}
                          </div>
                        </div>
                      </div>
                    );
                  })}

                  {/* Liens directs (sans accordéon) */}
                  {visibleAdminStandaloneItems.map((item) => {
                    const ItemIcon = item.icon;
                    const itemActive = item.key === activeSection;
                    return (
                      <Link
                        key={item.key}
                        to={`/configuration?s=${item.key}`}
                        onClick={onNavigate}
                        className={cn(
                          "flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition-colors",
                          itemActive
                            ? "bg-primary/10 text-primary"
                            : "text-foreground/60 hover:bg-muted hover:text-foreground",
                        )}
                      >
                        <ItemIcon className="h-3.5 w-3.5 shrink-0" />
                        <span className="truncate">{t(item.labelKey)}</span>
                      </Link>
                    );
                  })}
                </div>
              )}
            </div>
          );
        })}
      </nav>

      {/* Profil utilisateur + déconnexion */}
      <div className="border-t border-border px-3 py-4">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button
              type="button"
              className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted"
            >
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground">
                {avatarInitials}
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-xs font-bold text-foreground">
                  {fullName || t("nav.defaultUser")}
                </p>
                <p className="truncate text-[11px] text-muted-foreground">
                  {serviceName || "MINEPIA"}
                </p>
              </div>
              <MoreVertical className="h-4 w-4 shrink-0 text-muted-foreground" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent side="top" align="start" className="w-52">
            <DropdownMenuLabel>
              <div className="text-sm font-semibold">{fullName || t("nav.defaultUser")}</div>
              <div className="text-xs font-normal text-muted-foreground">
                {serviceName || "MINEPIA"}
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={() => {
                navigate("/profile");
                onNavigate?.();
              }}
            >
              <User className="mr-2 h-4 w-4" /> {t("nav.profile")}
            </DropdownMenuItem>
            <DropdownMenuItem
              onClick={() => {
                navigate("/configuration");
                onNavigate?.();
              }}
            >
              <Settings className="mr-2 h-4 w-4" /> {t("nav.configuration")}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={handleLogout}
              className="text-destructive focus:text-destructive"
            >
              <LogOut className="mr-2 h-4 w-4" /> {t("nav.logout")}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>
  );
}

// ─── AppShell principal ────────────────────────────────────────────────────────

export function AppShell({ children }: { children: ReactNode }) {
  const t = useT();
  const [drawerOpen, setDrawerOpen] = useState(false);
  // Sidebar repliable (desktop) — le choix est mémorisé d'une page à l'autre
  // et d'une session à l'autre. Quand elle est masquée, le contenu occupe
  // toute la largeur disponible.
  const [collapsed, setCollapsed] = useState<boolean>(
    () => localStorage.getItem("minepia_sidebar_collapsed") === "1",
  );

  useEffect(() => {
    localStorage.setItem("minepia_sidebar_collapsed", collapsed ? "1" : "0");
  }, [collapsed]);

  return (
    <div className="flex min-h-screen w-full bg-background text-foreground">
      {/* ── Sidebar gauche fixe (desktop ≥ lg) ───────────────── */}
      {!collapsed && (
        <aside className="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-20 lg:flex lg:w-64 xl:w-72 lg:flex-col border-r border-border shadow-sm overflow-y-auto">
          <SidebarContent onCollapse={() => setCollapsed(true)} />
        </aside>
      )}

      {/* ── Zone principale (marge gauche = largeur sidebar) ─── */}
      <div
        className={cn(
          "flex min-w-0 flex-1 flex-col",
          !collapsed && "lg:pl-64 xl:pl-72",
        )}
      >
        {/* Barre mobile (hamburger uniquement) */}
        <header className="sticky top-0 z-30 flex items-center justify-between border-b border-border bg-white px-4 py-3 lg:hidden">
          <Link to="/statistiques" className="flex items-center gap-2">
            <img
              src="/minepia-logo.png"
              alt="MINEPIA"
              className="h-8 w-8 rounded-full border border-border bg-white object-contain p-0.5"
            />
            <span className="text-sm font-extrabold text-primary">MINEPIA</span>
          </Link>
          <div className="flex items-center gap-2">
            <LanguageSwitcher />
            <NotificationsBell />
            <button
              type="button"
              aria-label={t("nav.openMenu")}
              onClick={() => setDrawerOpen(true)}
              className="inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/70 hover:bg-muted"
            >
              <Menu className="h-5 w-5" />
            </button>
          </div>
        </header>

        {/* Barre desktop de réaffichage de la sidebar (visible seulement
            lorsqu'elle est masquée) */}
        {collapsed && (
          <div className="sticky top-0 z-30 hidden items-center gap-2 border-b border-border bg-white px-4 py-2 lg:flex">
            <button
              type="button"
              aria-label={t("nav.expandSidebar")}
              title={t("nav.expandSidebar")}
              onClick={() => setCollapsed(false)}
              className="inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/70 hover:bg-muted"
            >
              <PanelLeftOpen className="h-5 w-5" />
            </button>
            <Link to="/statistiques" className="flex items-center gap-2">
              <img
                src="/minepia-logo.png"
                alt="MINEPIA"
                className="h-7 w-7 rounded-full border border-border bg-white object-contain p-0.5"
              />
              <span className="text-sm font-extrabold text-primary">MINEPIA</span>
            </Link>
            <div className="ml-auto flex items-center gap-2">
              <LanguageSwitcher />
              <NotificationsBell />
            </div>
          </div>
        )}

        {/* Contenu page — pas de largeur maximale fixe : sur les très grands
            écrans (ultrawide, incurvés), le contenu doit occuper tout
            l'espace disponible plutôt que de rester centré avec de grandes
            marges vides de part et d'autre. */}
        <main className="flex-1 px-3 py-5 sm:px-4 sm:py-7 lg:px-6 lg:py-8">
          <div className="mx-auto w-full">{children}</div>
        </main>
      </div>

      {/* ── Drawer mobile (gauche) ────────────────────────────── */}
      {drawerOpen && (
        <>
          <div
            className="fixed inset-0 z-40 bg-black/40 lg:hidden"
            onClick={() => setDrawerOpen(false)}
          />
          <div className="fixed inset-y-0 left-0 z-50 w-56 border-r border-border shadow-xl lg:hidden overflow-y-auto">
            <div className="absolute right-2 top-3 z-10">
              <button
                type="button"
                aria-label={t("nav.closeMenu")}
                onClick={() => setDrawerOpen(false)}
                className="rounded-md p-1 hover:bg-muted"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <SidebarContent onNavigate={() => setDrawerOpen(false)} />
          </div>
        </>
      )}
    </div>
  );
}
