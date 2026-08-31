/**
 * StatDetailModal — Modales de détail du dashboard statistiques.
 * Connectées aux vraies APIs backend. Plus aucune donnée mock.
 */
import React, { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import {
  Search,
  Download,
  ExternalLink,
  Truck,
  Mountain,
  Home,
  Monitor,
  Armchair,
  MapPin,
  Building2,
  FolderKanban,
  AlertTriangle,
  FileQuestion,
  TrendingUp,
  ShieldAlert,
  Loader2,
} from "lucide-react";
import {
  MINEPIA_GREEN,
  STAT_BLUE,
  STAT_ORANGE,
  STAT_PURPLE,
  STAT_RED,
  STAT_TEAL,
} from "../mock-stats-data";
import { exportStatistiquesExcel } from "../exportUtils";
import { exportStatistiques } from "@/api/statistiques";
import type { StatTab, FilterState } from "../types";
import { mapFilterStateToApiFilter } from "../filterMapper";
import {
  useRepartitionCategorie,
  useRepartitionRegions,
  useRepartitionStructures,
  useRepartitionProjets,
  useVueGlobale,
  useEvolutionGap,
  useClassementRegions,
  useDashboardStats,
} from "@/hooks/useStatistiques";

export type StatModalType =
  | "categories"
  | "regions"
  | "services"
  | "top_structures"
  | "top_projets"
  | "top_valeur"
  | "mauvais_etat"
  | "sans_info"
  | "gap_evolution"
  | "classement_regions"
  | "reforme_regions"
  | null;

interface StatDetailModalProps {
  modalType: StatModalType;
  filters?: FilterState;
  onClose: () => void;
  onNavigateTab?: (tab: StatTab) => void;
}

const formatNumber = (v: number) => v.toLocaleString("fr-FR");
const formatVal = (v: number) => {
  if (v === 0) return "0 FCFA";
  if (v < 1_000_000) return `${v.toLocaleString("fr-FR")} FCFA`;
  if (v < 1_000_000_000) return `${(v / 1_000_000).toFixed(2).replace(".", ",")} M FCFA`;
  return `${(v / 1_000_000_000).toFixed(2).replace(".", ",")} Mds FCFA`;
};

// ── Composant de chargement ────────────────────────────────────────────────
function LoadingRow({ cols }: { cols: number }) {
  return (
    <tr>
      <td colSpan={cols} className="p-8 text-center">
        <div className="flex items-center justify-center gap-2 text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" />
          <span className="text-xs">Chargement des données…</span>
        </div>
      </td>
    </tr>
  );
}

// ── Composant de tableau vide ──────────────────────────────────────────────
function EmptyRow({ cols, message }: { cols: number; message?: string }) {
  return (
    <tr>
      <td colSpan={cols} className="p-8 text-center text-xs text-muted-foreground">
        {message ?? "Aucune donnée disponible"}
      </td>
    </tr>
  );
}

// ── Couleur catégorie ──────────────────────────────────────────────────────
function getCatColor(nom: string): string {
  const l = nom.toLowerCase();
  if (l.includes("roulant") || l.includes("vehic")) return MINEPIA_GREEN;
  if (l.includes("terrain") || l.includes("fonc")) return STAT_BLUE;
  if (l.includes("batim") || l.includes("bâtim")) return STAT_ORANGE;
  if (l.includes("inform")) return STAT_PURPLE;
  if (l.includes("mobil")) return STAT_TEAL;
  return STAT_BLUE;
}
function getCatIcon(nom: string) {
  const l = nom.toLowerCase();
  if (l.includes("roulant") || l.includes("vehic")) return Truck;
  if (l.includes("terrain")) return Mountain;
  if (l.includes("batim") || l.includes("bâtim")) return Home;
  if (l.includes("inform")) return Monitor;
  return Armchair;
}
function getCatTab(nom: string): StatTab | null {
  const l = nom.toLowerCase();
  if (l.includes("roulant") || l.includes("vehic")) return "vehicules";
  if (l.includes("terrain")) return "terrains";
  if (l.includes("batim") || l.includes("bâtim")) return "batiments";
  if (l.includes("inform")) return "informatique";
  return null;
}

// ── Composant principal ────────────────────────────────────────────────────
export function StatDetailModal({
  modalType,
  filters,
  onClose,
  onNavigateTab,
}: StatDetailModalProps) {
  const [searchTerm, setSearchTerm] = useState("");

  // ── Un seul appel dashboard remplace les appels individuels ──────────
  // Filtre repris de la page (2026-08-28) : la modale appelait auparavant
  // useDashboardStats({}) sans filtre, donc affichait toujours les chiffres
  // globaux non filtrés même quand des filtres étaient actifs sur la page —
  // la carte et la modale pouvaient donc afficher des nombres différents.
  const apiFilter = React.useMemo(
    () => (filters ? mapFilterStateToApiFilter(filters) : {}),
    [filters],
  );
  const { data: dashboard, isLoading: loadingDash } = useDashboardStats(apiFilter);

  // Extraction depuis le payload unique
  const cats      = dashboard?.patrimoine?.repartition_par_categorie ?? [];
  const regions   = dashboard?.patrimoine?.repartition_par_region ?? [];
  const top10     = dashboard?.patrimoine?.repartition_par_structure ?? [];
  const top6v     = dashboard?.patrimoine?.top_services_valeur ?? [];
  const top5      = dashboard?.patrimoine?.repartition_par_projet ?? [];
  const global    = dashboard?.patrimoine?.vue_globale;
  const gap       = dashboard?.patrimoine?.evolution_gap ?? [];
  // Pour classement_regions, utiliser patrimoine.classement_regions si disponible,
  // sinon repartition_par_region comme approximation
  const classement = (dashboard?.patrimoine?.classement_regions ?? []).length > 0
    ? dashboard?.patrimoine?.classement_regions ?? []
    : null;

  const loadingCats      = loadingDash;
  const loadingRegions   = loadingDash;
  const loadingTop10     = loadingDash;
  const loadingTop6v     = loadingDash;
  const loadingTop5      = loadingDash;
  const loadingGlobal    = loadingDash;
  const loadingGap       = loadingDash;
  const loadingClassement = loadingDash;

  const totalBiens  = global?.total_biens ?? 0;
  const totalValeur = global?.valeur_totale_patrimoine ?? 0;

  if (!modalType) return null;

  // ── Export ──────────────────────────────────────────────────────────────
  const handleExport = async () => {
    const endpointMap: Record<string, string> = {
      categories: "/api/stats/repartition-par-categorie",
      regions: "/api/stats/repartition-par-region",
      classement_regions: "/api/stats/classement-regions",
      services: "/api/stats/vue-globale",
      top_structures: "/api/stats/repartition-par-structure?critere=nombre",
      top_projets: "/api/stats/repartition-par-projet",
      top_valeur: "/api/stats/repartition-par-structure?critere=valeur",
      mauvais_etat: "/api/stats/vue-globale",
      sans_info: "/api/stats/vue-globale",
      gap_evolution: "/api/stats/evolution-gap",
      reforme_regions: "/api/stats/vehicules/vue-globale",
    };
    const filename = `Detail_${modalType}_MINEPIA_${new Date().toISOString().slice(0, 10)}.xlsx`;
    if (endpointMap[modalType]) {
      try {
        await exportStatistiques(endpointMap[modalType], {}, "xlsx", filename);
        return;
      } catch { /* fallback */ }
    }
    // Fallback local avec données réelles si disponibles
    let data: Record<string, unknown>[] = [];
    if (modalType === "categories" && cats) {
      data = cats.map((c) => ({ Catégorie: c.categorie_nom, Nombre: c.nombre_biens, Valeur: c.valeur_patrimoine }));
    } else if ((modalType === "regions" || modalType === "classement_regions") && regions) {
      data = regions.map((r, i) => ({ Rang: i + 1, Région: r.region_nom, Biens: r.nombre_biens, Valeur: r.valeur_patrimoine }));
    } else if (modalType === "top_structures" && top10) {
      data = (top10 as any[]).map((s, i) => ({ Rang: i + 1, Structure: s.service_nom, Biens: s.nombre_biens }));
    } else if (modalType === "top_projets" && top5) {
      data = top5.map((p, i) => ({ Rang: i + 1, Projet: p.projet_nom, Biens: p.nombre_biens }));
    }
    exportStatistiquesExcel(`Détail ${modalType}`, data, filename);
  };

  // ── Titre de la modale ──────────────────────────────────────────────────
  const titles: Record<NonNullable<StatModalType>, string> = {
    categories: "Détail de la répartition par catégorie de biens",
    regions: "Répartition géographique complète",
    services: "Répartition : Services Centraux vs Services Déconcentrés",
    top_structures: "Classement des structures détentrices de biens",
    top_projets: "Répertoire des projets donateurs et bailleurs",
    top_valeur: "Classement des structures par valeur patrimoniale",
    mauvais_etat: "Biens en mauvais état — Alerte",
    sans_info: "Biens avec informations manquantes — Contrôle qualité",
    gap_evolution: "Évolution pluriannuelle et analyse du GAP",
    classement_regions: "Classement exhaustif des régions",
    reforme_regions: "Répartition des biens à réformer par région",
  };

  const iconColors: Partial<Record<NonNullable<StatModalType>, string>> = {
    mauvais_etat: STAT_RED,
    reforme_regions: STAT_RED,
    sans_info: STAT_ORANGE,
  };

  return (
    <Dialog open={Boolean(modalType)} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-4xl max-h-[85vh] flex flex-col p-0 overflow-hidden rounded-2xl border border-border shadow-2xl">
        {/* En-tête */}
        <DialogHeader className="px-6 pt-5 pb-4 border-b border-border bg-muted/20">
          <div className="flex items-center justify-between gap-4 pr-6">
            <div className="flex items-center gap-3">
              <div
                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white shadow-sm"
                style={{ backgroundColor: iconColors[modalType] ?? MINEPIA_GREEN }}
              >
                {modalType === "categories" && <Truck className="h-5 w-5" />}
                {(modalType === "regions" || modalType === "classement_regions") && <MapPin className="h-5 w-5" />}
                {modalType === "services" && <Building2 className="h-5 w-5" />}
                {modalType === "top_structures" && <Building2 className="h-5 w-5" />}
                {modalType === "top_projets" && <FolderKanban className="h-5 w-5" />}
                {modalType === "top_valeur" && <TrendingUp className="h-5 w-5" />}
                {modalType === "mauvais_etat" && <AlertTriangle className="h-5 w-5" />}
                {modalType === "sans_info" && <FileQuestion className="h-5 w-5" />}
                {modalType === "gap_evolution" && <TrendingUp className="h-5 w-5" />}
                {modalType === "reforme_regions" && <ShieldAlert className="h-5 w-5" />}
              </div>
              <div>
                <DialogTitle className="text-base font-extrabold text-foreground">
                  {titles[modalType]}
                </DialogTitle>
                <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                  Données consolidées du patrimoine MINEPIA — backend en temps réel
                </DialogDescription>
              </div>
            </div>
            <Button size="sm" variant="outline" onClick={handleExport}
              className="h-8 gap-1.5 text-xs font-bold shrink-0 border-emerald-600/30 text-emerald-800 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/30">
              <Download className="h-3.5 w-3.5" />
              Exporter Excel
            </Button>
          </div>
          <div className="relative mt-3">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
            <Input value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)}
              placeholder="Filtrer ou rechercher…" className="h-8 pl-8 text-xs bg-background" />
          </div>
        </DialogHeader>

        {/* Corps */}
        <div className="flex-1 overflow-y-auto p-6 space-y-4">

          {/* ── CATÉGORIES ───────────────────────────────────────────────── */}
          {modalType === "categories" && (
            <div className="rounded-xl border border-border overflow-hidden">
              <table className="w-full text-xs text-left">
                <thead className="bg-muted/40 text-muted-foreground uppercase text-[10px] font-bold border-b border-border">
                  <tr>
                    <th className="p-3">Catégorie</th>
                    <th className="p-3 text-right">Nombre total</th>
                    <th className="p-3 text-right">% du parc</th>
                    <th className="p-3 text-right">Valeur estimée (FCFA)</th>
                    <th className="p-3 text-center">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {loadingCats ? <LoadingRow cols={5} /> :
                   !cats || cats.length === 0 ? <EmptyRow cols={5} message="Aucune catégorie disponible" /> :
                   cats.filter(c => c.categorie_nom.toLowerCase().includes(searchTerm.toLowerCase()))
                     .map((c) => {
                       const Icon = getCatIcon(c.categorie_nom);
                       const color = getCatColor(c.categorie_nom);
                       const tab = getCatTab(c.categorie_nom);
                       const pct = totalBiens > 0 ? `${((c.nombre_biens / totalBiens) * 100).toFixed(1)}%` : "—";
                       return (
                         <tr key={c.categorie_id} className="hover:bg-muted/20 transition-colors">
                           <td className="p-3 font-semibold text-foreground">
                             <div className="flex items-center gap-2">
                               <span className="p-1 rounded-md text-white" style={{ backgroundColor: color }}>
                                 <Icon className="h-3.5 w-3.5" />
                               </span>
                               {c.categorie_nom}
                             </div>
                           </td>
                           <td className="p-3 text-right font-extrabold tabular-nums">{formatNumber(c.nombre_biens)}</td>
                           <td className="p-3 text-right font-bold text-muted-foreground tabular-nums">{pct}</td>
                           <td className="p-3 text-right font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">
                             {c.valeur_patrimoine != null ? formatVal(c.valeur_patrimoine) : "0 FCFA"}
                           </td>
                           <td className="p-3 text-center">
                             {tab && (
                               <Button size="sm" variant="ghost"
                                 className="h-7 px-2 text-[11px] font-bold text-emerald-700 gap-1 hover:underline"
                                 onClick={() => { onClose(); onNavigateTab?.(tab); }}>
                                 Ouvrir <ExternalLink className="h-3 w-3" />
                               </Button>
                             )}
                           </td>
                         </tr>
                       );
                     })}
                </tbody>
              </table>
            </div>
          )}

          {/* ── RÉGIONS / CLASSEMENT ─────────────────────────────────────── */}
          {(modalType === "regions" || modalType === "classement_regions") && (
            <div className="rounded-xl border border-border overflow-hidden">
              <table className="w-full text-xs text-left">
                <thead className="bg-muted/40 text-muted-foreground uppercase text-[10px] font-bold border-b border-border">
                  <tr>
                    <th className="p-3 text-center">Rang</th>
                    <th className="p-3">Région</th>
                    <th className="p-3 text-right">Total Biens</th>
                    <th className="p-3 text-right">Valeur Estimée (FCFA)</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {loadingRegions ? <LoadingRow cols={4} /> :
                   !regions || regions.length === 0
                     ? <EmptyRow cols={4} message="Aucune région disponible — les biens doivent avoir une région renseignée" />
                     : [...regions]
                         .sort((a, b) => b.nombre_biens - a.nombre_biens)
                         .filter(r => r.region_nom.toLowerCase().includes(searchTerm.toLowerCase()))
                         .map((r, i) => (
                           <tr key={r.region_id} className="hover:bg-muted/20 transition-colors">
                             <td className="p-3 text-center font-extrabold text-muted-foreground">#{i + 1}</td>
                             <td className="p-3 font-bold text-foreground">{r.region_nom}</td>
                             <td className="p-3 text-right font-extrabold tabular-nums">{formatNumber(r.nombre_biens)} biens</td>
                             <td className="p-3 text-right font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">
                               {r.valeur_patrimoine != null ? formatVal(r.valeur_patrimoine) : "0 FCFA"}
                             </td>
                           </tr>
                         ))}
                </tbody>
              </table>
            </div>
          )}

          {/* ── SERVICES CENTRAUX VS DÉCONCENTRÉS ───────────────────────── */}
          {modalType === "services" && (
            <div className="space-y-4">
              {loadingGlobal ? (
                <div className="flex items-center justify-center gap-2 p-8 text-muted-foreground text-xs">
                  <Loader2 className="h-4 w-4 animate-spin" /> Chargement…
                </div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="p-4 rounded-xl border border-emerald-200 bg-emerald-50/40 dark:border-emerald-900/30 dark:bg-emerald-950/20">
                    <h4 className="font-extrabold text-emerald-900 dark:text-emerald-300 text-sm">Services Centraux</h4>
                    <p className="text-2xl font-black text-emerald-700 mt-1">
                      {formatNumber(global?.structures_centrales_vs_deconcentrees?.central ?? 0)} biens{" "}
                      <span className="text-xs font-normal text-muted-foreground">
                        ({totalBiens > 0 && global?.structures_centrales_vs_deconcentrees?.central
                          ? `${(((global.structures_centrales_vs_deconcentrees.central) / totalBiens) * 100).toFixed(1)}%`
                          : "—"})
                      </span>
                    </p>
                  </div>
                  <div className="p-4 rounded-xl border border-teal-200 bg-teal-50/40 dark:border-teal-900/30 dark:bg-teal-950/20">
                    <h4 className="font-extrabold text-teal-900 dark:text-teal-300 text-sm">Services Déconcentrés (10 Régions)</h4>
                    <p className="text-2xl font-black text-teal-700 mt-1">
                      {formatNumber(global?.structures_centrales_vs_deconcentrees?.deconcentre ?? 0)} biens{" "}
                      <span className="text-xs font-normal text-muted-foreground">
                        ({totalBiens > 0 && global?.structures_centrales_vs_deconcentrees?.deconcentre
                          ? `${(((global.structures_centrales_vs_deconcentrees.deconcentre) / totalBiens) * 100).toFixed(1)}%`
                          : "—"})
                      </span>
                    </p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* ── TOP 10 STRUCTURES (NOMBRE) ───────────────────────────────── */}
          {modalType === "top_structures" && (
            <div className="rounded-xl border border-border overflow-hidden">
              <table className="w-full text-xs text-left">
                <thead className="bg-muted/40 text-muted-foreground uppercase text-[10px] font-bold border-b border-border">
                  <tr>
                    <th className="p-3 text-center">Rang</th>
                    <th className="p-3">Structure / Service</th>
                    <th className="p-3 text-right">Biens détenus</th>
                    <th className="p-3 text-right">% du total</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {loadingTop10 ? <LoadingRow cols={4} /> :
                   !top10 || top10.length === 0 ? <EmptyRow cols={4} /> :
                   (top10 as any[])
                     .filter(s => s.service_nom.toLowerCase().includes(searchTerm.toLowerCase()))
                     .map((s, i) => (
                       <tr key={s.service_id ?? i} className="hover:bg-muted/20 transition-colors">
                         <td className="p-3 text-center font-extrabold text-muted-foreground">#{i + 1}</td>
                         <td className="p-3 font-bold text-foreground">{s.service_nom}</td>
                         <td className="p-3 text-right font-extrabold tabular-nums">{formatNumber(s.nombre_biens)}</td>
                         <td className="p-3 text-right font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">
                           {totalBiens > 0 ? `${((s.nombre_biens / totalBiens) * 100).toFixed(1)}%` : "—"}
                         </td>
                       </tr>
                     ))}
                </tbody>
              </table>
            </div>
          )}

          {/* ── TOP 5 PROJETS ────────────────────────────────────────────── */}
          {modalType === "top_projets" && (
            <div className="rounded-xl border border-border overflow-hidden">
              <table className="w-full text-xs text-left">
                <thead className="bg-muted/40 text-muted-foreground uppercase text-[10px] font-bold border-b border-border">
                  <tr>
                    <th className="p-3 text-center">Rang</th>
                    <th className="p-3">Projet / Partenaire Bailleur</th>
                    <th className="p-3 text-right">Biens financés</th>
                    <th className="p-3 text-right">% Part</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {loadingTop5 ? <LoadingRow cols={4} /> :
                   !top5 || top5.length === 0 ? <EmptyRow cols={4} /> :
                   top5
                     .filter(p => p.projet_nom.toLowerCase().includes(searchTerm.toLowerCase()))
                     .map((p, i) => (
                       <tr key={p.projet_id} className="hover:bg-muted/20 transition-colors">
                         <td className="p-3 text-center font-extrabold text-muted-foreground">#{i + 1}</td>
                         <td className="p-3 font-bold text-foreground">{p.projet_nom}</td>
                         <td className="p-3 text-right font-extrabold text-blue-700 dark:text-blue-400 tabular-nums">{formatNumber(p.nombre_biens)} biens</td>
                         <td className="p-3 text-right font-bold text-muted-foreground tabular-nums">
                           {totalBiens > 0 ? `${((p.nombre_biens / totalBiens) * 100).toFixed(1)}%` : "—"}
                         </td>
                       </tr>
                     ))}
                </tbody>
              </table>
            </div>
          )}

          {/* ── TOP 6 PAR VALEUR ─────────────────────────────────────────── */}
          {modalType === "top_valeur" && (
            <div className="rounded-xl border border-border overflow-hidden">
              <table className="w-full text-xs text-left">
                <thead className="bg-muted/40 text-muted-foreground uppercase text-[10px] font-bold border-b border-border">
                  <tr>
                    <th className="p-3 text-center">Rang</th>
                    <th className="p-3">Structure</th>
                    <th className="p-3 text-right">Valeur Patrimoniale (FCFA)</th>
                    <th className="p-3 text-right">% Valeur Totale</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {loadingTop6v ? <LoadingRow cols={4} /> :
                   !top6v || top6v.length === 0 ? <EmptyRow cols={4} /> :
                   (top6v as any[])
                     .filter(s => s.service_nom.toLowerCase().includes(searchTerm.toLowerCase()))
                     .map((s, i) => (
                       <tr key={s.service_id ?? i} className="hover:bg-muted/20 transition-colors">
                         <td className="p-3 text-center font-extrabold text-muted-foreground">#{i + 1}</td>
                         <td className="p-3 font-bold text-foreground">{s.service_nom}</td>
                         <td className="p-3 text-right font-extrabold text-purple-700 dark:text-purple-400 tabular-nums">
                           {formatVal(s.valeur_patrimoine)}
                         </td>
                         <td className="p-3 text-right font-bold text-muted-foreground tabular-nums">
                           {totalValeur > 0 ? `${((s.valeur_patrimoine / totalValeur) * 100).toFixed(1)}%` : "—"}
                         </td>
                       </tr>
                     ))}
                </tbody>
              </table>
            </div>
          )}

          {/* ── MAUVAIS ÉTAT ─────────────────────────────────────────────── */}
          {modalType === "mauvais_etat" && (
            <div className="space-y-3">
              {loadingGlobal ? (
                <div className="flex items-center justify-center gap-2 p-8 text-muted-foreground text-xs">
                  <Loader2 className="h-4 w-4 animate-spin" /> Chargement…
                </div>
              ) : (
                <>
                  <div className="grid grid-cols-3 gap-3">
                    <div className="p-3 rounded-xl border border-red-200 bg-red-50/40">
                      <p className="text-[10px] text-red-700 font-bold uppercase">Biens en mauvais état</p>
                      <p className="text-2xl font-black text-red-700 mt-1">
                        {formatNumber(global?.biens_mauvais_etat?.nombre_biens ?? 0)}
                      </p>
                      <p className="text-[10px] text-muted-foreground">{global?.biens_mauvais_etat?.pourcentage?.toFixed(1) ?? "0"}% du total</p>
                    </div>
                    <div className="p-3 rounded-xl border border-orange-200 bg-orange-50/40">
                      <p className="text-[10px] text-orange-700 font-bold uppercase">Total biens</p>
                      <p className="text-2xl font-black text-orange-700 mt-1">{formatNumber(totalBiens)}</p>
                      <p className="text-[10px] text-muted-foreground">patrimoine total</p>
                    </div>
                    <div className="p-3 rounded-xl border border-border bg-muted/10">
                      <p className="text-[10px] text-muted-foreground font-bold uppercase">En maintenance</p>
                      <p className="text-2xl font-black text-foreground mt-1">{formatNumber(global?.total_biens_maintenance ?? 0)}</p>
                      <p className="text-[10px] text-muted-foreground">biens en réparation</p>
                    </div>
                  </div>
                  <p className="text-xs text-muted-foreground p-3 rounded-lg bg-muted/20 border border-border">
                    Pour consulter la liste nominative des biens en mauvais état, accédez au module Biens et filtrez par état. Le détail individual n'est pas disponible via les endpoints statistiques.
                  </p>
                </>
              )}
            </div>
          )}

          {/* ── SANS INFORMATION ─────────────────────────────────────────── */}
          {modalType === "sans_info" && (
            <div className="space-y-3">
              {loadingGlobal ? (
                <div className="flex items-center justify-center gap-2 p-8 text-muted-foreground text-xs">
                  <Loader2 className="h-4 w-4 animate-spin" /> Chargement…
                </div>
              ) : (
                <>
                  <div className="grid grid-cols-3 gap-3">
                    <div className="p-3 rounded-xl border border-orange-200 bg-orange-50/40">
                      <p className="text-[10px] text-orange-700 font-bold uppercase">Sans état renseigné</p>
                      <p className="text-2xl font-black text-orange-700 mt-1">
                        {formatNumber(global?.biens_sans_information?.sans_etat ?? 0)}
                      </p>
                    </div>
                    <div className="p-3 rounded-xl border border-amber-200 bg-amber-50/40">
                      <p className="text-[10px] text-amber-700 font-bold uppercase">Sans occupation</p>
                      <p className="text-2xl font-black text-amber-700 mt-1">
                        {formatNumber(global?.biens_sans_information?.sans_occupation ?? 0)}
                      </p>
                    </div>
                    <div className="p-3 rounded-xl border border-yellow-200 bg-yellow-50/40">
                      <p className="text-[10px] text-yellow-700 font-bold uppercase">Sans sécurisation</p>
                      <p className="text-2xl font-black text-yellow-700 mt-1">
                        {formatNumber(global?.biens_sans_information?.sans_securisation ?? 0)}
                      </p>
                    </div>
                  </div>
                  <p className="text-xs text-muted-foreground p-3 rounded-lg bg-muted/20 border border-border">
                    Pour consulter et compléter les fiches individuelles, accédez au module Biens et filtrez par information manquante.
                  </p>
                </>
              )}
            </div>
          )}

          {/* ── GAP ÉVOLUTION ────────────────────────────────────────────── */}
          {modalType === "gap_evolution" && (
            <div className="rounded-xl border border-border overflow-hidden">
              <table className="w-full text-xs text-left">
                <thead className="bg-muted/40 text-muted-foreground uppercase text-[10px] font-bold border-b border-border">
                  <tr>
                    <th className="p-3">Région</th>
                    <th className="p-3">Catégorie</th>
                    <th className="p-3 text-right">Année début</th>
                    <th className="p-3 text-right">Année fin</th>
                    <th className="p-3 text-right">GAP</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {loadingGap ? <LoadingRow cols={5} /> :
                   !gap || gap.length === 0
                     ? <EmptyRow cols={5} message="Pas encore de données pluriannuelles disponibles" />
                     : gap
                         .filter(g => g.region_nom.toLowerCase().includes(searchTerm.toLowerCase()) || g.categorie_nom.toLowerCase().includes(searchTerm.toLowerCase()))
                         .map((g, i) => (
                           <tr key={i} className="hover:bg-muted/20 transition-colors">
                             <td className="p-3 font-bold text-foreground">{g.region_nom}</td>
                             <td className="p-3 text-muted-foreground">{g.categorie_nom}</td>
                             <td className="p-3 text-right tabular-nums">{g.annee_debut}</td>
                             <td className="p-3 text-right tabular-nums">{g.annee_fin}</td>
                             <td className={`p-3 text-right font-extrabold tabular-nums ${g.gap >= 0 ? "text-emerald-700" : "text-red-600"}`}>
                               {g.gap >= 0 ? "+" : ""}{formatNumber(g.gap)}
                             </td>
                           </tr>
                         ))}
                </tbody>
              </table>
            </div>
          )}

          {/* ── RÉFORME PAR RÉGION ───────────────────────────────────────── */}
          {modalType === "reforme_regions" && (
            <div className="space-y-3">
              <p className="text-xs text-muted-foreground p-3 rounded-lg bg-muted/20 border border-border">
                Le détail des biens à réformer par région est disponible dans les onglets Véhicules, Bâtiments et Matériel informatique. Consultez chaque onglet pour le classement régional.
              </p>
              {/* Afficher les données de classement si disponibles */}
              {loadingClassement ? (
                <div className="flex items-center justify-center gap-2 p-8 text-muted-foreground text-xs">
                  <Loader2 className="h-4 w-4 animate-spin" /> Chargement…
                </div>
              ) : classement && classement.length > 0 && (
                <div className="rounded-xl border border-border overflow-hidden">
                  <table className="w-full text-xs text-left">
                    <thead className="bg-muted/40 text-muted-foreground uppercase text-[10px] font-bold border-b border-border">
                      <tr>
                        <th className="p-3">Catégorie</th>
                        <th className="p-3">Région</th>
                        <th className="p-3 text-right">Nombre de biens</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {classement
                        .flatMap(c => c.regions.map(r => ({ cat: c.categorie_nom, region: r.region_nom, biens: r.nombre_biens })))
                        .filter(r => r.region.toLowerCase().includes(searchTerm.toLowerCase()))
                        .slice(0, 20)
                        .map((r, i) => (
                          <tr key={i} className="hover:bg-muted/20 transition-colors">
                            <td className="p-3 text-muted-foreground">{r.cat}</td>
                            <td className="p-3 font-bold text-foreground">{r.region}</td>
                            <td className="p-3 text-right font-extrabold tabular-nums">{formatNumber(r.biens)}</td>
                          </tr>
                        ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          )}

        </div>

        {/* Pied */}
        <div className="px-6 py-3 border-t border-border bg-muted/20 flex justify-between items-center text-xs">
          <span className="text-muted-foreground">Système d'Inventaire et de Suivi du Patrimoine — MINEPIA</span>
          <Button size="sm" variant="default" onClick={onClose} className="h-8 px-4 font-bold">
            Fermer
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
