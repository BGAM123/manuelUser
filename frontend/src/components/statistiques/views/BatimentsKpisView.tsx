import React from "react";
import {
  Home,
  Hammer,
  DoorOpen,
  Scale,
  FileCheck2,
  DollarSign,
} from "lucide-react";
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
} from "recharts";
import {
  MINEPIA_GREEN,
  STAT_BLUE,
  STAT_ORANGE,
  STAT_PURPLE,
  STAT_RED,
  STAT_TEAL,
  STAT_AMBER,
} from "../mock-stats-data";
import { EmptyState } from "../shared/EmptyState";
import { CrossTableWidget, type CrossTableColumn } from "../shared/CrossTableWidget";
import type { FilterState } from "../types";
import { useDashboardStats } from "@/hooks/useStatistiques";
import { mapFilterStateToApiFilter } from "../filterMapper";
import { useT } from "@/utils/i18n";

const formatNumber = (v: number) => v.toLocaleString("fr-FR");
const formatMilliards = (v: number) => {
  if (v === 0) return "0 FCFA";
  if (v < 1_000_000) return `${v.toLocaleString("fr-FR")} FCFA`;
  if (v < 1_000_000_000) return `${(v / 1_000_000).toFixed(2).replace(".", ",")} M FCFA`;
  return `${(v / 1_000_000_000).toFixed(2).replace(".", ",")} Mds FCFA`;
};

const ETAT_COLORS: Record<string, string> = {
  Neuf: MINEPIA_GREEN,
  "Bon état": STAT_TEAL,
  Bon: STAT_TEAL,
  Passable: STAT_AMBER,
  Vétuste: STAT_ORANGE,
  "À réfectionner": STAT_RED,
  Inachevé: STAT_PURPLE,
  "Hors d'usage": STAT_RED,
  "Aucune information": "#94a3b8",
};
function getEtatColor(etat: string): string {
  for (const [k, c] of Object.entries(ETAT_COLORS)) {
    if (etat.toLowerCase().includes(k.toLowerCase())) return c;
  }
  return "#94a3b8";
}

interface BatimentsKpisViewProps {
  filters?: FilterState;
}

export function BatimentsKpisView({ filters }: BatimentsKpisViewProps) {
  const t = useT();
  const apiFilter = React.useMemo(
    () => (filters ? mapFilterStateToApiFilter(filters) : {}),
    [filters],
  );

  // ── Appel API centralisé ──────────────────────────────────────────────
  const { data: dashboard, isLoading } = useDashboardStats(apiFilter);

  // Extraction depuis dashboard.batiments.*
  // financement.par_source_financement est la vraie clé backend (confirmée)
  const liveBatiments      = dashboard?.batiments?.vue_globale;
  const liveLitiges        = dashboard?.batiments?.en_litige;
  const liveParSource      = dashboard?.batiments?.financement?.par_source_financement ?? [];
  const liveAnnees         = dashboard?.batiments?.annee_construction;
  const liveCroisement     = dashboard?.batiments?.croisement_etat_departement;
  const liveClassement     = dashboard?.batiments?.classement_regions;

  const loadingGlobal = isLoading;
  const loadingLitiges = isLoading;
  const loadingFin    = isLoading;
  const loadingAnnees = isLoading;
  const loadingCrois  = isLoading;

  // ── KPIs ────────────────────────────────────────────────────────────────
  const totalBatiments = liveBatiments?.total_batiments ?? 0;
  const aRefectionner =
    liveBatiments?.batiments_a_refectionner?.total ?? 0;
  const batimentsLoues =
    liveBatiments?.batiments_loues?.nombre ?? 0;
  const avecTitre =
    liveBatiments?.titre_foncier?.nombre_avec_titre ?? 0;
  const enLitige = liveLitiges?.nombre ?? 0;

  // ── État du parc ───────────────────────────────────────────────────────
  const parEtatData = React.useMemo(() => {
    const raw = liveBatiments?.repartition_par_etat;
    if (!raw || raw.length === 0) return [];
    return raw.map((e) => ({
      etat: e.etat,
      count: e.nombre,
      pct: `${e.pourcentage.toFixed(1)}%`,
      color: getEtatColor(e.etat),
    }));
  }, [liveBatiments]);

  // ── Réfections par région ──────────────────────────────────────────────
  // Coût estimé : batiments_a_refectionner.par_region ne fournit aucun champ
  // de coût (juste region_id/region_nom/nombre) — on le récupère en croisant
  // avec structures.cout_refection.par_region par region_id, déjà présent
  // dans ce même appel dashboard (pas de requête supplémentaire).
  const coutParRegionId = React.useMemo(() => {
    const raw = dashboard?.structures?.cout_refection?.par_region ?? [];
    const map = new Map<number, number>();
    raw.forEach((r) => {
      const cout = r.cout_total ?? r.cout_estime;
      if (r.region_id != null && cout != null) map.set(r.region_id, cout);
    });
    return map;
  }, [dashboard]);

  const refectionParRegion = React.useMemo(() => {
    const raw = liveBatiments?.batiments_a_refectionner?.par_region;
    if (!raw || raw.length === 0) return [];
    return raw.map((r) => {
      const cout = coutParRegionId.get(r.region_id);
      return {
        region: r.region_nom,
        count: r.nombre,
        coutEstimeM: cout != null ? (cout / 1_000_000).toFixed(1) : "—",
      };
    });
  }, [liveBatiments, coutParRegionId]);

  // ── Occupation ─────────────────────────────────────────────────────────
  const occupationData = React.useMemo(() => {
    const raw = liveBatiments?.occupation;
    if (!raw || raw.length === 0) return [];
    const OCCUP_COLORS: Record<string, string> = {
      Régulière: MINEPIA_GREEN,
      Irrégulière: STAT_RED,
      Cohabitation: STAT_ORANGE,
      Inoccupé: STAT_PURPLE,
      "Aucune information": "#94a3b8",
    };
    return raw.map((o) => ({
      statut: o.occupation,
      count: o.nombre,
      pct: `${o.pourcentage.toFixed(1)}%`,
      color: OCCUP_COLORS[o.occupation] ?? STAT_BLUE,
    }));
  }, [liveBatiments]);

  // ── Financement ────────────────────────────────────────────────────────
  const financementData = React.useMemo(() => {
    if (!liveParSource || liveParSource.length === 0) return [];
    return liveParSource.map((f) => ({
      source: f.source_financement || t("common.notSpecified"),
      count: f.nombre ?? 0,
      pct: totalBatiments > 0 ? `${((f.nombre ?? 0) / totalBatiments * 100).toFixed(1)}%` : "—",
    }));
  }, [liveParSource, totalBatiments, t]);

  // ── Années de construction ─────────────────────────────────────────────
  const anneesData = React.useMemo(() => {
    if (!liveAnnees || liveAnnees.length === 0) return [];
    // Regrouper en tranches de 10 ans
    const buckets: Record<string, number> = {};
    liveAnnees.forEach((a) => {
      const year = parseInt(a.annee);
      if (!isNaN(year)) {
        const decade = `${Math.floor(year / 10) * 10}s`;
        buckets[decade] = (buckets[decade] ?? 0) + a.nombre;
      }
    });
    return Object.entries(buckets)
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([tranche, count]) => ({ tranche, count }));
  }, [liveAnnees]);

  // ── Tableau croisé ─────────────────────────────────────────────────────
  const crossTableData = React.useMemo(() => {
    if (!liveCroisement || liveCroisement.length === 0) return [];
    return liveCroisement.map((row) => {
      const m: Record<string, number> = {};
      row.etats?.forEach((e) => { m[e.etat] = e.nombre; });
      const total = row.etats?.reduce((s, e) => s + e.nombre, 0) ?? 0;
      return {
        dep: row.departement_nom,
        neuf: m["Neuf"] ?? 0,
        bon: m["Bon"] ?? m["Bon état"] ?? 0,
        passable: m["Passable"] ?? 0,
        vetuste: m["Vétuste"] ?? 0,
        refection: m["À réfectionner"] ?? 0,
        inacheve: m["Inachevé"] ?? 0,
        total,
      };
    });
  }, [liveCroisement]);

  const activeDepartement = filters?.departement ?? "all";
  const activeRegion = filters?.region ?? "all";
  const filteredCross = activeDepartement !== "all"
    ? crossTableData.filter((d) => d.dep.toLowerCase().includes(activeDepartement.toLowerCase()))
    : crossTableData;

  const filteredRefection = activeRegion !== "all"
    ? refectionParRegion.filter((r) => r.region.toLowerCase() === activeRegion.toLowerCase())
    : refectionParRegion;

  const crossColumns: CrossTableColumn[] = [
    { key: "dep", label: t("stats.batiments.colDepartement"), align: "left" },
    { key: "neuf", label: t("stats.batiments.colNeuf"), align: "right" },
    { key: "bon", label: t("stats.batiments.colBonEtat"), align: "right" },
    { key: "passable", label: t("stats.batiments.colPassable"), align: "right" },
    { key: "vetuste", label: t("stats.batiments.colVetuste"), align: "right" },
    { key: "refection", label: t("stats.batiments.colRefection"), align: "right" },
    { key: "inacheve", label: t("stats.batiments.colInacheve"), align: "right" },
    { key: "total", label: t("stats.batiments.colTotalBatiments"), align: "right", isTotal: true },
  ];

  return (
    <div className="space-y-5">
      {/* ── KPIs ──────────────────────────────────────────────────────────── */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-700 dark:bg-orange-950/50 dark:text-orange-400">
              <Home className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">{t("stats.batiments.totalLabel")}</p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? <span className="inline-block h-5 w-12 animate-pulse rounded bg-muted" /> : formatNumber(totalBatiments)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">{t("stats.batiments.totalHint")}</p>
        </div>

        <div className="rounded-xl border border-orange-200 bg-orange-50/50 dark:border-orange-900/40 dark:bg-orange-950/20 p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-100 text-orange-700 dark:bg-orange-900/60 dark:text-orange-300">
              <Hammer className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-orange-700 dark:text-orange-400 truncate">{t("stats.batiments.refectionnerLabel")}</p>
              <p className="mt-0.5 text-lg font-extrabold text-orange-800 dark:text-orange-300">
                {loadingGlobal ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" /> : formatNumber(aRefectionner)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-orange-700/80 dark:text-orange-400/80 font-medium">{t("stats.batiments.refectionnerHint")}</p>
        </div>

        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400">
              <DoorOpen className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">{t("stats.batiments.totalPiecesLabel")}</p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">—</p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">{t("stats.batiments.nonDisponible")}</p>
        </div>

        <div className="rounded-xl border border-red-200 bg-red-50/50 dark:border-red-900/40 dark:bg-red-950/20 p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300">
              <Scale className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-red-700 dark:text-red-400 truncate">{t("stats.batiments.litigeLabel")}</p>
              <p className="mt-0.5 text-lg font-extrabold text-red-800 dark:text-red-300">
                {loadingLitiges ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" /> : formatNumber(enLitige)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-red-700/80 dark:text-red-400/80 font-medium">{t("stats.batiments.litigeHint")}</p>
        </div>

        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
              <FileCheck2 className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">{t("stats.batiments.titresFonciersLabel")}</p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" /> : formatNumber(avecTitre)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">{t("stats.batiments.titresFonciersHint")}</p>
        </div>

        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-400">
              <DollarSign className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">{t("stats.batiments.louesLabel")}</p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" /> : formatNumber(batimentsLoues)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">{t("stats.batiments.nonDisponible")}</p>
        </div>
      </div>

      {/* ── État & Réfections ─────────────────────────────────────────────── */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="border-b border-border pb-3 mb-3 flex items-center justify-between gap-2">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">{t("stats.batiments.etatTitle")}</p>
              <p className="text-[11px] text-muted-foreground">{t("stats.batiments.etatSub")}</p>
            </div>
            {loadingGlobal && <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />}
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
            {parEtatData.length === 0
              ? <EmptyState message={t("stats.batiments.emptyEtat")} />
              : parEtatData.map((e) => (
              <div key={e.etat} className="p-2.5 rounded-lg border border-border/70 bg-muted/20">
                <div className="flex items-center gap-1.5">
                  <span className="h-2 w-2 rounded-full" style={{ background: e.color }} />
                  <span className="text-[11px] font-semibold truncate text-foreground">{e.etat}</span>
                </div>
                <p className="text-base font-extrabold mt-1 text-foreground">{formatNumber(e.count)}</p>
                <p className="text-[10px] text-muted-foreground">{e.pct}</p>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="border-b border-border pb-3 mb-3">
            <p className="text-xs font-bold uppercase tracking-wider text-foreground">{t("stats.batiments.refectionRegionTitle")}</p>
            <p className="text-[11px] text-muted-foreground">{t("stats.batiments.refectionRegionSub")}</p>
          </div>
          <div className="space-y-2">
            {filteredRefection.length === 0
              ? <EmptyState message={t("stats.batiments.emptyRefectionRegion")} height="h-20" />
              : filteredRefection.map((r) => (
              <div key={r.region} className="flex items-center justify-between text-xs p-1.5 rounded-md hover:bg-muted/40 transition-colors">
                <span className="font-medium text-foreground">{r.region}</span>
                <div className="flex items-center gap-3">
                  <span className="font-bold text-orange-600">{t("stats.batiments.countEdifices", { count: r.count })}</span>
                  {(r as any).coutEstimeM !== "—" && (
                    <span className="font-extrabold text-foreground tabular-nums text-right w-24">
                      {t("stats.batiments.coutEstime", { cout: (r as any).coutEstimeM })}
                    </span>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* ── Occupation & Financement & Années ────────────────────────────── */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <p className="text-xs font-bold uppercase tracking-wider text-foreground mb-1">{t("stats.batiments.occupationTitle")}</p>
          <p className="text-[11px] text-muted-foreground mb-3">{t("stats.batiments.occupationSub")}</p>
          <div className="space-y-2.5">
            {occupationData.length === 0
              ? <EmptyState message={t("stats.batiments.emptyOccupation")} height="h-20" />
              : occupationData.map((o) => (
              <div key={o.statut} className="text-xs">
                <div className="flex justify-between items-center mb-0.5">
                  <span className="font-medium text-foreground truncate max-w-[190px]">{o.statut}</span>
                  <span className="font-bold tabular-nums text-foreground">
                    {formatNumber(o.count)}{" "}
                    <span className="text-muted-foreground font-normal">({o.pct})</span>
                  </span>
                </div>
                <div className="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                  <div className="h-full rounded-full" style={{ width: `${totalBatiments > 0 ? (o.count / totalBatiments) * 100 : 0}%`, background: o.color }} />
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="flex items-center justify-between gap-2 mb-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">{t("stats.batiments.financementTitle")}</p>
              <p className="text-[11px] text-muted-foreground">{t("stats.batiments.financementSub")}</p>
            </div>
            {loadingFin && <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />}
          </div>
          <div className="space-y-3">
            {financementData.length === 0
              ? <EmptyState message={t("stats.batiments.emptyFinancement")} height="h-20" />
              : financementData.map((f) => (
              <div key={f.source} className="p-2.5 rounded-lg border border-border/70 bg-muted/10 flex justify-between items-center">
                <div>
                  <p className="text-xs font-medium text-foreground">{f.source}</p>
                  <p className="text-[10px] text-muted-foreground mt-0.5">{t("stats.batiments.countBatiments", { count: formatNumber(f.count) })}</p>
                </div>
                <span className="text-xs font-extrabold text-orange-600 tabular-nums">{f.pct}</span>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="flex items-center justify-between gap-2 mb-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">{t("stats.batiments.anneesTitle")}</p>
              <p className="text-[11px] text-muted-foreground">{t("stats.batiments.anneesSub")}</p>
            </div>
            {loadingAnnees && <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />}
          </div>
          <div className="space-y-2.5">
            {anneesData.length === 0
              ? <EmptyState message={t("stats.batiments.emptyAnnees")} height="h-20" />
              : anneesData.map((a) => (
              <div key={a.tranche} className="p-2 rounded-lg border border-border bg-muted/20 text-xs">
                <div className="flex justify-between items-center">
                  <span className="font-medium text-foreground">{a.tranche}</span>
                  <span className="font-extrabold text-foreground tabular-nums">{formatNumber(a.count)}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* ── Classement régions ────────────────────────────────────────────── */}
      {liveClassement && liveClassement.length > 0 && (
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <p className="text-xs font-bold uppercase tracking-wider text-foreground mb-3">{t("stats.batiments.classementTitle")}</p>
          <div className="space-y-2">
            {[...liveClassement].sort((a, b) => b.nombre_batiments - a.nombre_batiments).slice(0, 5).map((r, i) => (
              <div key={r.region_id} className="flex items-center gap-2 text-xs">
                <span className="w-4 shrink-0 text-right font-bold text-muted-foreground">{i + 1}</span>
                <div className="flex-1 min-w-0">
                  <p className="truncate font-medium text-foreground">{r.region_nom}</p>
                  <div className="mt-0.5 h-1.5 overflow-hidden rounded-full bg-muted">
                    <div className="h-full rounded-full bg-orange-500" style={{ width: `${(r.nombre_batiments / (liveClassement[0]?.nombre_batiments ?? 1)) * 100}%` }} />
                  </div>
                </div>
                <span className="shrink-0 font-bold tabular-nums text-foreground">{formatNumber(r.nombre_batiments)}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ── Tableau croisé ────────────────────────────────────────────────── */}
      <div className="relative">
        {loadingCrois && <div className="absolute right-4 top-4 h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500 z-10" />}
        <CrossTableWidget
          title={t("stats.batiments.crossTitle")}
          subTitle={t("stats.batiments.crossSub")}
          columns={crossColumns}
          data={filteredCross}
          rowKey="dep"
        />
      </div>
    </div>
  );
}
