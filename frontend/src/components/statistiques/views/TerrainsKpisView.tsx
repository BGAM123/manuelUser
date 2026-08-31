import React from "react";
import {
  Mountain,
  MapPin,
  Wallet,
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

interface TerrainsKpisViewProps {
  filters?: FilterState;
}

export function TerrainsKpisView({ filters }: TerrainsKpisViewProps) {
  const t = useT();
  const apiFilter = React.useMemo(
    () => (filters ? mapFilterStateToApiFilter(filters) : {}),
    [filters],
  );

  // ── Appel API centralisé ──────────────────────────────────────────────
  const { data: dashboard, isLoading } = useDashboardStats(apiFilter);

  // Extraction depuis dashboard.terrains.*
  const liveTerrains        = dashboard?.terrains?.vue_globale;
  const liveLitiges         = dashboard?.terrains?.en_litige;
  const liveAcquisitions    = dashboard?.terrains?.acquisitions_par_annee;
  const liveCroisementBati  = dashboard?.terrains?.croisement_bati_departement;
  const liveCroisementOccup = dashboard?.terrains?.croisement_occupation_departement;

  const loadingGlobal  = isLoading;
  const loadingLitiges = isLoading;
  const loadingAcq     = isLoading;
  const loadingBati    = isLoading;

  // ── KPIs ────────────────────────────────────────────────────────────────
  const totalTerrains = liveTerrains?.total_terrains ?? 0;

  // "—" remplacé par 0 FCFA (2026-08-28) — voir même correction sur
  // VehiculesKpisView.tsx : le skeleton de chargement couvre déjà le vrai
  // état de chargement, donc un tiret ici ne représenterait qu'un montant
  // nul légitime, pas une erreur.
  const valeurTerrains =
    liveTerrains?.valeur?.valeur_totale != null
      ? formatMilliards(liveTerrains.valeur.valeur_totale)
      : formatMilliards(0);

  const sansValeurCount =
    liveTerrains?.valeur?.terrains_sans_valeur ?? 0;

  const litigeCount = liveLitiges?.nombre ?? 0;

  const avecTitreFoncier =
    liveTerrains?.titre_foncier?.nombre_avec_titre ?? 0;

  const sansTitreFoncier =
    liveTerrains?.titre_foncier?.nombre_sans_titre ?? 0;

  const terrainsLoues =
    liveTerrains?.terrains_loues?.nombre ?? 0;

  // ── Bâti / Non bâti — depuis repartition_bati ─────────────────────────
  const batiData = React.useMemo(() => {
    const raw = liveTerrains?.repartition_bati;
    if (!raw || raw.length === 0) return null;
    const bati = raw.find((r) => r.statut === "Bâti")?.nombre ?? 0;
    const nonBati = raw.find((r) => r.statut === "Non bâti")?.nombre ?? 0;
    const sansInfo =
      raw.find((r) => r.statut === "Aucune information")?.nombre ?? 0;
    const total = bati + nonBati + sansInfo || 1;
    return {
      bati,
      nonBati,
      sansInfo,
      pctBati: ((bati / total) * 100).toFixed(1),
      pctNonBati: ((nonBati / total) * 100).toFixed(1),
      pctSansInfo: ((sansInfo / total) * 100).toFixed(1),
    };
  }, [liveTerrains]);

  // ── Sécurisation foncière ──────────────────────────────────────────────
  const securisationData = React.useMemo(() => {
    const svc = liveTerrains?.securisation;
    if (!svc) return [];
    return [
      { label: t("stats.terrains.secJuridiquePhysique"), count: svc.les_deux ?? 0, color: MINEPIA_GREEN, pct: totalTerrains > 0 ? `${(((svc.les_deux ?? 0) / totalTerrains) * 100).toFixed(1)}%` : "—" },
      { label: t("stats.terrains.secJuridiqueSeulement"), count: svc.juridique_seulement ?? 0, color: STAT_BLUE, pct: totalTerrains > 0 ? `${(((svc.juridique_seulement ?? 0) / totalTerrains) * 100).toFixed(1)}%` : "—" },
      { label: t("stats.terrains.secPhysiqueSeulement"), count: svc.physique_seulement ?? 0, color: STAT_TEAL, pct: totalTerrains > 0 ? `${(((svc.physique_seulement ?? 0) / totalTerrains) * 100).toFixed(1)}%` : "—" },
      { label: t("stats.terrains.secAucune"), count: svc.aucun ?? 0, color: STAT_RED, pct: totalTerrains > 0 ? `${(((svc.aucun ?? 0) / totalTerrains) * 100).toFixed(1)}%` : "—" },
    ];
  }, [liveTerrains, totalTerrains, t]);

  // ── Occupation foncière ────────────────────────────────────────────────
  const occupationData = React.useMemo(() => {
    const raw = liveTerrains?.occupation;
    if (!raw || raw.length === 0) return [];
    const OCCUP_COLORS: Record<string, string> = {
      Régulière: MINEPIA_GREEN,
      Irrégulière: STAT_RED,
      "Aucune information": "#94a3b8",
    };
    return raw.map((o) => ({
      label: o.occupation,
      count: o.nombre,
      pct: `${o.pourcentage.toFixed(1)}%`,
      color: OCCUP_COLORS[o.occupation] ?? STAT_ORANGE,
    }));
  }, [liveTerrains]);

  // ── Acquisitions par année — graphique barres ──────────────────────────
  const acquisitionsData = React.useMemo(() => {
    if (!liveAcquisitions || liveAcquisitions.length === 0) return [];
    return liveAcquisitions.map((a) => ({ annee: a.annee, count: a.nombre }));
  }, [liveAcquisitions]);

  // ── Liste des litiges ──────────────────────────────────────────────────
  const litigesListe = React.useMemo(() => {
    if (!liveLitiges?.terrains || liveLitiges.terrains.length === 0) return [];
    return liveLitiges.terrains.map((item) => ({
      site: item.nom,
      region: "—",
      dep: "—",
      superficie: "—",
      motif: t("stats.terrains.motifEnLitige"),
      statut: t("stats.terrains.statutEnCours"),
    }));
  }, [liveLitiges, t]);

  const activeRegion = filters?.region ?? "all";
  const activeDepartement = filters?.departement ?? "all";

  const filteredLitiges =
    activeRegion !== "all"
      ? litigesListe.filter((l) => l.region.toLowerCase() === activeRegion.toLowerCase())
      : litigesListe;

  // ── Tableau croisé bâti + occupation par département ──────────────────
  const crossTableData = React.useMemo(() => {
    if (!liveCroisementBati || liveCroisementBati.length === 0) return [];

    // Fusionner bâti et occupation par département
    const occupMap: Record<string, { reg: number; irreg: number }> = {};
    liveCroisementOccup?.forEach((o) => {
      occupMap[o.departement_id] = {
        reg: o.reguliere ?? 0,
        irreg: o.irreguliere ?? 0,
      };
    });

    return liveCroisementBati.map((row) => {
      const occ = occupMap[row.departement_id] ?? { reg: 0, irreg: 0 };
      const total =
        (row.bati ?? 0) + (row.non_bati ?? 0) + (row.aucune_information ?? 0);
      return {
        dep: row.departement_nom,
        bati: row.bati ?? 0,
        nonBati: row.non_bati ?? 0,
        sansInfo: row.aucune_information ?? 0,
        reg: occ.reg,
        irreg: occ.irreg,
        tf: 0, // non disponible dans ce croisement
        total,
      };
    });
  }, [liveCroisementBati, liveCroisementOccup]);

  const filteredCrossData =
    activeDepartement !== "all"
      ? crossTableData.filter((d) =>
          d.dep.toLowerCase().includes(activeDepartement.toLowerCase()),
        )
      : crossTableData;

  const crossColumns: CrossTableColumn[] = [
    { key: "dep", label: t("stats.terrains.colDepartement"), align: "left" },
    { key: "bati", label: t("stats.terrains.colBati"), align: "right" },
    { key: "nonBati", label: t("stats.terrains.colNonBati"), align: "right" },
    { key: "sansInfo", label: t("stats.terrains.colSansInfo"), align: "right" },
    { key: "reg", label: t("stats.terrains.colOccReg"), align: "right" },
    { key: "irreg", label: t("stats.terrains.colOccIrreg"), align: "right" },
    { key: "tf", label: t("stats.terrains.colTF"), align: "right" },
    { key: "total", label: t("stats.terrains.colTotalTerrains"), align: "right", isTotal: true },
  ];

  return (
    <div className="space-y-5">
      {/* ── KPIs clés ──────────────────────────────────────────────────────── */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400">
              <Mountain className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">
                {t("stats.terrains.totalLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? <span className="inline-block h-5 w-12 animate-pulse rounded bg-muted" /> : formatNumber(totalTerrains)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">{t("stats.terrains.totalHint")}</p>
        </div>

        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
              <MapPin className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">
                {t("stats.terrains.superficieLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                —
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">{t("stats.terrains.superficieHint")}</p>
        </div>

        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-400">
              <Wallet className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">
                {t("stats.terrains.valeurLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? <span className="inline-block h-5 w-16 animate-pulse rounded bg-muted" /> : valeurTerrains}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">
            {t("stats.terrains.sansValeurCount", { count: formatNumber(sansValeurCount) })}
          </p>
        </div>

        <div className="rounded-xl border border-red-200 bg-red-50/50 dark:border-red-900/40 dark:bg-red-950/20 p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300">
              <Scale className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-red-700 dark:text-red-400 truncate">
                {t("stats.terrains.litigeLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-red-800 dark:text-red-300">
                {loadingLitiges ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" /> : formatNumber(litigeCount)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-red-700/80 dark:text-red-400/80 font-medium">{t("stats.terrains.litigeHint")}</p>
        </div>

        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-700 dark:bg-teal-950/50 dark:text-teal-400">
              <FileCheck2 className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">
                {t("stats.terrains.titresFonciersLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" /> : formatNumber(avecTitreFoncier)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">
            {t("stats.terrains.sansTitreCount", { count: formatNumber(sansTitreFoncier) })}
          </p>
        </div>

        <div className="rounded-xl border border-border bg-card p-3.5 shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">
              <DollarSign className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-[9px] font-bold uppercase tracking-wider text-muted-foreground truncate">
                {t("stats.terrains.terrainsLouesLabel")}
              </p>
              <p className="mt-0.5 text-lg font-extrabold text-foreground">
                {loadingGlobal ? <span className="inline-block h-5 w-10 animate-pulse rounded bg-muted" /> : formatNumber(terrainsLoues)}
              </p>
            </div>
          </div>
          <p className="mt-2 text-[10px] text-muted-foreground font-medium">
            <span className="text-[10px] text-muted-foreground font-medium">{t("stats.terrains.donneesNonDisponibles")}</span>
          </p>
        </div>
      </div>

      {/* ── Sécurisation & Occupation & Bâti/Non-Bâti ────────────────────── */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {/* Sécurisation foncière */}
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <p className="text-xs font-bold uppercase tracking-wider text-foreground mb-1">{t("stats.terrains.securisationTitle")}</p>
          <p className="text-[11px] text-muted-foreground mb-3">{t("stats.terrains.securisationSub")}</p>
          <div className="space-y-2.5">
            {securisationData.length === 0
              ? <EmptyState message={t("stats.terrains.emptySecurisation")} />
              : securisationData.map((s) => (
              <div key={s.label} className="text-xs">
                <div className="flex justify-between items-center mb-0.5">
                  <span className="font-medium text-foreground">{s.label}</span>
                  <span className="font-bold tabular-nums text-foreground">
                    {formatNumber(s.count)}{" "}
                    <span className="text-muted-foreground font-normal">({s.pct})</span>
                  </span>
                </div>
                <div className="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                  <div
                    className="h-full rounded-full"
                    style={{ width: `${totalTerrains > 0 ? (s.count / totalTerrains) * 100 : 0}%`, background: s.color }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Occupation */}
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <p className="text-xs font-bold uppercase tracking-wider text-foreground mb-1">{t("stats.terrains.occupationTitle")}</p>
          <p className="text-[11px] text-muted-foreground mb-3">{t("stats.terrains.occupationSub")}</p>
          <div className="space-y-2.5">
            {occupationData.length === 0
              ? <EmptyState message={t("stats.terrains.emptyOccupation")} />
              : occupationData.map((o) => (
              <div key={o.label} className="text-xs">
                <div className="flex justify-between items-center mb-0.5">
                  <span className="font-medium text-foreground">{o.label}</span>
                  <span className="font-bold tabular-nums text-foreground">
                    {formatNumber(o.count)}{" "}
                    <span className="text-muted-foreground font-normal">({o.pct})</span>
                  </span>
                </div>
                <div className="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                  <div
                    className="h-full rounded-full"
                    style={{ width: `${totalTerrains > 0 ? (o.count / totalTerrains) * 100 : 0}%`, background: o.color }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Bâti vs Non bâti */}
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
          <p className="text-xs font-bold uppercase tracking-wider text-foreground mb-1">{t("stats.terrains.batiTitle")}</p>
          <p className="text-[11px] text-muted-foreground mb-3">{t("stats.terrains.batiSub")}</p>
          <div className="space-y-3">
            {batiData ? (
              <>
                <div className="p-3 rounded-lg border border-emerald-100 bg-emerald-50/50 dark:border-emerald-950 dark:bg-emerald-950/20">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-bold text-emerald-800 dark:text-emerald-300">{t("stats.terrains.terrainsBatis")}</span>
                    <span className="text-sm font-extrabold text-emerald-800 dark:text-emerald-300">
                      {formatNumber(batiData.bati)} ({batiData.pctBati}%)
                    </span>
                  </div>
                  <p className="text-[10px] text-muted-foreground mt-0.5">{t("stats.terrains.terrainsBatisSub")}</p>
                </div>
                <div className="p-3 rounded-lg border border-blue-100 bg-blue-50/50 dark:border-blue-950 dark:bg-blue-950/20">
                  <div className="flex justify-between items-center">
                    <span className="text-xs font-bold text-blue-800 dark:text-blue-300">{t("stats.terrains.terrainsNonBatis")}</span>
                    <span className="text-sm font-extrabold text-blue-800 dark:text-blue-300">
                      {formatNumber(batiData.nonBati)} ({batiData.pctNonBati}%)
                    </span>
                  </div>
                  <p className="text-[10px] text-muted-foreground mt-0.5">{t("stats.terrains.terrainsNonBatisSub")}</p>
                </div>
                <div className="p-2.5 rounded-lg border border-border bg-muted/20">
                  <div className="flex justify-between items-center text-xs">
                    <span className="text-muted-foreground">{t("stats.terrains.sansInformation")}</span>
                    <span className="font-bold text-foreground">
                      {formatNumber(batiData.sansInfo)} ({batiData.pctSansInfo}%)
                    </span>
                  </div>
                </div>
              </>
            ) : (
              <EmptyState message={t("stats.terrains.emptyBati")} />
            )}
          </div>
        </div>
      </div>

      {/* ── Acquisitions par année ─────────────────────────────────────────── */}
      <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
        <div className="flex items-center justify-between border-b border-border pb-3 mb-3">
          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-foreground">
              {t("stats.terrains.acquisitionsTitle")}
            </p>
            <p className="text-[11px] text-muted-foreground">
              {t("stats.terrains.acquisitionsSub")}
            </p>
          </div>
          {loadingAcq && <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />}
        </div>
        <div className="h-48 w-full">
          {acquisitionsData.length === 0
            ? <EmptyState message={t("stats.terrains.emptyAcquisitions")} height="h-48" />
            : <ResponsiveContainer width="100%" height="100%">
                <BarChart data={acquisitionsData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" opacity={0.6} />
                  <XAxis dataKey="annee" tick={{ fontSize: 11, fill: "var(--muted-foreground)" }} />
                  <YAxis tick={{ fontSize: 11, fill: "var(--muted-foreground)" }} />
                  <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8, backgroundColor: "var(--card)", borderColor: "var(--border)" }} />
                  <Bar dataKey="count" fill={MINEPIA_GREEN} radius={[4, 4, 0, 0]} name={t("stats.terrains.terrainsAcquisName")} />
                </BarChart>
              </ResponsiveContainer>}
        </div>
      </div>

      {/* ── Litiges ────────────────────────────────────────────────────────── */}
      <div className="rounded-xl border border-red-200/80 bg-card p-4 shadow-xs">
        <div className="flex items-center gap-2 border-b border-border pb-3 mb-3 text-red-600">
          <Scale className="h-4 w-4" />
          <p className="text-xs font-bold uppercase tracking-wider">
            {t("stats.terrains.litigesTitle")}
          </p>
          {loadingLitiges && <span className="ml-auto h-1.5 w-1.5 animate-pulse rounded-full bg-red-500" />}
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          {filteredLitiges.length === 0
            ? <div className="col-span-4"><EmptyState message={t("stats.terrains.emptyLitiges")} /></div>
            : filteredLitiges.slice(0, 8).map((l, i) => (
            <div
              key={`${l.site}-${i}`}
              className="p-3 rounded-xl border border-red-100 bg-red-50/40 dark:border-red-900/30 dark:bg-red-950/20 flex flex-col justify-between"
            >
              <div>
                <p className="text-xs font-bold text-foreground">{l.site}</p>
                <p className="text-[11px] text-muted-foreground mt-0.5">
                  📍 {l.region !== "—" ? `${l.region} — ${l.dep}` : t("stats.terrains.localisationNonRenseignee")}
                  {l.superficie !== "—" ? ` (${l.superficie})` : ""}
                </p>
                <p className="text-xs text-red-700 dark:text-red-400 font-medium mt-2">⚠️ {l.motif}</p>
              </div>
              <div className="mt-3 pt-2 border-t border-red-100 dark:border-red-900/30 text-[10px] font-bold text-muted-foreground">
                {t("stats.terrains.statutLabel")} <span className="text-foreground">{l.statut}</span>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* ── Tableau croisé ────────────────────────────────────────────────── */}
      <div className="relative">
        {loadingBati && (
          <div className="absolute right-4 top-4 h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500 z-10" />
        )}
        <CrossTableWidget
          title={t("stats.terrains.crossTitle")}
          subTitle={t("stats.terrains.crossSub")}
          columns={crossColumns}
          data={filteredCrossData}
          rowKey="dep"
        />
      </div>
    </div>
  );
}
