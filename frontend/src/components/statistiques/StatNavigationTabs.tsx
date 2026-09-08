import {
  LayoutDashboard,
  Truck,
  Mountain,
  Home,
  Monitor,
  Building2,
  TrendingUp,
} from "lucide-react";
import type { StatTab } from "./types";
import { useCanAccess } from "@/hooks/useCanAccess";
import { MINEPIA_GREEN } from "./mock-stats-data";
import { useT, type Key } from "@/utils/i18n";

interface TabCounts {
  vehicules?: number;
  terrains?: number;
  batiments?: number;
  informatique?: number;
  structures?: number;
}

interface StatNavigationTabsProps {
  activeTab: StatTab;
  onChangeTab: (tab: StatTab) => void;
  /** Compteurs réels depuis le backend — remplace les badges hardcodés. */
  counts?: TabCounts;
}

const BASE_TABS: { id: StatTab; labelKey: Key; icon: any }[] = [
  { id: "global",       labelKey: "stats.tab.global",       icon: LayoutDashboard },
  { id: "vehicules",    labelKey: "stats.tab.vehicules",    icon: Truck },
  { id: "terrains",     labelKey: "stats.tab.terrains",     icon: Mountain },
  { id: "batiments",    labelKey: "stats.tab.batiments",    icon: Home },
  { id: "informatique", labelKey: "stats.tab.informatique", icon: Monitor },
  { id: "structures",   labelKey: "stats.tab.structures",   icon: Building2 },
  { id: "suivi",        labelKey: "stats.tab.suivi",        icon: TrendingUp },
];

const fmt = (n: number) => n.toLocaleString("fr-FR");

export function StatNavigationTabs({
  activeTab,
  onChangeTab,
  counts,
}: StatNavigationTabsProps) {
  const t = useT();
  // Onglet "global" accessible avec l'une ou l'autre permission (vue
  // d'ensemble) ; les onglets détaillés (véhicules, terrains...) exigent
  // spécifiquement consultation_statistiques.
  const { can } = useCanAccess();
  const canGlobal = can("consultation_tableau_bord") || can("consultation_statistiques");
  const canDetailed = can("consultation_statistiques");
  const tabs = BASE_TABS.filter((tab) => (tab.id === "global" ? canGlobal : canDetailed));
  const getBadge = (id: StatTab): string | undefined => {
    // Si counts fournis, on affiche les vraies valeurs (ou "0" si confirmé vide)
    if (counts !== undefined) {
      switch (id) {
        case "vehicules":    return counts.vehicules    != null ? fmt(counts.vehicules)    : undefined;
        case "terrains":     return counts.terrains     != null ? fmt(counts.terrains)     : undefined;
        case "batiments":    return counts.batiments    != null ? fmt(counts.batiments)    : undefined;
        case "informatique": return counts.informatique != null ? fmt(counts.informatique) : undefined;
        case "structures":   return counts.structures   != null ? fmt(counts.structures)   : undefined;
        case "suivi":        return "GAP";
        default:             return undefined;
      }
    }
    // Pas encore de données chargées : pas de badge (ne pas afficher les faux chiffres)
    if (id === "suivi") return "GAP";
    return undefined;
  };

  return (
    <div className="flex w-full justify-center overflow-x-auto pb-1 no-scrollbar">
      <div className="flex items-center gap-1.5 p-1 bg-muted/50 rounded-xl border border-border/80 min-w-max">
        {tabs.map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          const badge = getBadge(tab.id);

          return (
            <button
              type="button"
              key={tab.id}
              onClick={() => onChangeTab(tab.id)}
              className={`flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-all duration-200 cursor-pointer ${
                isActive
                  ? "bg-card text-foreground shadow-xs ring-1 ring-border/80"
                  : "text-muted-foreground hover:text-foreground hover:bg-background/60"
              }`}
            >
              <Icon
                className="h-4 w-4 shrink-0 transition-colors"
                style={{ color: isActive ? MINEPIA_GREEN : "currentColor" }}
              />
              <span className="whitespace-nowrap">{t(tab.labelKey)}</span>
              {badge && (
                <span
                  className={`ml-1 text-[10px] px-1.5 rounded-full font-bold tabular-nums ${
                    isActive
                      ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300"
                      : "bg-muted text-muted-foreground"
                  }`}
                >
                  {badge}
                </span>
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
}
