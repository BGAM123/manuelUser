import React, { useState } from "react";
import {
  Truck,
  Wallet,
  Clock,
  Gavel,
  ShieldAlert,
  FileX,
  User,
  Search,
} from "lucide-react";
import { Input } from "@/components/ui/input";
import { CrossTableWidget, type CrossTableColumn } from "../shared/CrossTableWidget";
import type { FilterState } from "../types";
import { useDashboardStats } from "@/hooks/useStatistiques";
import { mapFilterStateToApiFilter } from "../filterMapper";
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
import { useT } from "@/utils/i18n";

const formatNumber = (v: number) => v.toLocaleString("fr-FR");
const formatMilliards = (v: number) => {
  if (v === 0) return "0 FCFA";
  if (v < 1_000_000) return `${v.toLocaleString("fr-FR")} FCFA`;
  if (v < 1_000_000_000) return `${(v / 1_000_000).toFixed(2).replace(".", ",")} M FCFA`;
  return `${(v / 1_000_000_000).toFixed(2).replace(".", ",")} Mds FCFA`;
};

// ── Couleurs par état de fonctionnement (mapping backend → couleur UI) ─────
const ETAT_COLORS: Record<string, string> = {
  Neuf: MINEPIA_GREEN,
  "Bon état": STAT_TEAL,
  Bon: STAT_TEAL,
  Passable: STAT_AMBER,
  "En panne": STAT_ORANGE,
  Vétuste: STAT_ORANGE,
  "Hors d'usage": STAT_RED,
  "À réformer": STAT_PURPLE,
  "Aucune information": "#94a3b8",
};

function getEtatColor(etat: string): string {
  for (const [key, color] of Object.entries(ETAT_COLORS)) {
    if (etat.toLowerCase().includes(key.toLowerCase())) return color;
  }
  return "#94a3b8";
}

interface VehiculesKpisViewProps {
  filters?: FilterState;
}

export function VehiculesKpisView({ filters }: VehiculesKpisViewProps) {
  const t = useT();
  const [holderSearch, setHolderSearch] = useState(filters?.searchHolder ?? "");

  const apiFilter = React.useMemo(
    () => (filters ? mapFilterStateToApiFilter(filters) : {}),
    [filters],
  );

  // ── Appel API centralisé ──────────────────────────────────────────────
  const { data: dashboard, isLoading } = useDashboardStats(apiFilter);

  // Extraction depuis le payload unique dashboard.vehicules.*
  const liveVehicules   = dashboard?.vehicules?.vue_globale;
  const liveFinancement = dashboard?.vehicules?.repartition_financement;
  const liveTypes       = dashboard?.vehicules?.repartition_par_type;
  const liveAnciennete  = dashboard?.vehicules?.anciennete;
  const liveCroisement  = dashboard?.vehicules?.croisement_etat_departement;
  const liveClassement  = dashboard?.vehicules?.classement_regions;

  // Fallback : si vehicules.vue_globale.total_vehicules=0 mais que patrimoine
  // contient des biens de catégorie 28 (MATÉRIEL ROULANT), utiliser ce compteur.
  // Cela compense un bug backend où les biens roulants ne remontent pas dans la section vehicules.
  const totalVehiculesFallback = React.useMemo(() => {
    const fromVehicules = liveVehicules?.total_vehicules ?? 0;
    if (fromVehicules > 0) return fromVehicules;
    const cats = dashboard?.patrimoine?.vue_globale?.biens_par_categorie ?? [];
    return cats.find((c: any) => c.categorie_id === 28)?.nombre_biens ?? 0;
  }, [liveVehicules, dashboard]);

  const valeurParcFallback = React.useMemo(() => {
    const fromVehicules = liveVehicules?.valeur_parc?.valeur_totale;
    if (fromVehicules != null && fromVehicules > 0) return fromVehicules;
    const cats = dashboard?.patrimoine?.vue_globale?.biens_par_categorie ?? [];
    return cats.find((c: any) => c.categorie_id === 28)?.valeur_patrimoine ?? null;
  }, [liveVehicules, dashboard]);

  const loadingGlobal     = isLoading;
  const loadingFin        = isLoading;
  const loadingTypes      = isLoading;
  const loadingAnciennete = isLoading;
  const loadingCroisement = isLoading;

  // ── KPI Cards ──────────────────────────────────────────────────────────
  const totalVehicules = totalVehiculesFallback;

  // Utiliser formatMilliards adaptatif au lieu de division forcée par milliard.
  // "—" remplacé par 0 FCFA (2026-08-28) : le skeleton de chargement (voir
  // loadingGlobal ? <pulse/> ci-dessous) couvre déjà le vrai état de
  // chargement, donc cette valeur ne s'affiche qu'une fois chargée — un tiret
  // à ce stade lit comme une erreur plutôt qu'un montant nul légitime (ex.
  // filtre catégorie actif ≠ véhicules, qui vide dashboard.vehicules).
  const valeurParc =
    valeurParcFallback != null
      ? formatMilliards(valeurParcFallback)
      : formatMilliards(0);

  const ageMoyen =
    liveAnciennete?.age_moyen_annees != null
      ? `${liveAnciennete.age_moyen_annees.toFixed(1)} ans`
      : "—";

  const totalReformer =
    liveVehicules?.vehicules_a_reformer?.total ?? 0;

  const totalDisparus =
    liveVehicules?.vehicules_disparus?.nombre ?? 0;

  const totalSansCarteGrise =
    liveVehicules?.carte_grise_manquante?.nombre ?? 0;

  // ── État du parc — depuis repartition_par_etat ─────────────────────────
  const parEtatData = React.useMemo(() => {
    const raw = liveVehicules?.repartition_par_etat;
    if (!raw || raw.length === 0) return [];
    return raw.map((e) => ({
      etat: e.etat,
      count: e.nombre,
      pct: `${e.pourcentage.toFixed(1)}%`,
      color: getEtatColor(e.etat),
    }));
  }, [liveVehicules]);

  // ── Types de véhicules — depuis repartition-type ───────────────────────
  const parTypeData = React.useMemo(() => {
    if (!liveTypes || liveTypes.length === 0) return [];
    return liveTypes.map((t) => ({
      type: t.type_nom,
      count: t.nombre,
      pct:
        totalVehicules > 0
          ? `${((t.nombre / totalVehicules) * 100).toFixed(1)}%`
          : "—",
    }));
  }, [liveTypes, totalVehicules]);

  // ── Financement — depuis repartition-financement ───────────────────────
  const parFinancementData = React.useMemo(() => {
    if (!liveFinancement || liveFinancement.length === 0) return [];
    return liveFinancement.map((f) => ({
      source: f.source_financement || t("common.notSpecified"),
      count: f.nombre,
      pct:
        totalVehicules > 0
          ? `${((f.nombre / totalVehicules) * 100).toFixed(1)}%`
          : "—",
    }));
  }, [liveFinancement, totalVehicules, t]);

  // ── Pyramide des âges — depuis anciennete ─────────────────────────────
  const tranchesAgeData = React.useMemo(() => {
    const raw = liveAnciennete?.repartition_par_tranche;
    if (!raw || raw.length === 0) return [];
    const TRANCHE_COLORS: Record<string, string> = {
      "0-2 ans": MINEPIA_GREEN,
      "3-5 ans": STAT_TEAL,
      "6-10 ans": STAT_AMBER,
      "plus de 10 ans": STAT_RED,
    };
    return raw.map((tr) => ({
      tranche: tr.tranche,
      count: tr.nombre,
      color: TRANCHE_COLORS[tr.tranche] ?? STAT_BLUE,
    }));
  }, [liveAnciennete]);

  // ── Véhicules disparus par région — depuis vehicules_disparus.par_region ─
  const disparusParRegion = React.useMemo(() => {
    const raw = liveVehicules?.vehicules_disparus?.par_region;
    if (!raw || raw.length === 0) return [];
    return raw.map((r) => ({
      region: r.region_nom,
      count: r.nombre,
      motif: t("stats.vehicules.enqueteEnCours"),
    }));
  }, [liveVehicules, t]);

  // ── Tableau croisé état × département — depuis croisement-etat-departement ─
  const crossTableData = React.useMemo(() => {
    if (!liveCroisement || liveCroisement.length === 0) return [];
    return liveCroisement.map((row) => {
      const etatsMap: Record<string, number> = {};
      row.etats?.forEach((e) => {
        etatsMap[e.etat] = e.nombre;
      });
      const total = row.etats?.reduce((s, e) => s + e.nombre, 0) ?? 0;
      return {
        dep: row.departement_nom,
        neuf: etatsMap["Neuf"] ?? 0,
        bon: etatsMap["Bon"] ?? etatsMap["Bon état"] ?? 0,
        passable: etatsMap["Passable"] ?? 0,
        enPanne: etatsMap["En panne"] ?? 0,
        vetuste: etatsMap["Vétuste"] ?? 0,
        horsUsage: etatsMap["Hors d'usage"] ?? 0,
        reformer: etatsMap["À réformer"] ?? 0,
        total,
      };
    });
  }, [liveCroisement]);

  // Filtre local sur le département pour le tableau croisé
  const activeDepartement = filters?.departement ?? "all";
  const filteredCrossData =
    activeDepartement !== "all"
      ? crossTableData.filter((d) =>
          d.dep.toLowerCase().includes(activeDepartement.toLowerCase()),
        )
      : crossTableData;

  // ── Colonnes tableau croisé ────────────────────────────────────────────
  const crossColumns: CrossTableColumn[] = [
    { key: "dep", label: t("stats.vehicules.colDepartement"), align: "left" },
    { key: "neuf", label: t("stats.vehicules.colNeuf"), align: "right" },
    { key: "bon", label: t("stats.vehicules.colBon"), align: "right" },
    { key: "passable", label: t("stats.vehicules.colPassable"), align: "right" },
    { key: "enPanne", label: t("stats.vehicules.colEnPanne"), align: "right" },
    { key: "vetuste", label: t("stats.vehicules.colVetuste"), align: "right" },
    { key: "horsUsage", label: t("stats.vehicules.colHorsUsage"), align: "right" },
    { key: "reformer", label: t("stats.vehicules.colReformer"), align: "right" },
    { key: "total", label: t("stats.vehicules.colTotalVehicules"), align: "right", isTotal: true },
  ];

  // ── Classement régions ─────────────────────────────────────────────────
  const classementData = React.useMemo(() => {
    if (!liveClassement || liveClassement.length === 0) return null;
    return [...liveClassement]
      .sort((a, b) => b.nombre_vehicules - a.nombre_vehicules)
      .slice(0, 5);
  }, [liveClassement]);

  return (
    <div className="space-y-5">
      {/* ── KPIs synthèse ─────────────────────────────────────────────────── */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        {/* 1. Total véhicules */}
        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
              <Truck className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">
                {t("stats.vehicules.totalLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? (
                  <span className="inline-block h-5 w-12 animate-pulse rounded bg-muted" />
                ) : (
                  formatNumber(totalVehicules)
                )}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">
            {t("stats.vehicules.totalHint")}
          </p>
        </div>

        {/* 2. Valeur du parc */}
        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400">
              <Wallet className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">
                {t("stats.vehicules.valeurLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? (
                  <span className="inline-block h-5 w-16 animate-pulse rounded bg-muted" />
                ) : (
                  valeurParc
                )}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">
            {t("stats.vehicules.valeurHint")}
          </p>
        </div>

        {/* 3. Âge moyen */}
        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">
              <Clock className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">
                {t("stats.vehicules.ageMoyenLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingAnciennete ? (
                  <span className="inline-block h-5 w-12 animate-pulse rounded bg-muted" />
                ) : (
                  ageMoyen
                )}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">
            {t("stats.vehicules.ageMoyenHint")}
          </p>
        </div>

        {/* 4. À réformer */}
        <div className="rounded-xl border border-purple-200 bg-purple-50/50 dark:border-purple-900/40 dark:bg-purple-950/20 p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-300">
              <Gavel className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-purple-700 dark:text-purple-400 truncate">
                {t("stats.vehicules.reformerLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-purple-800 dark:text-purple-300">
                {loadingGlobal ? (
                  <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" />
                ) : (
                  formatNumber(totalReformer)
                )}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-purple-700/80 dark:text-purple-400/80 font-medium">
            {t("stats.vehicules.reformerHint")}
          </p>
        </div>

        {/* 5. Disparus */}
        <div className="rounded-xl border border-red-200 bg-red-50/50 dark:border-red-900/40 dark:bg-red-950/20 p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300">
              <ShieldAlert className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-red-700 dark:text-red-400 truncate">
                {t("stats.vehicules.disparusLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-red-800 dark:text-red-300">
                {loadingGlobal ? (
                  <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" />
                ) : (
                  formatNumber(totalDisparus)
                )}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-red-700/80 dark:text-red-400/80 font-medium">
            {t("stats.vehicules.disparusHint")}
          </p>
        </div>

        {/* 6. Sans carte grise */}
        <div className="rounded-xl border border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20 p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300">
              <FileX className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400 truncate">
                {t("stats.vehicules.sansCarteGriseLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-amber-800 dark:text-amber-300">
                {loadingGlobal ? (
                  <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" />
                ) : (
                  formatNumber(totalSansCarteGrise)
                )}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-amber-700/80 dark:text-amber-400/80 font-medium">
            {t("stats.vehicules.sansCarteGriseHint")}
          </p>
        </div>
      </div>

      {/* ── État du parc & Types ──────────────────────────────────────────── */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* Répartition par état */}
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="border-b border-border pb-3 mb-3 flex items-center justify-between gap-2">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">
                {t("stats.vehicules.etatSectionTitle")}
              </p>
              <p className="text-[11px] text-muted-foreground">
                {t("stats.vehicules.etatSectionSub")}
              </p>
            </div>
            {loadingGlobal && (
              <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
            )}
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
            {parEtatData.length === 0
              ? <div className="col-span-4"><EmptyState message={t("stats.vehicules.emptyEtat")} /></div>
              : parEtatData.map((e) => (
              <div
                key={e.etat}
                className="p-2 rounded-lg border border-border/70 bg-muted/20"
              >
                <div className="flex items-center gap-1.5">
                  <span
                    className="h-2 w-2 rounded-full"
                    style={{ background: e.color }}
                  />
                  <span className="text-[11px] font-semibold truncate text-foreground">
                    {e.etat}
                  </span>
                </div>
                <p className="text-base font-extrabold mt-1 text-foreground">
                  {formatNumber(e.count)}
                </p>
                <p className="text-[10px] text-muted-foreground">{e.pct}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Répartition par type */}
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="border-b border-border pb-3 mb-3 flex items-center justify-between gap-2">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">
                {t("stats.vehicules.typeSectionTitle")}
              </p>
              <p className="text-[11px] text-muted-foreground">
                {t("stats.vehicules.typeSectionSub")}
              </p>
            </div>
            {loadingTypes && (
              <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
            )}
          </div>
          <div className="space-y-2.5">
            {parTypeData.length === 0
              ? <EmptyState message={t("stats.vehicules.emptyType")} />
              : parTypeData.map((t) => (
              <div key={t.type} className="text-xs">
                <div className="flex justify-between items-center mb-0.5">
                  <span className="font-medium text-foreground">{t.type}</span>
                  <span className="font-bold tabular-nums text-foreground">
                    {formatNumber(t.count)}{" "}
                    <span className="text-muted-foreground font-normal">
                      ({t.pct})
                    </span>
                  </span>
                </div>
                <div className="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                  <div
                    className="h-full bg-emerald-600 rounded-full"
                    style={{
                      width:
                        totalVehicules > 0
                          ? `${(t.count / totalVehicules) * 100}%`
                          : "0%",
                    }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* ── Financement & Ancienneté & Disparus ──────────────────────────── */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {/* Sources de financement */}
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="flex items-center justify-between gap-2 mb-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">
                {t("stats.vehicules.financementTitle")}
              </p>
              <p className="text-[11px] text-muted-foreground">
                {t("stats.vehicules.financementSub")}
              </p>
            </div>
            {loadingFin && (
              <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
            )}
          </div>
          <div className="space-y-3">
            {parFinancementData.length === 0
              ? <EmptyState message={t("stats.vehicules.emptyFinancement")} />
              : parFinancementData.map((f) => (
              <div
                key={f.source}
                className="p-2.5 rounded-lg border border-border/70 bg-muted/10"
              >
                <div className="flex justify-between items-start gap-2">
                  <p className="text-xs font-medium text-foreground">{f.source}</p>
                  <span className="text-xs font-bold tabular-nums text-emerald-700 dark:text-emerald-400">
                    {f.pct}
                  </span>
                </div>
                <p className="text-sm font-extrabold mt-1 text-foreground">
                  {t("stats.vehicules.countVehicules", { count: formatNumber(f.count) })}
                </p>
              </div>
            ))}
          </div>
        </div>

        {/* Pyramide des âges */}
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="flex items-center justify-between gap-2 mb-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">
                {t("stats.vehicules.pyramidTitle")}
              </p>
              <p className="text-[11px] text-muted-foreground">
                {liveAnciennete?.age_moyen_annees != null
                  ? t("stats.vehicules.pyramidSubKnown", {
                      age: liveAnciennete.age_moyen_annees.toFixed(1),
                      known: liveAnciennete.vehicules_avec_date_connue != null ? formatNumber(liveAnciennete.vehicules_avec_date_connue) : "—",
                      total: liveAnciennete.total_vehicules != null ? formatNumber(liveAnciennete.total_vehicules) : "—",
                    })
                  : t("stats.vehicules.pyramidSubDefault")}
              </p>
            </div>
            {loadingAnciennete && (
              <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
            )}
          </div>
          <div className="space-y-3">
            {tranchesAgeData.length === 0
              ? <EmptyState message={t("stats.vehicules.emptyAnciennete")} />
              : tranchesAgeData.map((tr) => (
              <div key={tr.tranche}>
                <div className="flex justify-between items-center text-xs mb-1">
                  <span className="font-medium text-foreground">{tr.tranche}</span>
                  <span className="font-bold tabular-nums text-foreground">
                    {formatNumber(tr.count)}
                  </span>
                </div>
                <div className="h-2 w-full bg-muted rounded-full overflow-hidden">
                  <div
                    className="h-full rounded-full"
                    style={{
                      width:
                        totalVehicules > 0
                          ? `${(tr.count / totalVehicules) * 100}%`
                          : "0%",
                      background: tr.color,
                    }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Véhicules disparus par région */}
        <div className="rounded-xl border border-red-200/80 bg-card p-4 shadow-xs">
          <div className="flex items-center gap-1.5 text-red-600 mb-1">
            <ShieldAlert className="h-4 w-4" />
            <p className="text-xs font-bold uppercase tracking-wider">
              {t("stats.vehicules.disparusTitle")}
            </p>
          </div>
          <p className="text-[11px] text-muted-foreground mb-3">
            {liveVehicules?.vehicules_disparus?.pourcentage != null
              ? t("stats.vehicules.disparusSubWithPct", { pct: liveVehicules.vehicules_disparus.pourcentage.toFixed(1) })
              : t("stats.vehicules.disparusSub")}
          </p>
          <div className="space-y-2">
            {disparusParRegion.length === 0
              ? <EmptyState message={t("stats.vehicules.emptyDisparus")} />
              : disparusParRegion.map((d) => (
              <div
                key={d.region}
                className="flex items-center justify-between p-2 rounded-lg bg-red-50/60 dark:bg-red-950/20 border border-red-100 dark:border-red-900/30 text-xs"
              >
                <div>
                  <p className="font-bold text-red-900 dark:text-red-300">
                    {d.region}
                  </p>
                  <p className="text-[10px] text-muted-foreground">{d.motif}</p>
                </div>
                <span className="font-extrabold text-red-600 text-sm">
                  {formatNumber(d.count)}
                </span>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* ── Classement régions ────────────────────────────────────────────── */}
      {classementData && classementData.length > 0 && (
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <p className="text-xs font-bold uppercase tracking-wider text-foreground mb-3">
            {t("stats.vehicules.classementTitle")}
          </p>
          <div className="space-y-2">
            {classementData.map((r, i) => (
              <div key={r.region_id} className="flex items-center gap-2 text-xs">
                <span className="w-4 shrink-0 text-right font-bold text-muted-foreground">
                  {i + 1}
                </span>
                <div className="flex-1 min-w-0">
                  <p className="truncate font-medium text-foreground">
                    {r.region_nom}
                  </p>
                  <div className="mt-0.5 h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                      className="h-full rounded-full bg-emerald-600"
                      style={{
                        width: `${(r.nombre_vehicules / (classementData[0]?.nombre_vehicules ?? 1)) * 100}%`,
                      }}
                    />
                  </div>
                </div>
                <span className="shrink-0 font-bold tabular-nums text-foreground">
                  {formatNumber(r.nombre_vehicules)}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ── Tableau Croisé État × Département ─────────────────────────────── */}
      <div className="relative">
        {loadingCroisement && (
          <div className="absolute right-4 top-4 h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500 z-10" />
        )}
        <CrossTableWidget
          title={t("stats.vehicules.crossTitle")}
          subTitle={t("stats.vehicules.crossSub")}
          columns={crossColumns}
          data={filteredCrossData}
          rowKey="dep"
          searchPlaceholder={t("stats.vehicules.crossSearchPlaceholder")}
        />
      </div>

      {/* ── Suivi par détenteur (section mock — nécessite matricule de l'utilisateur) ─ */}
      <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-border pb-3 mb-4">
          <div>
            <div className="flex items-center gap-2">
              <User className="h-4 w-4 text-emerald-600" />
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">
                {t("stats.vehicules.detenteurTitle")}
              </p>
            </div>
            <p className="text-[11px] text-muted-foreground">
              {t("stats.vehicules.detenteurSub")}
            </p>
          </div>
          <div className="relative w-full sm:w-72">
            <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
            <Input
              type="text"
              placeholder={t("stats.vehicules.detenteurPlaceholder")}
              value={holderSearch}
              onChange={(e) => setHolderSearch(e.target.value)}
              className="h-8.5 pl-8 text-xs bg-background"
            />
          </div>
        </div>

        {/* Toujours EmptyState pour les détenteurs — endpoint nécessite un matricule */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <EmptyState message={t("stats.vehicules.emptyDetenteur")} height="h-24" />
        </div>
      </div>
    </div>
  );
}
