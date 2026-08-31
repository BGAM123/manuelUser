/**
 * InformatiqueKpisView — Vue Matériel informatique & bureautique
 *
 * Stratégie API :
 * - La catégorie "MATÉRIEL INFORMATIQUE" a l'id=31 en base (vérifié le 2026-08-24).
 * - Tous les appels injectent automatiquement categorie_id=31 dans le filtre.
 * - Les KPIs globaux (total, valeur, mauvais état) reflètent les vraies données
 *   filtrées (dashboard.patrimoine.vue_globale). En revanche, la typologie, la
 *   pyramide d'âges et le croisement état×département lisent
 *   dashboard.vehicules.* — une clé absente de la réponse quand categorie_id=31
 *   est actif (voir Cas 3 signalé le 2026-08-28) : ces 3 widgets resteront
 *   vides même une fois de vrais biens IT saisis, tant que le backend n'expose
 *   pas un domaine dédié `data.informatique`.
 */

import React from "react";
import {
  Monitor,
  Wallet,
  Clock,
  Sparkles,
} from "lucide-react";
import {
  MINEPIA_GREEN,
  STAT_BLUE,
  STAT_ORANGE,
  STAT_PURPLE,
  STAT_RED,
  STAT_TEAL,
  STAT_AMBER,
} from "../mock-stats-data";
import { CrossTableWidget, type CrossTableColumn } from "../shared/CrossTableWidget";
import { EmptyState } from "../shared/EmptyState";
import type { FilterState } from "../types";
import { useDashboardStats } from "@/hooks/useStatistiques";
import { mapFilterStateToApiFilter } from "../filterMapper";
import type { StatisticsFilter } from "@/api/statistiques";
import { useT } from "@/utils/i18n";

const formatNumber = (v: number) => v.toLocaleString("fr-FR");
const formatMilliards = (v: number) => {
  if (v === 0) return "0 FCFA";
  if (v < 1_000_000) return `${v.toLocaleString("fr-FR")} FCFA`;
  if (v < 1_000_000_000) return `${(v / 1_000_000).toFixed(2).replace(".", ",")} M FCFA`;
  return `${(v / 1_000_000_000).toFixed(2).replace(".", ",")} Mds FCFA`;
};

// ID catégorie "MATÉRIEL INFORMATIQUE" vérifié sur le backend
const CATEGORIE_INFORMATIQUE_ID = 31;

const ETAT_COLORS: Record<string, string> = {
  Neuf: MINEPIA_GREEN,
  "Bon état": STAT_TEAL,
  Bon: STAT_TEAL,
  Passable: STAT_AMBER,
  "En panne": STAT_ORANGE,
  Vétuste: STAT_ORANGE,
  "Hors d'usage": STAT_RED,
  "Aucune information": "#94a3b8",
};
function getEtatColor(etat: string): string {
  for (const [k, c] of Object.entries(ETAT_COLORS)) {
    if (etat.toLowerCase().includes(k.toLowerCase())) return c;
  }
  return "#94a3b8";
}

interface InformatiqueKpisViewProps {
  filters?: FilterState;
}

export function InformatiqueKpisView({ filters }: InformatiqueKpisViewProps) {
  const t = useT();
  // Injecter categorie_id=31 dans TOUS les appels API de cette vue.
  // Paramètre réel confirmé sur le Swagger (2026-08-28) : `categorie_id` au
  // singulier — l'ancien `categorie_ids` (pluriel) était silencieusement
  // ignoré par le backend, ce qui affichait les totaux de TOUT le patrimoine
  // au lieu des seuls biens informatiques.
  const apiFilter: StatisticsFilter = React.useMemo(() => {
    const base = filters ? mapFilterStateToApiFilter(filters) : {};
    return { ...base, categorie_id: CATEGORIE_INFORMATIQUE_ID };
  }, [filters]);

  // ── Appel API centralisé — dashboard filtré sur categorie IT ─────────
  // categorie_id=31 injecté pour filtrer les données sur le Matériel Informatique
  const { data: dashboard, isLoading } = useDashboardStats(apiFilter);

  // Extraction depuis le payload dashboard
  // Les sections vehicules sont réutilisées car les données IT sont filtrées par categorie_id
  const liveGlobal     = dashboard?.patrimoine?.vue_globale;
  const liveTypes      = dashboard?.vehicules?.repartition_par_type;
  const liveAnciennete = dashboard?.vehicules?.anciennete;
  const liveCroisement = dashboard?.vehicules?.croisement_etat_departement;
  const liveVehiculesIT = dashboard?.vehicules?.vue_globale;

  const loadingGlobal = isLoading;
  const loadingTypes  = isLoading;
  const loadingAncien = isLoading;
  const loadingCrois  = isLoading;

  // ── Détection données réelles disponibles ─────────────────────────────
  const hasLiveData = (liveGlobal?.total_biens ?? 0) > 0;

  // ── KPIs ────────────────────────────────────────────────────────────────
  const totalIT = hasLiveData ? liveGlobal!.total_biens : 0;

  // "—" remplacé par 0 FCFA (2026-08-28) — même correction que sur les autres
  // vues KPI : le skeleton de chargement couvre déjà le vrai état de
  // chargement, donc un tiret ici ne représenterait qu'un montant nul
  // légitime (aucun bien IT chargé pour ce filtre), pas une erreur.
  const valeurIT =
    hasLiveData && liveGlobal!.valeur_totale_patrimoine > 0
      ? formatMilliards(liveGlobal!.valeur_totale_patrimoine)
      : formatMilliards(0);

  const aRenouveler =
    hasLiveData && liveGlobal!.biens_mauvais_etat != null
      ? liveGlobal!.biens_mauvais_etat.nombre_biens
      : 0;

  const disponibilite = React.useMemo(() => {
    if (!liveGlobal) return "—";
    const actifs = liveGlobal.total_biens_actifs ?? 0;
    const total = liveGlobal.total_biens;
    if (total === 0) return "—";
    return `${((actifs / total) * 100).toFixed(1)}%`;
  }, [liveGlobal, hasLiveData]);

  // ── Types de matériels ─────────────────────────────────────────────────
  const parTypeData = React.useMemo(() => {
    if (!liveTypes || liveTypes.length === 0 || !hasLiveData) return [];
    return liveTypes.map((t) => ({
      type: t.type_nom,
      code: t.type_id?.toString().padStart(2, "0") ?? "—",
      count: t.nombre,
      famille: "—",
    }));
  }, [liveTypes, hasLiveData]);

  // ── État du matériel — depuis dashboard.vehicules.vue_globale filtré IT ─
  // liveVehiculesIT est déjà extrait dans le bloc d'appel API ci-dessus

  const parEtatData = React.useMemo(() => {
    // Essayer d'abord la répartition spécifique depuis vehicules/vue-globale filtré IT
    const raw = liveVehiculesIT?.repartition_par_etat;
    if (raw && raw.length > 0 && raw.some((e: any) => e.nombre > 0)) {
      return raw.map((e: any) => ({
        etat: e.etat,
        count: e.nombre,
        pct: `${e.pourcentage.toFixed(1)}%`,
        color: getEtatColor(e.etat),
      }));
    }
    // Sinon construire depuis vue-globale si données disponibles
    if (hasLiveData && liveGlobal) {
      const actifs = liveGlobal.total_biens_actifs ?? 0;
      const maintenance = liveGlobal.total_biens_maintenance ?? 0;
      const sortis = liveGlobal.total_biens_sortis ?? 0;
      const total = liveGlobal.total_biens;
      if (total > 0) {
        return [
          { etat: t("stats.informatique.etatActif"), count: actifs,      pct: `${((actifs/total)*100).toFixed(1)}%`,      color: MINEPIA_GREEN },
          { etat: t("stats.informatique.etatMaintenance"),       count: maintenance, pct: `${((maintenance/total)*100).toFixed(1)}%`, color: STAT_ORANGE },
          { etat: t("stats.informatique.etatSorti"),  count: sortis,      pct: `${((sortis/total)*100).toFixed(1)}%`,      color: STAT_RED },
        ].filter(e => e.count >= 0);
      }
    }
    // Fallback vide si aucune donnée réelle
    return [];
  }, [liveVehiculesIT, liveGlobal, hasLiveData, t]);

  // ── Pyramide des âges ─────────────────────────────────────────────────
  const tranchesAgeData = React.useMemo(() => {
    const raw = liveAnciennete?.repartition_par_tranche;
    if (!raw || raw.length === 0 || !hasLiveData) return [];
    const TRANCHE_COLORS: Record<string, string> = {
      "0-2 ans": MINEPIA_GREEN,
      "3-5 ans": STAT_TEAL,
      "6-10 ans": STAT_AMBER,
      "plus de 10 ans": STAT_RED,
    };
    return raw.map((tr) => ({
      annee: tr.tranche,
      count: tr.nombre,
      color: TRANCHE_COLORS[tr.tranche] ?? STAT_ORANGE,
    }));
  }, [liveAnciennete, hasLiveData]);

  // ── Tableau croisé état × département ─────────────────────────────────
  const crossTableData = React.useMemo(() => {
    if (!liveCroisement || liveCroisement.length === 0 || !hasLiveData) return [];
    return liveCroisement.map((row) => {
      const m: Record<string, number> = {};
      row.etats?.forEach((e) => { m[e.etat] = e.nombre; });
      const total = row.etats?.reduce((s, e) => s + e.nombre, 0) ?? 0;
      return {
        dep: row.departement_nom,
        neuf: m["Neuf"] ?? 0,
        bon: m["Bon"] ?? m["Bon état"] ?? 0,
        passable: m["Passable"] ?? 0,
        enPanne: m["En panne"] ?? 0,
        vetuste: m["Vétuste"] ?? 0,
        total,
      };
    });
  }, [liveCroisement, hasLiveData]);

  const activeDepartement = filters?.departement ?? "all";
  const filteredCrossData =
    activeDepartement !== "all"
      ? crossTableData.filter((d) =>
          d.dep.toLowerCase().includes(activeDepartement.toLowerCase()),
        )
      : crossTableData;

  const crossColumns: CrossTableColumn[] = [
    { key: "dep", label: t("stats.informatique.colDepartement"), align: "left" },
    { key: "neuf", label: t("stats.informatique.colNeuf"), align: "right" },
    { key: "bon", label: t("stats.informatique.colBonEtat"), align: "right" },
    { key: "passable", label: t("stats.informatique.colPassable"), align: "right" },
    { key: "enPanne", label: t("stats.informatique.colEnPanne"), align: "right" },
    { key: "vetuste", label: t("stats.informatique.colVetuste"), align: "right" },
    { key: "total", label: t("stats.informatique.colTotalMateriel"), align: "right", isTotal: true },
  ];

  // legendColumns : référentiel de nomenclature statique, jamais rendu (dead code) — non traduit.
  const legendColumns: CrossTableColumn[] = [
    { key: "code", label: "Code", align: "center" },
    { key: "libelle", label: "Désignation complète du matériel", align: "left" },
    { key: "categorie", label: "Famille / Domaine", align: "left" },
    { key: "dureeVie", label: "Durée d'amortissement officielle", align: "right" },
  ];

  return (
    <div className="space-y-5">
      {/* ── KPIs ──────────────────────────────────────────────────────────── */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-400">
              <Monitor className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">
                {t("stats.informatique.totalLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal
                  ? <span className="inline-block h-5 w-12 animate-pulse rounded bg-muted" />
                  : formatNumber(totalIT)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">
            {t("stats.informatique.totalHint")}
          </p>
        </div>

        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400">
              <Wallet className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">
                {t("stats.informatique.valeurLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal
                  ? <span className="inline-block h-5 w-16 animate-pulse rounded bg-muted" />
                  : valeurIT}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">
            {t("stats.informatique.valeurHint")}
          </p>
        </div>

        <div className="rounded-xl border border-red-200 bg-red-50/50 dark:border-red-900/40 dark:bg-red-950/20 p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300">
              <Clock className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-red-700 dark:text-red-400 truncate">
                {t("stats.informatique.renouvellementLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-red-800 dark:text-red-300">
                {loadingGlobal
                  ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" />
                  : formatNumber(aRenouveler)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-red-700/80 dark:text-red-400/80 font-medium">
            {t("stats.informatique.renouvellementHint")}
          </p>
        </div>

        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
              <Sparkles className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">
                {t("stats.informatique.disponibiliteLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal
                  ? <span className="inline-block h-5 w-12 animate-pulse rounded bg-muted" />
                  : disponibilite}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">
            {t("stats.informatique.disponibiliteHint")}
          </p>
        </div>
      </div>

      {/* ── Typologie & État ──────────────────────────────────────────────── */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* Typologie des équipements */}
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="border-b border-border pb-3 mb-3 flex items-center justify-between gap-2">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">
                {t("stats.informatique.typologieTitle")}
              </p>
              <p className="text-[11px] text-muted-foreground">
                {t("stats.informatique.typologieSub")}
              </p>
            </div>
            {loadingTypes && <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-purple-500" />}
          </div>
          <div className="space-y-2">
            {parTypeData.length === 0
              ? <EmptyState message={t("stats.informatique.emptyType")} height="h-24" />
              : parTypeData.map((t) => (
              <div
                key={t.code}
                className="flex items-center justify-between text-xs p-1.5 rounded-lg hover:bg-muted/30 transition-colors"
              >
                <div className="flex items-center gap-2">
                  <span className="font-mono text-[10px] font-extrabold px-1.5 py-0.5 rounded-md bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300">
                    {t.code}
                  </span>
                  <span className="font-medium text-foreground">{t.type}</span>
                </div>
                <span className="font-bold tabular-nums text-foreground">
                  {formatNumber(t.count)}
                </span>
              </div>
            ))}
          </div>
        </div>

        {/* État de fonctionnement */}
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="border-b border-border pb-3 mb-3">
            <p className="text-xs font-bold uppercase tracking-wider text-foreground">
              {t("stats.informatique.etatTitle")}
            </p>
            <p className="text-[11px] text-muted-foreground">
              {t("stats.informatique.etatSub")}
            </p>
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
            {parEtatData.length === 0
              ? <EmptyState message={t("stats.informatique.emptyEtat")} height="h-24" />
              : parEtatData.map((e) => (
              <div
                key={e.etat}
                className="p-2.5 rounded-lg border border-border/70 bg-muted/20"
              >
                <div className="flex items-center gap-1.5">
                  <span className="h-2 w-2 rounded-full" style={{ background: e.color }} />
                  <span className="text-[11px] font-semibold truncate text-foreground">{e.etat}</span>
                </div>
                <p className="text-base font-extrabold mt-1 text-foreground">
                  {formatNumber(e.count)}
                </p>
                <p className="text-[10px] text-muted-foreground">{e.pct}</p>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* ── Ancienneté & Grandes familles ────────────────────────────────── */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* Ancienneté */}
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <div className="flex items-center justify-between gap-2 mb-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">
                {t("stats.informatique.ancienneteTitle")}
              </p>
              <p className="text-[11px] text-muted-foreground">
                {liveAnciennete?.age_moyen_annees != null
                  ? t("stats.informatique.ancienneteSubKnown", { age: liveAnciennete.age_moyen_annees.toFixed(1) })
                  : t("stats.informatique.ancienneteSubDefault")}
              </p>
            </div>
            {loadingAncien && <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-purple-500" />}
          </div>
          <div className="space-y-2.5">
            {tranchesAgeData.length === 0
              ? <EmptyState message={t("stats.informatique.emptyAnciennete")} height="h-24" />
              : tranchesAgeData.map((a) => (
              <div key={a.annee} className="text-xs">
                <div className="flex justify-between items-center mb-0.5">
                  <span className="font-medium text-foreground">{a.annee}</span>
                  <span className="font-bold tabular-nums text-foreground">{formatNumber(a.count)}</span>
                </div>
                <div className="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                  <div
                    className="h-full rounded-full"
                    style={{
                      width: `${totalIT > 0 ? (a.count / totalIT) * 100 : 0}%`,
                      background: a.color,
                    }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Grandes familles */}
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <p className="text-xs font-bold uppercase tracking-wider text-foreground mb-1">
            {t("stats.informatique.famillesTitle")}
          </p>
          <p className="text-[11px] text-muted-foreground mb-3">
            {t("stats.informatique.famillesSub")}
          </p>
          <div className="space-y-3">
            <EmptyState message={t("stats.informatique.emptyFamilles")} height="h-24" />
          </div>
        </div>
      </div>

      {/* ── Table codes — référentiel statique, données normatives conservées ─ */}
      {/* codesLegende est un référentiel de nomenclature officielle, pas des données de production */}

      {/* ── Tableau croisé état × département ──────────────────────────── */}
      <div className="relative">
        {loadingCrois && (
          <div className="absolute right-4 top-4 h-1.5 w-1.5 animate-pulse rounded-full bg-purple-500 z-10" />
        )}
        <CrossTableWidget
          title={t("stats.informatique.crossTitle")}
          subTitle={t("stats.informatique.crossSub")}
          columns={crossColumns}
          data={filteredCrossData}
          rowKey="dep"
        />
      </div>
    </div>
  );
}
