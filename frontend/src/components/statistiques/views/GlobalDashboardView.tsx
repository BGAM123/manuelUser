import React from "react";
import {
  Package,
  Wallet,
  CheckCircle2,
  Wrench,
  PackageMinus,
  Building2,
  AlertTriangle,
  HelpCircle,
  TrendingUp,
  Gavel,
  ArrowRight,
} from "lucide-react";
import {
  ResponsiveContainer,
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
} from "recharts";
import type { WidgetVisibility, StatTab, FilterState } from "../types";
import {
  useVueGlobale,
  useRepartitionCategorie,
  useRepartitionRegions,
  useRepartitionStructures,
  useRepartitionProjets,
  useEvolutionMensuelle,
  useEvolutionGap,
  useDashboardStats,
} from "@/hooks/useStatistiques";
import type {
  RepartitionStructureNombreItem,
  RepartitionStructureValeurItem,
  EvolutionMensuellePatrimoineItem,
  EvolutionMensuelleCompteItem,
} from "@/api/statistiques";
import { mapFilterStateToApiFilter } from "../filterMapper";
import {
  // Couleurs — conservées (pas du mock métier, juste des constantes CSS)
  MINEPIA_GREEN,
  STAT_BLUE,
  STAT_ORANGE,
  STAT_PURPLE,
  STAT_RED,
  STAT_TEAL,
} from "../mock-stats-data";
import { StatDonutChart } from "../shared/StatDonutChart";
import { CameroonMapWidget } from "../shared/CameroonMapWidget";
import { StatDetailModal, type StatModalType } from "../shared/StatDetailModal";
import { EmptyState } from "../shared/EmptyState";
import { useT } from "@/utils/i18n";

interface GlobalDashboardViewProps {
  filters?: FilterState;
  widgetVis: WidgetVisibility;
  onNavigateTab: (tab: StatTab) => void;
  onFilterRegion?: (region: string) => void;
}

const formatNumber = (v: number) => v.toLocaleString("fr-FR");
const formatMilliards = (v: number) => {
  if (v === 0) return "0 FCFA";
  if (v < 1_000_000) return `${v.toLocaleString("fr-FR")} FCFA`;
  if (v < 1_000_000_000) return `${(v / 1_000_000).toFixed(2).replace(".", ",")} M FCFA`;
  return `${(v / 1_000_000_000).toFixed(2).replace(".", ",")} Mds FCFA`;
};

// ── Couleurs par catégorie (stables) ───────────────────────────────────────
const CATEGORY_COLORS: Record<string, string> = {
  vehicule: MINEPIA_GREEN,
  roulant: MINEPIA_GREEN,
  terrain: STAT_BLUE,
  foncier: STAT_BLUE,
  batiment: STAT_ORANGE,
  bati: STAT_ORANGE,
  informatique: STAT_PURPLE,
  mobilier: STAT_TEAL,
};

function getCategoryColor(nom: string): string {
  const lower = nom.toLowerCase();
  for (const [key, color] of Object.entries(CATEGORY_COLORS)) {
    if (lower.includes(key)) return color;
  }
  return STAT_BLUE;
}

// ── Composants de présentation (inchangés) ─────────────────────────────────

function FlexRow({ children }: { children: React.ReactNode }) {
  return <div className="flex flex-wrap gap-4 w-full">{children}</div>;
}

function FlexCell({
  children,
  minW = "300px",
}: {
  children: React.ReactNode;
  minW?: string;
}) {
  return (
    <div
      className="flex-1 min-w-[280px] transition-all duration-300"
      style={{ minWidth: `min(100%, ${minW})` }}
    >
      {children}
    </div>
  );
}

function DashboardCard({
  title,
  sub,
  children,
  isLoading,
}: {
  title: string;
  sub?: string;
  children: React.ReactNode;
  isLoading?: boolean;
}) {
  return (
    <div className="flex h-full flex-col rounded-xl border border-border bg-card shadow-xs transition-shadow hover:shadow-sm">
      <div className="border-b border-border px-4 py-3 bg-muted/10 flex items-center justify-between gap-2">
        <div>
          <p className="text-[11px] font-bold uppercase tracking-wider text-foreground">
            {title}
          </p>
          {sub && <p className="text-[10px] text-muted-foreground">{sub}</p>}
        </div>
        {isLoading && (
          <span className="h-1.5 w-1.5 shrink-0 animate-pulse rounded-full bg-emerald-500" />
        )}
      </div>
      <div className="flex-1 p-4">{children}</div>
    </div>
  );
}

function DetailLink({
  label,
  onClick,
}: {
  label?: string;
  onClick?: () => void;
}) {
  const t = useT();
  return (
    <button
      type="button"
      onClick={onClick}
      className="mt-3 flex items-center gap-1 text-[11px] font-bold text-emerald-700 dark:text-emerald-400 hover:underline cursor-pointer transition-colors"
    >
      <span>{label ?? t("stats.global.viewDetail")}</span>
      <ArrowRight className="h-3 w-3" />
    </button>
  );
}

function ProgressBar({
  rank,
  name,
  value,
  max,
  color,
}: {
  rank?: number;
  name: string;
  value: number;
  max: number;
  color: string;
}) {
  const pct = Math.max(3, Math.round((value / max) * 100));
  return (
    <div className="flex items-center gap-2 text-[11px]">
      {rank !== undefined && (
        <span className="w-4 shrink-0 text-right font-bold text-muted-foreground">
          {rank}
        </span>
      )}
      <div className="flex-1 min-w-0">
        <p className="truncate text-foreground font-medium leading-tight">{name}</p>
        <div className="mt-0.5 h-1.5 overflow-hidden rounded-full bg-muted">
          <div
            className="h-full rounded-full transition-all duration-500"
            style={{ width: `${pct}%`, background: color }}
          />
        </div>
      </div>
      <span className="shrink-0 tabular-nums font-bold text-foreground">
        {formatNumber(value)}
      </span>
    </div>
  );
}

function MonthlyLineChart({
  data,
  dataKey,
  color,
  yFormatter,
  tooltipFormatter,
}: {
  data: Array<{ m: string; v: number }>;
  dataKey: string;
  color: string;
  yFormatter?: (v: number) => string;
  tooltipFormatter?: (v: number) => string;
}) {
  const chartData = data.map((d) => ({ mois: d.m, [dataKey]: d.v }));
  return (
    <div className="h-44 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={chartData} margin={{ top: 8, right: 12, left: -10, bottom: 0 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" opacity={0.6} />
          <XAxis dataKey="mois" tick={{ fontSize: 10, fill: "var(--muted-foreground)" }} />
          <YAxis
            tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
            width={44}
            tickFormatter={yFormatter}
            allowDecimals={false}
          />
          <Tooltip
            formatter={(v: any) => [
              tooltipFormatter ? tooltipFormatter(Number(v)) : Number(v),
            ]}
            contentStyle={{
              fontSize: 12,
              borderRadius: 8,
              backgroundColor: "var(--card)",
              borderColor: "var(--border)",
            }}
          />
          <Line
            type="monotone"
            dataKey={dataKey}
            stroke={color}
            strokeWidth={2.5}
            dot={{ r: 3.5, fill: color, strokeWidth: 0 }}
            activeDot={{ r: 5 }}
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}

// ── Composant principal ────────────────────────────────────────────────────

export function GlobalDashboardView({
  filters,
  widgetVis,
  onNavigateTab,
  onFilterRegion,
}: GlobalDashboardViewProps) {
  const t = useT();
  const [activeModal, setActiveModal] = React.useState<StatModalType>(null);

  const apiFilter = React.useMemo(
    () => (filters ? mapFilterStateToApiFilter(filters) : {}),
    [filters],
  );

  // ── Appel API centralisé — un seul appel /api/stats/dashboard ────────
  const { data: dashboard, isLoading } = useDashboardStats(apiFilter);

  // Extraire les données depuis le payload unique (avec optional chaining)
  const liveData     = dashboard?.patrimoine?.vue_globale;
  const liveCats     = dashboard?.patrimoine?.repartition_par_categorie;
  const liveRegions  = dashboard?.patrimoine?.repartition_par_region;
  const liveTop10    = dashboard?.patrimoine?.repartition_par_structure;
  const liveTop6v    = dashboard?.patrimoine?.top_services_valeur;
  const liveTop5     = dashboard?.patrimoine?.repartition_par_projet;
  const liveEvolVal  = dashboard?.patrimoine?.evolution_mensuelle?.patrimoine;
  const liveEvolMaint= dashboard?.patrimoine?.evolution_mensuelle?.maintenance;
  const liveEvolMouv = dashboard?.patrimoine?.evolution_mensuelle?.mouvements;
  const liveGap      = dashboard?.patrimoine?.evolution_gap;

  // État de chargement unifié (un seul indicateur)
  const loadingGlobal  = isLoading;
  const loadingCats    = isLoading;
  const loadingRegions = isLoading;
  const loadingTop10   = isLoading;
  const loadingTop5    = isLoading;
  const loadingTop6v   = isLoading;
  const loadingEvolVal = isLoading;
  const loadingEvolMaint = isLoading;
  const loadingEvolMouv  = isLoading;
  const loadingGap     = isLoading;

  // ── KPI Cards — 6 valeurs depuis useVueGlobale ─────────────────────────
  // Le skeleton animé (voir loadingGlobal ? <pulse/> : k.val plus bas) couvre
  // déjà le vrai état de chargement — cette branche ne s'affiche donc qu'une
  // fois le chargement terminé sans `patrimoine` dans la réponse (ex. filtre
  // catégorie actif = BÂTIMENTS/TERRAINS, qui restructure la réponse — voir
  // StatisticsFilter.categorie_id). Afficher 0 plutôt que "—", qui laissait
  // croire à un chargement bloqué ou une erreur.
  const activeKpis = React.useMemo(() => {
    if (!liveData) return [
      { id: "total",      label: t("stats.global.kpiTotalLabel"),  val: "0", hint: t("stats.global.kpiTotalHint"), color: MINEPIA_GREEN, bg: "rgba(15, 122, 54, 0.12)" },
      { id: "valeur",     label: t("stats.global.kpiValeurLabel"),    val: "0 FCFA", hint: t("stats.global.kpiValeurHint"), color: STAT_BLUE, bg: "rgba(2, 132, 199, 0.12)" },
      { id: "actifs",     label: t("stats.global.kpiActifsLabel"),        val: "0", hint: t("stats.global.kpiActifsHintNoData"), color: MINEPIA_GREEN, bg: "rgba(15, 122, 54, 0.12)" },
      { id: "maintenance",label: t("stats.global.kpiMaintenanceLabel"),          val: "0", hint: t("stats.global.kpiMaintenanceHint"), color: STAT_ORANGE, bg: "rgba(234, 88, 12, 0.12)" },
      { id: "sortis",     label: t("stats.global.kpiSortisLabel"),            val: "0", hint: t("stats.global.kpiSortisHint"), color: STAT_RED, bg: "rgba(220, 38, 38, 0.12)" },
      { id: "structures", label: t("stats.global.kpiStructuresLabel"),      val: "0", hint: t("stats.global.kpiStructuresHint"), color: STAT_PURPLE, bg: "rgba(147, 51, 234, 0.12)" },
    ];
    return [
      {
        id: "total",
        label: t("stats.global.kpiTotalLabel"),
        val: formatNumber(liveData.total_biens),
        hint: t("stats.global.kpiTotalHint"),
        color: MINEPIA_GREEN,
        bg: "rgba(15, 122, 54, 0.12)",
      },
      {
        id: "valeur",
        label: t("stats.global.kpiValeurLabel"),
        val: liveData.valeur_totale_patrimoine != null
          ? formatMilliards(liveData.valeur_totale_patrimoine)
          : "—",
        hint: t("stats.global.kpiValeurHint"),
        color: STAT_BLUE,
        bg: "rgba(2, 132, 199, 0.12)",
      },
      {
        id: "actifs",
        label: t("stats.global.kpiActifsLabel"),
        val: formatNumber(liveData.total_biens_actifs),
        hint: t("stats.global.pctDuTotal", { pct: liveData.total_biens > 0 ? ((liveData.total_biens_actifs / liveData.total_biens) * 100).toFixed(1) : "—" }),
        color: MINEPIA_GREEN,
        bg: "rgba(15, 122, 54, 0.12)",
      },
      {
        id: "maintenance",
        label: t("stats.global.kpiMaintenanceLabel"),
        val: formatNumber(liveData.total_biens_maintenance),
        hint: t("stats.global.kpiMaintenanceHint"),
        color: STAT_ORANGE,
        bg: "rgba(234, 88, 12, 0.12)",
      },
      {
        id: "sortis",
        label: t("stats.global.kpiSortisLabel"),
        val: formatNumber(liveData.total_biens_sortis),
        hint: t("stats.global.kpiSortisHint"),
        color: STAT_RED,
        bg: "rgba(220, 38, 38, 0.12)",
      },
      {
        id: "structures",
        label: t("stats.global.kpiStructuresLabel"),
        val: formatNumber(liveData.total_services_avec_biens),
        hint: t("stats.global.kpiStructuresHint"),
        color: STAT_PURPLE,
        bg: "rgba(147, 51, 234, 0.12)",
      },
    ];
  }, [liveData, t]);

  const getKpiIcon = (id: string) => {
    switch (id) {
      case "total":      return Package;
      case "valeur":     return Wallet;
      case "actifs":     return CheckCircle2;
      case "maintenance":return Wrench;
      case "sortis":     return PackageMinus;
      default:           return Building2;
    }
  };

  // ── Donut Catégories — depuis getRepartitionParCategorie ───────────────
  const categoriesDonutData = React.useMemo(() => {
    if (!liveCats || liveCats.length === 0) return [];
    return liveCats.map((c) => ({
      id: String(c.categorie_id),
      name: c.categorie_nom,
      value: c.nombre_biens,
      pct:
        liveData && liveData.total_biens > 0
          ? `${((c.nombre_biens / liveData.total_biens) * 100).toFixed(1)}%`
          : undefined,
      color: getCategoryColor(c.categorie_nom),
    }));
  }, [liveCats, liveData]);

  const categoriesTotal = liveData?.total_biens ?? 0;

  // ── Donut Services centraux vs déconcentrés — depuis useVueGlobale ─────
  const servicesPieData = React.useMemo(() => {
    const svc = liveData?.structures_centrales_vs_deconcentrees;
    if (!svc) return [];
    const total = (svc.central ?? 0) + (svc.deconcentre ?? 0);
    return [
      {
        name: t("stats.global.structureCentrale"),
        value: svc.central ?? 0,
        pct: total > 0 ? `${(((svc.central ?? 0) / total) * 100).toFixed(1)}%` : undefined,
        color: MINEPIA_GREEN,
      },
      {
        name: t("stats.global.structureDeconcentree"),
        value: svc.deconcentre ?? 0,
        pct: total > 0 ? `${(((svc.deconcentre ?? 0) / total) * 100).toFixed(1)}%` : undefined,
        color: STAT_BLUE,
      },
    ];
  }, [liveData, t]);

  const servicesTotal =
    (liveData?.structures_centrales_vs_deconcentrees?.central ?? 0) +
    (liveData?.structures_centrales_vs_deconcentrees?.deconcentre ?? 0);

  // ── Carte géo — depuis getRepartitionParRegion ─────────────────────────
  const geoMapData = React.useMemo(() => {
    if (!liveRegions || liveRegions.length === 0) return [];
    return liveRegions.map((r) => ({ name: r.region_nom, value: r.nombre_biens }));
  }, [liveRegions]);

  // ── Top 10 structures (nombre) — depuis getRepartitionParStructure ─────
  const top10Data = React.useMemo(() => {
    if (!liveTop10 || liveTop10.length === 0) return [];
    return (liveTop10 as RepartitionStructureNombreItem[]).map((s) => ({
      name: s.service_nom,
      v: s.nombre_biens,
    }));
  }, [liveTop10]);
  const max10 = top10Data[0]?.v ?? 1;

  // ── Top 5 projets — depuis getRepartitionParProjet ─────────────────────
  const top5Data = React.useMemo(() => {
    if (!liveTop5 || liveTop5.length === 0) return [];
    return liveTop5.map((p) => ({ name: p.projet_nom, v: p.nombre_biens }));
  }, [liveTop5]);
  const max5 = top5Data[0]?.v ?? 1;

  // ── Top 6 structures (valeur) — depuis getRepartitionParStructure ──────
  const top6vData = React.useMemo(() => {
    if (!liveTop6v || liveTop6v.length === 0) return [];
    return (liveTop6v as RepartitionStructureValeurItem[]).map((s) => ({
      name: s.service_nom,
      v: s.valeur_patrimoine, // valeur brute en FCFA — affichage adaptatif via formatMilliards
    }));
  }, [liveTop6v]);
  const maxVal = top6vData[0]?.v ?? 1;

  // ── Courbes temporelles — depuis getEvolutionMensuelle ─────────────────
  // Normalise les deux shapes possibles ({ mois, valeur_patrimoine } ou { mois, nombre_biens })
  const evolValData = React.useMemo(() => {
    if (!liveEvolVal || liveEvolVal.length === 0) return [];
    return (liveEvolVal as EvolutionMensuellePatrimoineItem[]).map((e) => ({
      m: e.mois,
      v: e.valeur_patrimoine ?? 0, // valeur brute FCFA
    }));
  }, [liveEvolVal]);

  const evolMaintData = React.useMemo(() => {
    if (!liveEvolMaint || liveEvolMaint.length === 0) return [];
    return (liveEvolMaint as EvolutionMensuelleCompteItem[]).map((e) => ({
      m: e.mois,
      v: e.nombre_biens ?? 0,
    }));
  }, [liveEvolMaint]);

  const evolMouvData = React.useMemo(() => {
    if (!liveEvolMouv || liveEvolMouv.length === 0) return [];
    return (liveEvolMouv as EvolutionMensuelleCompteItem[]).map((e) => ({
      m: e.mois,
      v: e.nombre_biens ?? 0,
    }));
  }, [liveEvolMouv]);

  // ── Alerte mauvais état — depuis liveData.biens_mauvais_etat ───────────
  const mauvaisEtatNombre = liveData?.biens_mauvais_etat?.nombre_biens;
  const mauvaisEtatPct = liveData?.biens_mauvais_etat?.pourcentage;

  // ── Alerte sans information — depuis liveData.biens_sans_information ───
  const sansInfoTotal =
    liveData?.biens_sans_information != null
      ? (liveData.biens_sans_information.sans_etat ?? 0) +
        (liveData.biens_sans_information.sans_occupation ?? 0) +
        (liveData.biens_sans_information.sans_securisation ?? 0)
      : null;

  // ── Biens à réformer (partiel) — somme véhicules + bâtiments ───────────
  // Pas d'endpoint dédié "toutes catégories confondues" (2026-08-28), mais
  // vehicules_a_reformer et batiments_a_refectionner sont déjà présents dans
  // ce même appel dashboard — les additionner donne un vrai chiffre plutôt
  // qu'un tiret permanent. Reste partiel : ne couvre pas terrains/
  // informatique/mobilier, qui n'ont pas de compteur "à réformer" équivalent.
  const reformerTotal = React.useMemo(() => {
    const v = dashboard?.vehicules?.vue_globale?.vehicules_a_reformer?.total;
    const b = dashboard?.batiments?.vue_globale?.batiments_a_refectionner?.total;
    if (v == null && b == null) return null;
    return (v ?? 0) + (b ?? 0);
  }, [dashboard]);

  // ── Évolution GAP — calcul depuis liveGap ─────────────────────────────
  const gapDisplay = React.useMemo(() => {
    if (!liveGap || liveGap.length === 0) return null;
    const totalGap = liveGap.reduce((sum, g) => sum + (g.gap ?? 0), 0);
    const sign = totalGap >= 0 ? "+" : "";
    return t("stats.global.gapBiens", { value: `${sign}${totalGap.toLocaleString("fr-FR")}` });
  }, [liveGap, t]);

  // ── Classement régions (top 5) — réutilise liveRegions ────────────────
  const classementTop5 = React.useMemo(() => {
    if (!liveRegions || liveRegions.length === 0) return null;
    return [...liveRegions]
      .sort((a, b) => b.nombre_biens - a.nombre_biens)
      .slice(0, 5)
      .map((r) => ({ name: r.region_nom, value: r.nombre_biens }));
  }, [liveRegions]);

  // ── Bâtiment des lignes de widgets ────────────────────────────────────

  const row1Widgets = [
    widgetVis.catPie ? (
      <FlexCell key="catPie" minW="320px">
        <DashboardCard title={t("stats.widget.catPie")} isLoading={loadingCats}>
          {categoriesDonutData.length === 0
            ? <EmptyState message={t("stats.global.emptyCategories")} />
            : <StatDonutChart
                data={categoriesDonutData}
                total={categoriesTotal}
                label={t("stats.global.totalLabel")}
              />}
          <DetailLink
            label={t("stats.global.viewDetail")}
            onClick={() => setActiveModal("categories")}
          />
        </DashboardCard>
      </FlexCell>
    ) : null,

    widgetVis.geoMap ? (
      <FlexCell key="geoMap" minW="320px">
        <DashboardCard
          title={t("stats.global.geoTitle")}
          sub={t("stats.global.geoSub")}
          isLoading={loadingRegions}
        >
          {geoMapData.length === 0
            ? <EmptyState message={t("stats.global.emptyGeo")} height="h-40" />
            : <CameroonMapWidget
                data={geoMapData}
                onSelectRegion={(reg) => onFilterRegion?.(reg)}
              />}
          <DetailLink
            label={t("stats.global.viewAllRegions")}
            onClick={() => setActiveModal("regions")}
          />
        </DashboardCard>
      </FlexCell>
    ) : null,

    widgetVis.svcPie ? (
      <FlexCell key="svcPie" minW="320px">
        <DashboardCard
          title={t("stats.global.svcTitle")}
          isLoading={loadingGlobal}
        >
          {servicesPieData.length === 0
            ? <EmptyState message={t("stats.global.emptyServices")} />
            : <StatDonutChart
                data={servicesPieData}
                total={servicesTotal}
                label={t("stats.global.biensLabel")}
              />}
          <DetailLink
            label={t("stats.global.viewDetail")}
            onClick={() => setActiveModal("services")}
          />
        </DashboardCard>
      </FlexCell>
    ) : null,
  ].filter((w): w is React.ReactElement => w !== null);

  const row2Widgets = [
    widgetVis.top10 ? (
      <FlexCell key="top10" minW="320px">
        <DashboardCard
          title={t("stats.global.top10Title")}
          isLoading={loadingTop10}
        >
          {top10Data.length === 0
            ? <EmptyState message={t("stats.global.emptyTop10")} />
            : <div className="space-y-2">
                {top10Data.map((s, i) => (
                  <ProgressBar
                    key={s.name}
                    rank={i + 1}
                    name={s.name}
                    value={s.v}
                    max={max10}
                    color={MINEPIA_GREEN}
                  />
                ))}
              </div>}
          <DetailLink
            label={t("stats.global.viewAllStructures")}
            onClick={() => setActiveModal("top_structures")}
          />
        </DashboardCard>
      </FlexCell>
    ) : null,

    widgetVis.top5 ? (
      <FlexCell key="top5" minW="320px">
        <DashboardCard
          title={t("stats.global.top5Title")}
          sub={t("stats.global.top5Sub")}
          isLoading={loadingTop5}
        >
          {top5Data.length === 0
            ? <EmptyState message={t("stats.global.emptyTop5")} />
            : <div className="space-y-2.5">
                {top5Data.map((p, i) => (
                  <ProgressBar
                    key={p.name}
                    rank={i + 1}
                    name={p.name}
                    value={p.v}
                    max={max5}
                    color={STAT_BLUE}
                  />
                ))}
              </div>}
          <DetailLink
            label={t("stats.global.viewAllProjects")}
            onClick={() => setActiveModal("top_projets")}
          />
        </DashboardCard>
      </FlexCell>
    ) : null,

    widgetVis.top6v ? (
      <FlexCell key="top6v" minW="320px">
        <DashboardCard
          title={t("stats.global.top6vTitle")}
          sub={t("stats.global.top6vSub")}
          isLoading={loadingTop6v}
        >
          {top6vData.length === 0
            ? <EmptyState message={t("stats.global.emptyTop6v")} />
            : <div className="space-y-2.5">
                {top6vData.map((s, i) => (
                  <div key={s.name} className="flex items-center gap-2 text-[11px]">
                    <span className="w-4 shrink-0 text-right font-bold text-muted-foreground">
                      {i + 1}
                    </span>
                    <div className="flex-1 min-w-0">
                      <p className="truncate font-medium text-foreground">{s.name}</p>
                      <div className="mt-0.5 h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                          className="h-full rounded-full"
                          style={{
                            width: `${(s.v / (maxVal || 1)) * 100}%`,
                            background: STAT_PURPLE,
                          }}
                        />
                      </div>
                    </div>
                    <span className="shrink-0 font-bold tabular-nums" style={{ color: STAT_PURPLE }}>
                      {formatMilliards(s.v)}
                    </span>
                  </div>
                ))}
              </div>}
          <DetailLink
            label={t("stats.global.viewAllStructures")}
            onClick={() => setActiveModal("top_valeur")}
          />
        </DashboardCard>
      </FlexCell>
    ) : null,
  ].filter((w): w is React.ReactElement => w !== null);

  const row3Widgets = [
    widgetVis.valeur ? (
      <FlexCell key="valeur" minW="320px">
        <DashboardCard
          title={t("stats.global.valeurTitle")}
          sub={t("stats.global.valeurSub")}
          isLoading={loadingEvolVal}
        >
          <div className="mb-1 flex items-center gap-1.5">
            <span className="h-2 w-2 rounded-full" style={{ background: STAT_BLUE }} />
            <span className="text-[10px] text-muted-foreground font-medium">
              {t("stats.global.valeurCumulLabel")}
            </span>
          </div>
          {evolValData.length === 0
            ? <EmptyState message={t("stats.global.emptyEvolution")} height="h-44" />
            : <MonthlyLineChart
                data={evolValData}
                dataKey="v"
                color={STAT_BLUE}
                yFormatter={(v) => formatMilliards(v)}
                tooltipFormatter={(v) => formatMilliards(v)}
              />}
        </DashboardCard>
      </FlexCell>
    ) : null,

    widgetVis.maint ? (
      <FlexCell key="maint" minW="320px">
        <DashboardCard
          title={t("stats.global.maintTitle")}
          isLoading={loadingEvolMaint}
        >
          <div className="mb-1 flex items-center gap-1.5">
            <span className="h-2 w-2 rounded-full" style={{ background: STAT_ORANGE }} />
            <span className="text-[10px] text-muted-foreground font-medium">
              {t("stats.global.nombreBiensLabel")}
            </span>
          </div>
          {evolMaintData.length === 0
            ? <EmptyState message={t("stats.global.emptyEvolution")} height="h-44" />
            : <MonthlyLineChart data={evolMaintData} dataKey="v" color={STAT_ORANGE} />}
        </DashboardCard>
      </FlexCell>
    ) : null,

    widgetVis.mouv ? (
      <FlexCell key="mouv" minW="320px">
        <DashboardCard
          title={t("stats.global.mouvTitle")}
          sub={t("stats.global.mouvSub")}
          isLoading={loadingEvolMouv}
        >
          <div className="mb-1 flex items-center gap-1.5">
            <span className="h-2 w-2 rounded-full" style={{ background: MINEPIA_GREEN }} />
            <span className="text-[10px] text-muted-foreground font-medium">
              {t("stats.global.biensAffectesLabel")}
            </span>
          </div>
          {evolMouvData.length === 0
            ? <EmptyState message={t("stats.global.emptyEvolution")} height="h-44" />
            : <MonthlyLineChart data={evolMouvData} dataKey="v" color={MINEPIA_GREEN} />}
        </DashboardCard>
      </FlexCell>
    ) : null,
  ].filter((w): w is React.ReactElement => w !== null);

  const row4Widgets = [
    widgetVis.aEtat ? (
      <FlexCell key="aEtat" minW="200px">
        <div
          className="flex h-full flex-col justify-between rounded-xl border p-4 shadow-xs"
          style={{ borderColor: `${STAT_RED}40`, background: `${STAT_RED}08` }}
        >
          <div>
            <div className="flex items-start justify-between gap-2">
              <p className="text-[10px] font-bold uppercase tracking-wide text-red-600">
                {t("stats.global.alertMauvaisEtat")}
              </p>
              <AlertTriangle className="h-5 w-5 shrink-0 text-red-600" />
            </div>
            <p className="mt-2 text-2xl font-extrabold text-red-600 leading-none">
              {mauvaisEtatNombre != null ? formatNumber(mauvaisEtatNombre) : loadingGlobal ? "—" : "0"}
            </p>
            <p className="mt-1 text-[11px] text-muted-foreground">
              {mauvaisEtatPct != null ? t("stats.global.pctDuTotal", { pct: mauvaisEtatPct.toFixed(1) }) : loadingGlobal ? t("common.loading") : t("stats.global.pctDuTotal", { pct: "0.0" })}
            </p>
          </div>
        </div>
      </FlexCell>
    ) : null,

    widgetVis.aInfo ? (
      <FlexCell key="aInfo" minW="200px">
        <div
          className="flex h-full flex-col justify-between rounded-xl border p-4 shadow-xs"
          style={{ borderColor: `${STAT_ORANGE}40`, background: `${STAT_ORANGE}08` }}
        >
          <div>
            <div className="flex items-start justify-between gap-2">
              <p className="text-[10px] font-bold uppercase tracking-wide text-orange-600">
                {t("stats.global.alertSansInfo")}
              </p>
              <HelpCircle className="h-5 w-5 shrink-0 text-orange-600" />
            </div>
            <p className="mt-2 text-2xl font-extrabold text-orange-600 leading-none">
              {sansInfoTotal != null ? formatNumber(sansInfoTotal) : loadingGlobal ? "—" : "0"}
            </p>
            <p className="mt-1 text-[11px] text-muted-foreground">
              {liveData?.biens_sans_information != null
                ? [
                    liveData.biens_sans_information.sans_etat > 0 && t("stats.global.sansEtatCount", { count: formatNumber(liveData.biens_sans_information.sans_etat) }),
                    liveData.biens_sans_information.sans_occupation > 0 && t("stats.global.sansOccupationCount", { count: formatNumber(liveData.biens_sans_information.sans_occupation) }),
                    liveData.biens_sans_information.sans_securisation > 0 && t("stats.global.sansSecurisationCount", { count: formatNumber(liveData.biens_sans_information.sans_securisation) }),
                  ].filter(Boolean).join(" · ") || t("stats.global.toutesInfosRenseignees")
                : loadingGlobal ? t("common.loading") : t("stats.global.toutesInfosRenseignees")}
            </p>
          </div>
        </div>
      </FlexCell>
    ) : null,

    widgetVis.evol ? (
      <FlexCell key="evol" minW="200px">
        <div
          className="flex h-full flex-col justify-between rounded-xl border p-4 shadow-xs"
          style={{ borderColor: `${MINEPIA_GREEN}40`, background: `${MINEPIA_GREEN}08` }}
        >
          <div>
            <div className="flex items-start justify-between gap-2">
              <p className="text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">
                {t("stats.global.alertEvolution")}
              </p>
              <TrendingUp className="h-5 w-5 shrink-0 text-emerald-600" />
            </div>
            <p className="mt-2 text-2xl font-extrabold text-emerald-700 dark:text-emerald-400 leading-none">
              {gapDisplay ?? (loadingGap ? "…" : "—")}
            </p>
            <p className="mt-1 text-[11px] text-muted-foreground">
              {t("stats.global.gapVsExercice")}
            </p>
          </div>
        </div>
      </FlexCell>
    ) : null,

    widgetVis.classmt ? (
      <FlexCell key="classmt" minW="200px">
        <div className="flex h-full flex-col justify-between rounded-xl border border-border bg-card p-4 shadow-xs">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-wide text-foreground">
              {t("stats.global.classementTitle")}{" "}
              <span className="font-normal text-muted-foreground">{t("stats.global.classementSub")}</span>
            </p>
            <div className="mt-2 space-y-1.5">
              {classementTop5 && classementTop5.length > 0
                ? classementTop5.map((r, i) => (
                    <div key={r.name} className="flex items-center gap-1.5 text-[11px]">
                      <span className="w-3 shrink-0 text-right font-bold text-muted-foreground">
                        {i + 1}
                      </span>
                      <span className="flex-1 truncate font-medium text-foreground">
                        {r.name}
                      </span>
                      <span className="tabular-nums font-bold text-foreground">
                        {formatNumber(r.value)}
                      </span>
                    </div>
                  ))
                : <EmptyState message={t("stats.global.emptyRegions")} height="h-20" />}
            </div>
          </div>
        </div>
      </FlexCell>
    ) : null,

    widgetVis.reformer ? (
      <FlexCell key="reformer" minW="200px">
        {/* Pas d'endpoint dédié "toutes catégories confondues" — somme partielle
            véhicules + bâtiments (voir reformerTotal), déjà présents dans ce
            même appel dashboard. Ne couvre pas terrains/informatique/mobilier. */}
        <div
          className="flex h-full flex-col justify-between rounded-xl border p-4 shadow-xs"
          style={{ borderColor: `${STAT_PURPLE}40`, background: `${STAT_PURPLE}08` }}
        >
          <div>
            <div className="flex items-start justify-between gap-2">
              <p className="text-[10px] font-bold uppercase tracking-wide text-purple-700 dark:text-purple-400">
                {t("stats.global.alertReformer")}
              </p>
              <Gavel className="h-5 w-5 shrink-0 text-purple-600" />
            </div>
            <p className="mt-2 text-2xl font-extrabold text-purple-700 dark:text-purple-400 leading-none">
              {reformerTotal != null ? formatNumber(reformerTotal) : loadingGlobal ? "—" : "0"}
            </p>
            <p className="mt-1 text-[11px] text-muted-foreground">
              {t("stats.global.reformerSub")}
            </p>
          </div>
        </div>
      </FlexCell>
    ) : null,
  ].filter((w): w is React.ReactElement => w !== null);

  return (
    <div className="space-y-4">
      {/* ── KPI Cards Principaux ─────────────────────────────────────────── */}
      {widgetVis.kpiCards && (
        <div className="flex flex-wrap gap-3">
          {activeKpis.map((k) => {
            const Icon = getKpiIcon(k.id);
            return (
              <div
                key={k.id}
                className="flex-1 min-w-[170px] rounded-xl border border-border bg-card p-3.5 shadow-xs transition-all hover:shadow-sm"
              >
                <div className="flex items-center gap-2.5">
                  <div
                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                    style={{ background: k.bg }}
                  >
                    <Icon className="h-5 w-5" style={{ color: k.color }} />
                  </div>
                  <div className="min-w-0">
                    <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground leading-tight truncate">
                      {k.label}
                    </p>
                    <p className="mt-0.5 text-lg font-extrabold leading-tight text-foreground">
                      {loadingGlobal ? (
                        <span className="inline-block h-5 w-16 animate-pulse rounded bg-muted" />
                      ) : (
                        k.val
                      )}
                    </p>
                  </div>
                </div>
                <p className="mt-2 text-[10px] text-muted-foreground font-medium truncate">
                  {k.hint}
                </p>
              </div>
            );
          })}
        </div>
      )}

      {/* ── Donut Catégories | Carte Cameroun | Donut Services ──────────── */}
      {row1Widgets.length > 0 && <FlexRow>{row1Widgets}</FlexRow>}

      {/* ── Top 10 Structures | Top 5 Projets | Top 6 Valeur ───────────── */}
      {row2Widgets.length > 0 && <FlexRow>{row2Widgets}</FlexRow>}

      {/* ── Courbes temporelles ─────────────────────────────────────────── */}
      {row3Widgets.length > 0 && <FlexRow>{row3Widgets}</FlexRow>}

      {/* ── Synthèse & Alertes ──────────────────────────────────────────── */}
      {row4Widgets.length > 0 && <FlexRow>{row4Widgets}</FlexRow>}

      {/* ── Modale de détail ────────────────────────────────────────────── */}
      <StatDetailModal
        modalType={activeModal}
        filters={filters}
        onClose={() => setActiveModal(null)}
        onNavigateTab={onNavigateTab}
      />
    </div>
  );
}
