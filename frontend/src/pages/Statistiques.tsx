import { useState, useMemo } from "react";
import { AppShell } from "@/components/shared/AppShell";
import type { StatTab, FilterState, WidgetVisibility } from "@/components/statistiques/types";
import {
  GLOBAL_KPIS,
  CATEGORIES_BREAKDOWN,
  REGIONS_STATS,
  VEHICULES_DATA,
  TERRAINS_DATA,
  BATIMENTS_DATA,
  INFORMATIQUE_DATA,
  STRUCTURES_DATA,
  SUIVI_ALERTES_DATA,
} from "@/components/statistiques/mock-stats-data";
import { StatistiquesHeader } from "@/components/statistiques/StatistiquesHeader";
import { StatistiquesFilterBar } from "@/components/statistiques/StatistiquesFilterBar";
import { StatNavigationTabs } from "@/components/statistiques/StatNavigationTabs";
import { GlobalDashboardView } from "@/components/statistiques/views/GlobalDashboardView";
import { VehiculesKpisView } from "@/components/statistiques/views/VehiculesKpisView";
import { TerrainsKpisView } from "@/components/statistiques/views/TerrainsKpisView";
import { BatimentsKpisView } from "@/components/statistiques/views/BatimentsKpisView";
import { InformatiqueKpisView } from "@/components/statistiques/views/InformatiqueKpisView";
import { StructuresKpisView } from "@/components/statistiques/views/StructuresKpisView";
import { SuiviAlertesKpisView } from "@/components/statistiques/views/SuiviAlertesKpisView";
import {
  exportStatistiquesExcel,
  exportStatistiquesPdf,
} from "@/components/statistiques/exportUtils";
import { exportStatistiques } from "@/api/statistiques";
import { mapFilterStateToApiFilter } from "@/components/statistiques/filterMapper";
import {
  useDashboardStats,
} from "@/hooks/useStatistiques";
import { toast } from "sonner";
import { useT } from "@/utils/i18n";
import { CanAccess } from "@/components/auth/CanAccess";

const INITIAL_FILTERS: FilterState = {
  // Champs d'affichage UI
  // period vide = pas de filtre année envoyé au backend → toutes les années
  period: "",
  organigramme: "all",
  category: "all",
  assetType: "all",
  project: "all",
  region: "all",
  departement: "all",
  arrondissement: "all",
  etatBien: [],
  statutGestion: "all",
  hasTitreFoncier: "all",
  hasCarteGrise: "all",
  enLitigeOnly: false,
  searchHolder: "",
  // Résolutions numériques pour l'API (undefined = non filtré)
  organigrammeServiceId: null,
  regionId: null,
  departementId: null,
  arrondissementId: null,
  categorieId: null,
  assetTypeId: null,
  projectId: null,
  etatBienIds: [],
  categorieIds: [],
  assetTypeIds: [],
  regionIds: [],
  departementIds: [],
  arrondissementIds: [],
  projectIds: [],
  organigrammeServiceIds: [],
  exercice: new Date().getFullYear(),
};

const ALL_WIDGETS_VISIBLE: WidgetVisibility = {
  kpiCards: true,
  catPie: true,
  geoMap: true,
  svcPie: true,
  top10: true,
  top5: true,
  top6v: true,
  valeur: true,
  maint: true,
  mouv: true,
  aEtat: true,
  aInfo: true,
  evol: true,
  classmt: true,
  reformer: true,
};

export default function StatistiquesPage() {
  const t = useT();
  const [activeTab, setActiveTab] = useState<StatTab>("global");
  const [filters, setFilters] = useState<FilterState>(INITIAL_FILTERS);
  const [widgetVis, setWidgetVis] = useState<WidgetVisibility>(ALL_WIDGETS_VISIBLE);

  // ── Filtre API global (sans catégorie — pour les compteurs d'onglets) ──
  const baseApiFilter = useMemo(() => mapFilterStateToApiFilter({
    ...filters,
    categorieId: null,
    categorieIds: [],
    assetTypeIds: [],
  }), [filters]);

  // ── Compteurs pour les badges des onglets — un seul appel dashboard ────
  const { data: dashboardData } = useDashboardStats(baseApiFilter);

  const catCounts = useMemo(() => {
    const cats = dashboardData?.patrimoine?.vue_globale?.biens_par_categorie ?? [];
    const get = (id: number) => cats.find((c: any) => c.categorie_id === id)?.nombre_biens ?? 0;
    // Pour vehicules : utiliser d'abord vehicules.vue_globale.total_vehicules,
    // sinon fallback sur biens_par_categorie[cat=28] (compense bug backend)
    const vFromSection = dashboardData?.vehicules?.vue_globale?.total_vehicules ?? 0;
    // Pour terrains : utiliser terrains.vue_globale.total_terrains, sinon biens_par_categorie[29]
    const tFromSection = dashboardData?.terrains?.vue_globale?.total_terrains ?? 0;
    return {
      vehicules:    vFromSection > 0 ? vFromSection : get(28),
      batiments:    dashboardData?.batiments?.vue_globale?.total_batiments ?? get(35),
      terrains:     tFromSection > 0 ? tFromSection : get(29),
      informatique: get(31),
      structures:   dashboardData?.structures?.vue_globale?.total_structures?.total ?? undefined,
    };
  }, [dashboardData]);

  // IDs catégories réels confirmés depuis GET /categories (backend 185.98.136.192:8075)
  // 28 = MATÉRIEL ROULANT, 29 = TERRAINS, 35 = BÂTIMENTS, 31 = MATÉRIEL INFORMATIQUE
  const TAB_CATEGORY_MAP: Partial<Record<StatTab, { slug: string; id: number | null; nom: string }>> = {
    vehicules:    { slug: "vehicules",    id: 28, nom: "MATÉRIEL  ROULANT" },
    terrains:     { slug: "terrains",     id: 29, nom: "TERRAINS" },
    batiments:    { slug: "batiments",    id: 35, nom: "BÂTIMENTS" },
    informatique: { slug: "informatique", id: 31, nom: "MATÉRIEL INFORMATIQUE" },
    global:       { slug: "all",          id: null, nom: "" },
    structures:   { slug: "all",          id: null, nom: "" },
    suivi:        { slug: "all",          id: null, nom: "" },
  };

  const handleFilterChange = <K extends keyof FilterState>(
    key: K,
    value: FilterState[K]
  ) => {
    setFilters((prev) => ({ ...prev, [key]: value }));

    // Synchronisation automatique de l'onglet selon la catégorie sélectionnée
    // On compare maintenant l'ID numérique (plus fiable que le slug)
    if (key === "categorieId") {
      const id = value as number | null;
      if (id === 28) setActiveTab("vehicules");
      else if (id === 29) setActiveTab("terrains");
      else if (id === 35) setActiveTab("batiments");
      else if (id === 31) setActiveTab("informatique");
      else if (!id) setActiveTab("global");
    }
  };

  const handleTabChange = (tab: StatTab) => {
    setActiveTab(tab);
    const mapping = TAB_CATEGORY_MAP[tab];
    if (mapping) {
      setFilters((prev) => ({
        ...prev,
        // category = nom lisible (affiché dans le filtre) ou "all"
        category: mapping.nom || "all",
        categorieId: mapping.id,
        // Réinitialiser le type de bien quand on change d'onglet
        assetType: "all",
        assetTypeId: null,
      }));
    }
  };

  const handleApplyFilters = (newFilters: FilterState) => {
    setFilters(newFilters);
  };

  const handleResetFilters = () => {
    setFilters(INITIAL_FILTERS);
    setActiveTab("global");
  };

  const handleToggleWidget = (key: keyof WidgetVisibility) => {
    setWidgetVis((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  const handleShowAllWidgets = () => {
    setWidgetVis(ALL_WIDGETS_VISIBLE);
  };

  // Export Excel global
  const handleExportExcel = async () => {
    const apiFilter = mapFilterStateToApiFilter(filters);
    const endpoint =
      activeTab === "vehicules"
        ? "/api/stats/vehicules/vue-globale"
        : activeTab === "terrains"
        ? "/api/stats/terrains/vue-globale"
        : activeTab === "batiments"
        ? "/api/stats/batiments/vue-globale"
        : activeTab === "structures"
        ? "/api/stats/structures/vue-globale"
        : activeTab === "suivi"
        ? "/api/stats/suivi/points-attention-prioritaires"
        : "/api/stats/vue-globale";

    try {
      await exportStatistiques(
        endpoint,
        apiFilter,
        "xlsx",
        `Statistiques_${activeTab}_MINEPIA_${new Date().toISOString().slice(0, 10)}.xlsx`
      );
      toast.success(t("stats.toast.exportExcelSuccess"));
    } catch {
      // Repli local si l'API Symfony n'est pas encore démarrée en local
      const exportData = [
        ...CATEGORIES_BREAKDOWN.map((c) => ({
          Catégorie: c.name,
          "Nombre de biens": c.value,
          Pourcentage: c.pct,
        })),
        ...REGIONS_STATS.map((r) => ({
          Région: r.name,
          "Biens recensés": r.value,
          "Superficie (Ha)": r.surfaceHa,
          "Valeur (Mds FCFA)": r.valeurMds,
        })),
      ];
      exportStatistiquesExcel(
        "Synthèse Statistiques MINEPIA",
        exportData,
        `Statistiques_MINEPIA_${new Date().toISOString().slice(0, 10)}.xlsx`
      );
    }
  };

  // Export PDF officiel
  const handleExportPdf = async () => {
    const apiFilter = mapFilterStateToApiFilter(filters);
    const endpoint =
      activeTab === "vehicules"
        ? "/api/stats/vehicules/vue-globale"
        : activeTab === "terrains"
        ? "/api/stats/terrains/vue-globale"
        : activeTab === "batiments"
        ? "/api/stats/batiments/vue-globale"
        : "/api/stats/vue-globale";

    try {
      await exportStatistiques(
        endpoint,
        apiFilter,
        "pdf",
        `Rapport_Statistiques_${activeTab}_MINEPIA_${new Date().toISOString().slice(0, 10)}.pdf`
      );
      toast.success(t("stats.toast.exportPdfSuccess"));
    } catch {
      // Repli local
      const columns = [
        { header: "Domaine / Catégorie", dataKey: "domaine" },
        { header: "Total recensé", dataKey: "total" },
        { header: "Valeur déclarée", dataKey: "valeur" },
        { header: "Biens actifs", dataKey: "actifs" },
        { header: "Maintenance", dataKey: "maint" },
        { header: "À réformer", dataKey: "reformer" },
      ];

      const rows = [
        {
          domaine: "Matériel roulant (Véhicules)",
          total: "5 425",
          valeur: "11,85 Mds FCFA",
          actifs: "4 090",
          maint: "480",
          reformer: "412",
        },
        {
          domaine: "Terrains (Domaine foncier)",
          total: "1 245",
          valeur: "5,42 Mds FCFA",
          actifs: "1 185",
          maint: "—",
          reformer: "48 litiges",
        },
        {
          domaine: "Bâtiments (Patrimoine bâti)",
          total: "1 890",
          valeur: "6,24 Mds FCFA",
          actifs: "1 545",
          maint: "345",
          reformer: "45 H.U.",
        },
        {
          domaine: "Matériel informatique & bureautique",
          total: "2 153",
          valeur: "1,24 Md FCFA",
          actifs: "1 780",
          maint: "185",
          reformer: "188",
        },
        {
          domaine: "Mobilier de bureau",
          total: "1 745",
          valeur: "—",
          actifs: "1 620",
          maint: "46",
          reformer: "79",
        },
      ];

      exportStatistiquesPdf(
        "Rapport d'inventaire statistique et indicateurs patrimoniaux",
        columns,
        rows,
        `Rapport_Statistiques_MINEPIA_${new Date().toISOString().slice(0, 10)}.pdf`
      );
    }
  };

  return (
    <AppShell>
      <div className="space-y-4 pb-8">
        {/* ── En-tête officiel MINEPIA ─────────────────────────────────────── */}
        <StatistiquesHeader
          periodLabel={filters.period}
          onSelectPeriod={(p) => handleFilterChange("period", p)}
          onResetAll={handleResetFilters}
          onExportExcel={handleExportExcel}
          onExportPdf={handleExportPdf}
          widgetVis={widgetVis}
          onToggleWidget={handleToggleWidget}
          onShowAllWidgets={handleShowAllWidgets}
        />

        {/* ── Barre de filtres principale & bouton '+ Plus de filtres' ─────── */}
        <StatistiquesFilterBar
          filters={filters}
          onChangeFilter={handleFilterChange}
          onApplyFilters={handleApplyFilters}
          onResetFilters={handleResetFilters}
        />

        {/* ── Indicateur filtres actifs ────────────────────────────────────── */}
        {(filters.organigrammeServiceIds.length > 0 || filters.categorieIds.length > 0 || filters.assetTypeIds.length > 0 ||
          filters.projectIds.length > 0 || filters.regionIds.length > 0 || filters.departementIds.length > 0 ||
          filters.arrondissementIds.length > 0 ||
          filters.enLitigeOnly || (filters.etatBienIds && filters.etatBienIds.length > 0) ||
          (filters.statutGestion && filters.statutGestion !== "all")) && (
          <div className="flex items-center gap-2 flex-wrap text-[11px]">
            <span className="text-muted-foreground font-medium">{t("stats.filter.activeFilters")}</span>
            {filters.categorieIds.length > 0 && (
              <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 font-bold">
                {filters.categorieIds.length} {t("stats.filter.categoriesCount")}
              </span>
            )}
            {filters.assetTypeIds.length > 0 && (
              <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 font-bold">
                {filters.assetTypeIds.length} {t("stats.filter.assetTypesCount")}
              </span>
            )}
            {filters.projectIds.length > 0 && (
              <span className="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 font-bold">
                {filters.projectIds.length} {t("stats.filter.fundingSourcesCount")}
              </span>
            )}
            {filters.organigrammeServiceIds.length > 0 && (
              <span className="px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 font-bold">
                {filters.organigrammeServiceIds.length} {t("stats.filter.structuresCount")}
              </span>
            )}
            {filters.regionIds.length > 0 && (
              <span className="px-2 py-0.5 rounded-full bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300 font-bold">
                {filters.regionIds.length} {t("stats.filter.regionsCount")}
              </span>
            )}
            {filters.departementIds.length > 0 && (
              <span className="px-2 py-0.5 rounded-full bg-orange-100 text-orange-800 font-bold">
                {filters.departementIds.length} {t("stats.filter.departementsCount")}
              </span>
            )}
            {filters.arrondissementIds.length > 0 && (
              <span className="px-2 py-0.5 rounded-full bg-orange-100 text-orange-800 font-bold">
                {filters.arrondissementIds.length} {t("stats.filter.arrondissementsCount")}
              </span>
            )}
            {filters.statutGestion && filters.statutGestion !== "all" && (
              <span className="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold">
                {t("stats.filter.status")} {filters.statutGestion}
              </span>
            )}
            {filters.enLitigeOnly && (
              <span className="px-2 py-0.5 rounded-full bg-red-100 text-red-800 font-bold">
                {t("stats.filter.litigationOnly")}
              </span>
            )}
            <button
              type="button"
              onClick={handleResetFilters}
              className="px-2 py-0.5 rounded-full bg-muted text-muted-foreground hover:bg-destructive/10 hover:text-destructive text-[10px] font-bold transition-colors cursor-pointer"
            >
              {t("stats.filter.clearAll")}
            </button>
          </div>
        )}

        {/* ── Onglets de navigation entre les 7 écrans KPI ─────────────────── */}
        <StatNavigationTabs
          activeTab={activeTab}
          onChangeTab={handleTabChange}
          counts={catCounts}
        />

        {/* ── Affichage de la vue active ───────────────────────────────────── */}
        <div className="transition-all duration-300">
          {activeTab === "global" && (
            <CanAccess anyOf={["consultation_tableau_bord", "consultation_statistiques"]}>
              <GlobalDashboardView
                filters={filters}
                widgetVis={widgetVis}
                onNavigateTab={(tab) => handleTabChange(tab)}
                onFilterRegion={(reg) => handleFilterChange("region", reg)}
              />
            </CanAccess>
          )}

          <CanAccess permission="consultation_statistiques">
            {activeTab === "vehicules" && <VehiculesKpisView filters={filters} />}

            {activeTab === "terrains" && <TerrainsKpisView filters={filters} />}

            {activeTab === "batiments" && <BatimentsKpisView filters={filters} />}

            {activeTab === "informatique" && <InformatiqueKpisView filters={filters} />}

            {activeTab === "structures" && <StructuresKpisView filters={filters} />}

            {activeTab === "suivi" && <SuiviAlertesKpisView filters={filters} />}
          </CanAccess>
        </div>
      </div>
    </AppShell>
  );
}
