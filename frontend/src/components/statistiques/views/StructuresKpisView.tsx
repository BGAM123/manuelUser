import React from "react";
import {
  Building2,
  Home,
  Hammer,
  FileCheck2,
  FileSpreadsheet,
  AlertTriangle,
  Sparkles,
} from "lucide-react";
import {
  MINEPIA_GREEN,
  STAT_BLUE,
  STAT_ORANGE,
  STAT_PURPLE,
  STAT_TEAL,
} from "../mock-stats-data";
import type { FilterState } from "../types";
import { useDashboardStats } from "@/hooks/useStatistiques";
import { mapFilterStateToApiFilter } from "../filterMapper";
import { EmptyState } from "../shared/EmptyState";
import { useT } from "@/utils/i18n";

const formatNumber = (v: number) => v.toLocaleString("fr-FR");
const formatMilliards = (v: number) => {
  if (v === 0) return "0 FCFA";
  if (v < 1_000_000) return `${v.toLocaleString("fr-FR")} FCFA`;
  if (v < 1_000_000_000) return `${(v / 1_000_000).toFixed(2).replace(".", ",")} M FCFA`;
  return `${(v / 1_000_000_000).toFixed(2).replace(".", ",")} Mds FCFA`;
};

interface StructuresKpisViewProps {
  filters?: FilterState;
}

export function StructuresKpisView({ filters }: StructuresKpisViewProps) {
  const t = useT();
  const apiFilter = React.useMemo(
    () => (filters ? mapFilterStateToApiFilter(filters) : {}),
    [filters],
  );

  const { data: dashboard, isLoading } = useDashboardStats(apiFilter);

  // Extraction depuis dashboard.structures.*
  const liveStructures    = dashboard?.structures?.vue_globale;
  const liveCoutRefection = dashboard?.structures?.cout_refection;
  const liveAnnees        = dashboard?.structures?.annee_construction;

  const loadingGlobal = isLoading;
  const loadingCout   = isLoading;
  const loadingAnnees = isLoading;

  // ── Champs depuis vue-globale (vrais types confirmés le 2026-08-24) ──────
  const totalStructures =
    liveStructures?.total_structures?.total ?? 0;

  // "—" remplacé par 0 FCFA (2026-08-28) — même correction que sur les autres
  // vues KPI : le skeleton de chargement couvre déjà le vrai état de
  // chargement, donc un tiret ici ne représenterait qu'un montant nul
  // légitime, pas une erreur.
  const valeurInfrastructures =
    liveStructures?.valeur_infrastructures?.valeur_totale != null
      ? formatMilliards(liveStructures.valeur_infrastructures.valeur_totale)
      : formatMilliards(0);

  const avecBatiment =
    liveStructures?.avec_batiment?.avec_batiment ?? 0;

  const sansBatiment =
    liveStructures?.avec_batiment?.sans_batiment ?? 0;

  const aRefectionner =
    liveStructures?.a_refectionner?.nombre ?? 0;

  const planDisponible =
    liveStructures?.plan_disponible?.avec_plan ?? 0;

  const devisDisponible =
    liveStructures?.devis_disponible?.avec_devis ?? 0;

  // ── Coût de réfection par région ──────────────────────────────────────
  const coutRefectionData = React.useMemo(() => {
    if (!liveCoutRefection) return [];
    const items = liveCoutRefection.par_region ?? [];
    if (items.length === 0) return [];
    return items.map((r) => ({
      region: r.region_nom ?? "—",
      structures: r.nombre_structures ?? r.nombre ?? 0,
      coutM:
        (r.cout_total ?? r.cout_estime) != null
          ? ((r.cout_total ?? r.cout_estime)! / 1_000_000).toFixed(1)
          : "—",
    }));
  }, [liveCoutRefection]);

  const coutTotal =
    liveCoutRefection?.cout_total != null
      ? `${(liveCoutRefection.cout_total / 1_000_000).toFixed(0)} M FCFA`
      : "0 M FCFA";

  // ── Années de construction ─────────────────────────────────────────────
  const anneesData = React.useMemo(() => {
    if (!liveAnnees || liveAnnees.length === 0) return null;
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

  const activeRegion = filters?.region ?? "all";

  const filteredCout =
    activeRegion !== "all"
      ? coutRefectionData.filter((r: any) =>
          r.region.toLowerCase() === activeRegion.toLowerCase(),
        )
      : coutRefectionData;

  const filteredSansSiege = liveStructures?.avec_batiment
    ? [{
        nom: t("stats.structures.structuresSansSiegePropre", { count: formatNumber(liveStructures.avec_batiment.sans_batiment) }),
        region: "—", dep: "—",
        motif: t("stats.structures.surTotalStructures", { total: formatNumber(liveStructures.avec_batiment.total_structures) }),
        priorite: t("stats.structures.prioriteNormale"),
      }]
    : [];

  return (
    <div className="space-y-5">
      {/* ── KPIs ──────────────────────────────────────────────────────────── */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-400">
              <Building2 className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">{t("stats.structures.totalLabel")}</p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" /> : formatNumber(totalStructures)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">{t("stats.structures.totalHint")}</p>
        </div>

        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400">
              <Sparkles className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">{t("stats.structures.valeurLabel")}</p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? <span className="inline-block h-5 w-14 animate-pulse rounded bg-muted" /> : valeurInfrastructures}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">{t("stats.structures.valeurHint")}</p>
        </div>

        <div className="rounded-xl border border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/40 dark:bg-emerald-950/20 p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
              <Home className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 truncate">{t("stats.structures.avecBatimentLabel")}</p>
              <p className="mt-0.5 text-lg font-extrabold text-emerald-800 dark:text-emerald-300">
                {loadingGlobal ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" /> : formatNumber(avecBatiment)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-emerald-700/80 dark:text-emerald-400/80 font-medium">
            {t("stats.structures.sansSiegeDedie", { count: formatNumber(sansBatiment) })}
          </p>
        </div>

        <div className="rounded-xl border border-orange-200 bg-orange-50/50 dark:border-orange-900/40 dark:bg-orange-950/20 p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-100 text-orange-700 dark:bg-orange-900/60 dark:text-orange-300">
              <Hammer className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-orange-700 dark:text-orange-400 truncate">{t("stats.structures.refectionnerLabel")}</p>
              <p className="mt-0.5 text-lg font-extrabold text-orange-800 dark:text-orange-300">
                {loadingGlobal ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" /> : formatNumber(aRefectionner)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-orange-700/80 dark:text-orange-400/80 font-medium">
            {t("stats.structures.coutTotalHint", { cout: coutTotal })}
          </p>
        </div>

        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-700 dark:bg-teal-950/50 dark:text-teal-400">
              <FileCheck2 className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">{t("stats.structures.plansLabel")}</p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" /> : formatNumber(planDisponible)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">{t("stats.structures.plansHint")}</p>
        </div>

        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">
              <FileSpreadsheet className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">{t("stats.structures.devisLabel")}</p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" /> : formatNumber(devisDisponible)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">{t("stats.structures.devisHint")}</p>
        </div>
      </div>

      {/* ── Répartition par type & Plans ──────────────────────────────────── */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="border-b border-border pb-3 mb-3">
            <p className="text-xs font-bold uppercase tracking-wider text-foreground">{t("stats.structures.typeServiceTitle")}</p>
            <p className="text-[11px] text-muted-foreground">{t("stats.structures.typeServiceSub")}</p>
          </div>
          <div className="space-y-2.5">
            {liveStructures?.repartition_par_type_service?.length
              ? liveStructures.repartition_par_type_service.map((s, i) => ({
                  type: s.type_service,
                  count: s.nombre,
                  color: [MINEPIA_GREEN, STAT_BLUE, STAT_ORANGE, STAT_PURPLE, STAT_TEAL][i % 5],
                })).map((s) => (
                  <div key={s.type} className="flex items-center justify-between p-2 rounded-lg border border-border/70 bg-muted/10 text-xs">
                    <div className="flex items-center gap-2">
                      <span className="h-2.5 w-2.5 rounded-full shrink-0" style={{ background: s.color }} />
                      <span className="font-medium text-foreground">{s.type}</span>
                    </div>
                    <span className="font-extrabold text-foreground tabular-nums">{formatNumber(s.count)}</span>
                  </div>
                ))
              : <EmptyState message={t("stats.structures.emptyTypeService")} height="h-20" />}
          </div>
        </div>

        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="border-b border-border pb-3 mb-3">
            <p className="text-xs font-bold uppercase tracking-wider text-foreground">{t("stats.structures.plansTitle")}</p>
            <p className="text-[11px] text-muted-foreground">{t("stats.structures.plansSub")}</p>
          </div>
          <div className="space-y-2.5">
            {liveStructures?.plan_disponible ? (
              [
                { statut: t("stats.structures.avecPlanDisponible"), count: liveStructures.plan_disponible.avec_plan, color: "#10b981" },
                { statut: t("stats.structures.selonPlanType"), count: liveStructures.plan_disponible.construites_selon_plan_type_officiel, color: "#3b82f6" },
              ].map((p) => (
                <div key={p.statut} className="text-xs">
                  <div className="flex justify-between items-center mb-0.5">
                    <span className="font-medium text-foreground truncate max-w-[210px]">{p.statut}</span>
                    <span className="font-bold tabular-nums text-foreground">
                      {formatNumber(p.count)}{" "}
                      <span className="text-muted-foreground font-normal">
                        ({totalStructures > 0 ? `${((p.count / totalStructures) * 100).toFixed(1)}%` : "—"})
                      </span>
                    </span>
                  </div>
                  <div className="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                    <div className="h-full rounded-full" style={{ width: `${totalStructures > 0 ? (p.count / totalStructures) * 100 : 0}%`, background: p.color }} />
                  </div>
                </div>
              ))
            ) : (
              <EmptyState message={t("stats.structures.emptyPlans")} height="h-20" />
            )}
          </div>
        </div>
      </div>

      {/* ── Coût réfection par région & Structures sans siège ────────────── */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="border-b border-border pb-3 mb-3 flex items-center justify-between gap-2">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">{t("stats.structures.coutRefectionTitle")}</p>
              <p className="text-[11px] text-muted-foreground">{t("stats.structures.coutRefectionSub")}</p>
            </div>
            {loadingCout && <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />}
          </div>
          <div className="space-y-2">
            {filteredCout.length === 0
              ? <EmptyState message={t("stats.structures.emptyCoutRefection")} height="h-20" />
              : filteredCout.map((r: any) => (
              <div key={r.region} className="flex items-center justify-between text-xs p-1.5 rounded-lg hover:bg-muted/30 transition-colors">
                <div className="flex items-center gap-2">
                  <span className="font-medium text-foreground">{r.region}</span>
                  {r.structures > 0 && (
                    <span className="text-[10px] text-muted-foreground">{t("stats.structures.countStructuresParen", { count: formatNumber(r.structures) })}</span>
                  )}
                </div>
                <span className="font-extrabold text-orange-600 tabular-nums">
                  {r.coutM !== "—" ? t("stats.structures.coutMFCFA", { cout: r.coutM }) : r.coutM}
                </span>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-xl border border-amber-200/80 bg-card p-4 shadow-xs">
          <div className="flex items-center gap-1.5 text-amber-700 dark:text-amber-400 border-b border-border pb-3 mb-3">
            <AlertTriangle className="h-4 w-4" />
            <p className="text-xs font-bold uppercase tracking-wider">{t("stats.structures.sansSiegeTitle")}</p>
          </div>
          <div className="space-y-2.5">
            {filteredSansSiege.length === 0
              ? <EmptyState message={t("stats.structures.emptySansSiege")} height="h-20" />
              : filteredSansSiege.map((s) => (
              <div key={s.nom} className="p-2.5 rounded-lg border border-amber-100 bg-amber-50/50 dark:border-amber-900/30 dark:bg-amber-950/20 text-xs">
                <div className="flex justify-between items-start gap-2">
                  <p className="font-bold text-foreground">{s.nom}</p>
                  <span className={`text-[9px] px-1.5 py-0.5 rounded-full font-bold uppercase ${s.priorite === "Urgente" || (s.priorite as string) === "Urgente" ? "bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300" : "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"}`}>
                    {s.priorite}
                  </span>
                </div>
                <p className="text-[10px] text-muted-foreground mt-1">
                  📍 {s.region} — {s.dep} | <span className="text-foreground/80">{s.motif}</span>
                </p>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* ── Années de construction ────────────────────────────────────────── */}
      {anneesData && anneesData.length > 0 && (
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="flex items-center justify-between gap-2 mb-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">{t("stats.structures.anneesTitle")}</p>
              <p className="text-[11px] text-muted-foreground">{t("stats.structures.anneesSub")}</p>
            </div>
            {loadingAnnees && <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />}
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
            {anneesData.map((a) => (
              <div key={a.tranche} className="p-2 rounded-lg border border-border bg-muted/20 text-xs">
                <div className="flex justify-between items-center">
                  <span className="font-medium text-foreground">{a.tranche}</span>
                  <span className="font-extrabold text-foreground tabular-nums">{formatNumber(a.count)}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
