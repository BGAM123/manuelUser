import React from "react";
import {
  TrendingUp,
  AlertTriangle,
  CheckCircle2,
  ShieldAlert,
} from "lucide-react";
import {
  ResponsiveContainer,
  LineChart,
  Line,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
} from "recharts";
import {
  STAT_BLUE,
} from "../mock-stats-data";
import { CrossTableWidget, type CrossTableColumn } from "../shared/CrossTableWidget";
import { EmptyState } from "../shared/EmptyState";
import type { FilterState } from "../types";
import { useDashboardStats } from "@/hooks/useStatistiques";
import { mapFilterStateToApiFilter } from "../filterMapper";
import { useT } from "@/utils/i18n";

const formatNumber = (v: number) => v.toLocaleString("fr-FR");

interface SuiviAlertesKpisViewProps {
  filters?: FilterState;
}

export function SuiviAlertesKpisView({ filters }: SuiviAlertesKpisViewProps) {
  const t = useT();
  const apiFilter = React.useMemo(
    () => (filters ? mapFilterStateToApiFilter(filters) : {}),
    [filters],
  );

  // ── Appel API centralisé ──────────────────────────────────────────────
  const { data: dashboard, isLoading } = useDashboardStats(apiFilter);

  // Extraction depuis dashboard.suivi.*
  const liveAlertes    = dashboard?.suivi?.points_attention_prioritaires;
  const liveEvolution  = dashboard?.suivi?.evolution_pluriannuelle;
  const liveCollecte   = dashboard?.suivi?.collecte_donnees;
  // Note : departements_sans_declaration n'est pas dans le payload dashboard
  // (endpoint individuel avec categorie_id requis) — non disponible ici

  const loadingAlertes   = isLoading;
  const loadingEvol      = isLoading;
  const loadingCollecte  = isLoading;

  const activeRegion = filters?.region ?? "all";

  // ── Points d'attention — depuis getSuiviPointsAttentionPrioritaires ────
  const alertesList = React.useMemo(() => {
    if (!liveAlertes || liveAlertes.length === 0) return [];
    return liveAlertes.map((a) => ({
      site: a.site,
      region: a.region_nom ?? "—",
      dep: a.departement_nom ?? "—",
      arr: a.arrondissement_nom ?? "—",
      superficie: "—",
      urgence: a.traitement === "Non traité" ? t("stats.suivi.urgenceCritique") : t("stats.suivi.urgencePrioritaire"),
      menace: a.traitement,
      statut: a.traitement,
    }));
  }, [liveAlertes, t]);

  const totalSitesPrioritaires = liveAlertes?.length ?? alertesList.length;

  const filteredAlertes =
    activeRegion !== "all"
      ? alertesList.filter(
          (s: any) => s.region.toLowerCase() === activeRegion.toLowerCase(),
        )
      : alertesList;

  // ── Évolution pluriannuelle → tableau croisé ───────────────────────────
  const evolutionTableData = React.useMemo(() => {
    if (!liveEvolution || liveEvolution.length === 0) return [];

    // Regrouper les séries par année
    const anneeMap: Record<
      string,
      { annee: string; vehicules: number; terrains: number; batiments: number; informatique: number; totalBiens: number }
    > = {};

    liveEvolution.forEach((item) => {
      item.series?.forEach((s) => {
        if (!anneeMap[s.annee]) {
          anneeMap[s.annee] = { annee: s.annee, vehicules: 0, terrains: 0, batiments: 0, informatique: 0, totalBiens: 0 };
        }
        const catLower = item.categorie_nom?.toLowerCase() ?? "";
        if (catLower.includes("vehic") || catLower.includes("roulant")) {
          anneeMap[s.annee].vehicules += s.nombre_biens;
        } else if (catLower.includes("terrain") || catLower.includes("fonc")) {
          anneeMap[s.annee].terrains += s.nombre_biens;
        } else if (catLower.includes("batim") || catLower.includes("bâtim")) {
          anneeMap[s.annee].batiments += s.nombre_biens;
        } else if (catLower.includes("inform") || catLower.includes("info")) {
          anneeMap[s.annee].informatique += s.nombre_biens;
        }
        anneeMap[s.annee].totalBiens += s.nombre_biens;
      });
    });

    const rows = Object.values(anneeMap).sort((a, b) =>
      a.annee.localeCompare(b.annee),
    );

    // Calcul du GAP par rapport à l'année précédente
    return rows.map((row, i) => {
      const prev = rows[i - 1];
      const gap =
        prev
          ? `${row.totalBiens >= prev.totalBiens ? "+" : ""}${(row.totalBiens - prev.totalBiens).toLocaleString("fr-FR")}`
          : "—";
      return { ...row, gap };
    });
  }, [liveEvolution]);

  // ── Collecte hebdomadaire ──────────────────────────────────────────────
  const collecteData = React.useMemo(() => {
    if (!liveCollecte) return [];
    const raw = liveCollecte.nouveaux_biens_par_semaine ?? [];
    if (raw.length === 0) return [];
    let cumul = 0;
    return raw.map((s) => {
      cumul += s.nombre;
      return { sem: s.semaine, cumul };
    });
  }, [liveCollecte]);

  const evolutionColumns: CrossTableColumn[] = [
    { key: "annee", label: t("stats.suivi.colAnnee"), align: "left" },
    { key: "vehicules", label: t("stats.suivi.colVehicules"), align: "right" },
    { key: "terrains", label: t("stats.suivi.colTerrains"), align: "right" },
    { key: "batiments", label: t("stats.suivi.colBatiments"), align: "right" },
    { key: "informatique", label: t("stats.suivi.colInformatique"), align: "right" },
    { key: "totalBiens", label: t("stats.suivi.colTotalBiens"), align: "right", isTotal: true },
    { key: "gap", label: t("stats.suivi.colGap"), align: "right" },
  ];

  return (
    <div className="space-y-5">
      {/* ── KPIs Synthèse ─────────────────────────────────────────────────── */}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="rounded-xl border border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/40 dark:bg-emerald-950/20 p-4 shadow-xs">
          <div className="flex items-center justify-between">
            <p className="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">
              {t("stats.suivi.evolutionTitle")}
            </p>
            <TrendingUp className="h-5 w-5 text-emerald-600" />
          </div>
          <p className="text-3xl font-extrabold text-emerald-700 dark:text-emerald-400 mt-2">
            {loadingEvol
              ? "…"
              : liveEvolution && liveEvolution.length > 0
              ? (liveEvolution.length > 1
                  ? t("stats.suivi.seriePlural", { count: liveEvolution.length })
                  : t("stats.suivi.serieSingular", { count: liveEvolution.length }))
              : t("stats.suivi.serieSingular", { count: 0 })}
          </p>
          <p className="text-xs text-muted-foreground mt-1">
            {liveEvolution && liveEvolution.length > 0
              ? t("stats.suivi.evolutionSubData")
              : t("stats.suivi.evolutionSubEmpty")}
          </p>
        </div>

        <div className="rounded-xl border border-blue-200 bg-blue-50/50 dark:border-blue-950 dark:bg-blue-950/20 p-4 shadow-xs">
          <div className="flex items-center justify-between">
            <p className="text-xs font-bold uppercase tracking-wider text-blue-800 dark:text-blue-300">
              {t("stats.suivi.collecteTitle")}
            </p>
            <CheckCircle2 className="h-5 w-5 text-blue-600" />
          </div>
          {liveCollecte ? (
            <>
              <p className="text-3xl font-extrabold text-blue-700 dark:text-blue-400 mt-2">
                {formatNumber(
                  (liveCollecte.nouveaux_biens_par_semaine ?? []).reduce(
                    (s, w) => s + w.nombre,
                    0,
                  ),
                )}
              </p>
              <p className="text-xs text-muted-foreground mt-1">
                {t("stats.suivi.collecteSubLive")}
              </p>
            </>
          ) : (
            <>
              <p className="text-3xl font-extrabold text-blue-700 dark:text-blue-400 mt-2">
                {loadingCollecte ? "…" : "—"}
              </p>
              <p className="text-xs text-muted-foreground mt-1">{t("stats.suivi.collecteSubEmpty")}</p>
            </>
          )}
        </div>

        <div className="rounded-xl border border-red-200 bg-red-50/50 dark:border-red-900/40 dark:bg-red-950/20 p-4 shadow-xs">
          <div className="flex items-center justify-between">
            <p className="text-xs font-bold uppercase tracking-wider text-red-800 dark:text-red-300">
              {t("stats.suivi.alertesTitle")}
            </p>
            <ShieldAlert className="h-5 w-5 text-red-600" />
          </div>
          <p className="text-3xl font-extrabold text-red-700 dark:text-red-400 mt-2">
            {loadingAlertes ? "…" : formatNumber(totalSitesPrioritaires)}
          </p>
          <p className="text-xs text-muted-foreground mt-1">
            {t("stats.suivi.alertesSub")}
          </p>
        </div>
      </div>

      {/* ── Évolution pluriannuelle ────────────────────────────────────────── */}
      <div className="relative">
        {loadingEvol && (
          <div className="absolute right-4 top-4 h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500 z-10" />
        )}
        {evolutionTableData.length === 0
          ? <EmptyState message={t("stats.suivi.emptyEvolutionTable")} height="h-32" />
          : <CrossTableWidget
          title={t("stats.suivi.evolutionTableTitle")}
          subTitle={t("stats.suivi.evolutionTableSub")}
          columns={evolutionColumns}
          data={evolutionTableData}
          rowKey="annee"
        />}
      </div>

      {/* ── Collecte hebdomadaire ──────────────────────────────────────────── */}
      <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
        <div className="flex items-center justify-between border-b border-border pb-3 mb-3">
          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-foreground">
              {t("stats.suivi.collecteHebdoTitle")}
            </p>
            <p className="text-[11px] text-muted-foreground">
              {liveCollecte
                ? t("stats.suivi.collecteHebdoSubLive")
                : t("stats.suivi.collecteHebdoSubEmpty")}
            </p>
          </div>
          <div className="flex items-center gap-2">
            {loadingCollecte && (
              <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-500" />
            )}
            <span className="h-2.5 w-2.5 rounded-full bg-blue-600" />
            <span className="text-xs font-semibold text-foreground">
              {t("stats.suivi.cumulFichesLabel")}
            </span>
          </div>
        </div>
        {collecteData.length === 0
          ? <EmptyState message={t("stats.suivi.emptyCollecte")} height="h-48" />
          : <div className="h-48 w-full">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={collecteData}>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" opacity={0.6} />
              <XAxis dataKey="sem" tick={{ fontSize: 10, fill: "var(--muted-foreground)" }} />
              <YAxis tick={{ fontSize: 10, fill: "var(--muted-foreground)" }} width={48} />
              <Tooltip
                contentStyle={{
                  fontSize: 12,
                  borderRadius: 8,
                  backgroundColor: "var(--card)",
                  borderColor: "var(--border)",
                }}
              />
              <Line
                type="monotone"
                dataKey="cumul"
                stroke={STAT_BLUE}
                strokeWidth={3}
                dot={{ r: 4, fill: STAT_BLUE }}
                name={t("stats.suivi.fichesConsolideesName")}
              />
            </LineChart>
          </ResponsiveContainer>
        </div>}
        {liveCollecte?.biens_mis_a_jour_par_semaine && liveCollecte.biens_mis_a_jour_par_semaine.length > 0 && (
          <p className="mt-2 text-[11px] text-muted-foreground text-center">
            {t("stats.suivi.biensMisAJour", { count: liveCollecte.biens_mis_a_jour_par_semaine.reduce((s, w) => s + w.nombre, 0).toLocaleString("fr-FR") })}
          </p>
        )}
      </div>

      {/* ── Départements sans déclaration ────────────────────────────────── */}
      <div className="rounded-xl border border-orange-200/80 bg-card p-4 shadow-xs">
        <div className="flex items-center gap-2 border-b border-border pb-3 mb-3 text-orange-700 dark:text-orange-400">
          <AlertTriangle className="h-4 w-4" />
          <p className="text-xs font-bold uppercase tracking-wider">
            {t("stats.suivi.sansDeclarationTitle")}
          </p>
        </div>
        <EmptyState
          message={t("stats.suivi.sansDeclarationEmpty")}
          height="h-20"
        />
      </div>
      <div className="rounded-xl border border-red-200/80 bg-card p-4 shadow-xs">
        <div className="flex items-center gap-2 border-b border-border pb-3 mb-3 text-red-600">
          <ShieldAlert className="h-4 w-4" />
          <p className="text-xs font-bold uppercase tracking-wider">
            {t("stats.suivi.matriceTitle")}
          </p>
          {loadingAlertes && (
            <span className="ml-auto h-1.5 w-1.5 animate-pulse rounded-full bg-red-500" />
          )}
        </div>
        {filteredAlertes.length === 0
          ? <EmptyState message={t("stats.suivi.emptyMatrice")} height="h-24" />
          : <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          {filteredAlertes.slice(0, 8).map((s: any, i: number) => (
            <div
              key={`${s.site}-${i}`}
              className="p-3 rounded-xl border border-red-100 bg-red-50/40 dark:border-red-950 dark:bg-red-950/20 flex flex-col justify-between"
            >
              <div>
                <div className="flex justify-between items-start mb-1">
                  <span
                    className={`text-[9px] px-1.5 py-0.5 rounded-full font-extrabold uppercase ${
                      s.urgence === "CRITIQUE"
                        ? "bg-red-600 text-white"
                        : "bg-orange-600 text-white"
                    }`}
                  >
                    {s.urgence}
                  </span>
                  {s.superficie !== "—" && (
                    <span className="text-[10px] text-muted-foreground font-bold">{s.superficie}</span>
                  )}
                </div>
                <p className="text-xs font-bold text-foreground">{s.site}</p>
                <p className="text-[10px] text-muted-foreground mt-0.5">
                  📍 {s.region !== "—" ? `${s.region} — ${s.dep}` : t("stats.suivi.localisationNonRenseignee")}
                  {s.arr && s.arr !== "—" ? ` (${s.arr})` : ""}
                </p>
                <p className="text-xs text-red-700 dark:text-red-400 font-medium mt-2">
                  ⚠️ {s.menace}
                </p>
              </div>
              <div className="mt-3 pt-2 border-t border-red-100 dark:border-red-900/30 text-[10px] font-bold text-muted-foreground">
                {t("stats.suivi.traitementLabel")} <span className="text-foreground">{s.statut}</span>
              </div>
            </div>
          ))}
        </div>}
      </div>
    </div>
  );
}
