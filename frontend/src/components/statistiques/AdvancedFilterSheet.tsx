import { useState } from "react";
import {
  SlidersHorizontal,
  RotateCcw,
  Check,
  MapPin,
  Tag,
  ShieldAlert,
  FileCheck2,
  User,
  Sparkles,
} from "lucide-react";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
  SheetFooter,
} from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import type { FilterState } from "./types";
import { FILTER_OPTIONS, MINEPIA_GREEN } from "./mock-stats-data";
import {
  useDepartementOptions,
  useEtatBienOptions,
} from "@/hooks/useFilterReferentiel";

interface AdvancedFilterSheetProps {
  filters: FilterState;
  onApplyFilters: (filters: FilterState) => void;
  onResetFilters: () => void;
}

export function AdvancedFilterSheet({
  filters,
  onApplyFilters,
  onResetFilters,
}: AdvancedFilterSheetProps) {
  const [open, setOpen] = useState(false);
  const [localFilters, setLocalFilters] = useState<FilterState>({ ...filters });

  // ── Référentiels dynamiques ────────────────────────────────────────────
  const { data: deptOptions = [], isLoading: loadingDepts } =
    useDepartementOptions(localFilters.regionId ?? null);
  const { data: etatOptions = [], isLoading: loadingEtats } =
    useEtatBienOptions();

  // Fallback sur les options mock si l'API ne répond pas encore
  const finalDeptOptions =
    deptOptions.length > 1
      ? deptOptions
      : FILTER_OPTIONS.departementsParRegion[
          localFilters.region as keyof typeof FILTER_OPTIONS.departementsParRegion
        ] ?? FILTER_OPTIONS.departementsParRegion.all;

  const finalEtatOptions =
    etatOptions.length > 0 ? etatOptions : FILTER_OPTIONS.etatsBien;

  // Calcul du nombre de filtres avancés actifs
  const countActiveExtraFilters = () => {
    let count = 0;
    if (filters.departement && filters.departement !== "all") count++;
    if (filters.arrondissement && filters.arrondissement !== "all") count++;
    if (filters.etatBien.length > 0) count += filters.etatBien.length;
    if (filters.statutGestion && filters.statutGestion !== "all") count++;
    if (filters.hasTitreFoncier && filters.hasTitreFoncier !== "all") count++;
    if (filters.hasCarteGrise && filters.hasCarteGrise !== "all") count++;
    if (filters.enLitigeOnly) count++;
    if (filters.searchHolder) count++;
    return count;
  };

  const activeCount = countActiveExtraFilters();

  const handleOpenChange = (isOpen: boolean) => {
    if (isOpen) {
      setLocalFilters({ ...filters });
    }
    setOpen(isOpen);
  };

  const handleApply = () => {
    onApplyFilters(localFilters);
    setOpen(false);
  };

  const handleReset = () => {
    onResetFilters();
    setOpen(false);
  };

  const toggleEtat = (etatVal: string) => {
    setLocalFilters((prev) => {
      const exists = prev.etatBien.includes(etatVal);
      // Met à jour aussi etatBienIds avec les IDs numériques correspondants
      const newEtatBien = exists
        ? prev.etatBien.filter((e) => e !== etatVal)
        : [...prev.etatBien, etatVal];
      const newEtatBienIds = newEtatBien
        .map((v) => parseInt(v, 10))
        .filter((n) => !isNaN(n));
      return { ...prev, etatBien: newEtatBien, etatBienIds: newEtatBienIds };
    });
  };

  const currentDepartements = finalDeptOptions;

  return (
    <Sheet open={open} onOpenChange={handleOpenChange}>
      <SheetTrigger asChild>
        <button
          type="button"
          className={`group relative flex h-9 items-center gap-2 rounded-xl border px-3.5 text-xs font-bold transition-all duration-300 cursor-pointer shadow-xs active:scale-95 ${
            activeCount > 0
              ? "bg-gradient-to-r from-emerald-600 to-teal-700 text-white border-transparent shadow-emerald-600/25 shadow-md hover:brightness-110"
              : "border-dashed border-emerald-600/50 bg-gradient-to-r from-emerald-500/8 via-teal-500/10 to-emerald-500/8 text-emerald-800 dark:text-emerald-300 hover:border-emerald-600 hover:bg-emerald-600/15 hover:shadow-sm"
          }`}
        >
          <div className={`flex items-center justify-center h-5 w-5 rounded-lg transition-transform duration-300 group-hover:rotate-45 ${
            activeCount > 0 ? "bg-white/20 text-white" : "bg-emerald-600/10 text-emerald-700 dark:text-emerald-300"
          }`}>
            <SlidersHorizontal className="h-3 w-3" />
          </div>

          <span className="tracking-tight font-extrabold">+ Plus de filtres</span>

          {activeCount > 0 ? (
            <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-white px-1.5 text-[10px] font-black text-emerald-800 shadow-xs animate-in zoom-in-50 duration-200">
              {activeCount}
            </span>
          ) : (
            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500/80 animate-pulse group-hover:scale-125 transition-transform" />
          )}
        </button>
      </SheetTrigger>

      <SheetContent
        side="right"
        className="w-full sm:max-w-md overflow-y-auto flex flex-col justify-between p-6 bg-card"
      >
        <div>
          <SheetHeader className="text-left border-b border-border pb-4">
            <div className="flex items-center gap-2">
              <div
                className="flex h-8 w-8 items-center justify-center rounded-lg text-white"
                style={{ background: MINEPIA_GREEN }}
              >
                <Sparkles className="h-4 w-4" />
              </div>
              <div>
                <SheetTitle className="text-base font-extrabold text-foreground">
                  Filtres avancés & Affinage
                </SheetTitle>
                <SheetDescription className="text-xs text-muted-foreground">
                  Personnalisez précisément les données et indicateurs statistiques
                </SheetDescription>
              </div>
            </div>
          </SheetHeader>

          <div className="space-y-5 py-5">
            {/* Section 1 : Localisation fine */}
            <div className="space-y-2.5">
              <div className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                <MapPin className="h-3.5 w-3.5 text-emerald-600" />
                <span>Découpage géographique précis</span>
              </div>
                <div className="grid grid-cols-1 gap-2.5 pl-5">
                <div className="space-y-1">
                  <Label className="text-[11px] text-muted-foreground">
                    Département {localFilters.region !== "all" && `(${localFilters.region})`}
                  </Label>
                  <Select
                    value={localFilters.departement}
                    onValueChange={(val) => {
                      const id = val === "all" ? null : parseInt(val, 10) || null;
                      setLocalFilters((prev) => ({
                        ...prev,
                        departement: val,
                        departementId: id,
                      }));
                    }}
                  >
                    <SelectTrigger className="h-8.5 text-xs">
                      <SelectValue placeholder={loadingDepts ? "Chargement…" : "Tous les départements"} />
                    </SelectTrigger>
                    <SelectContent>
                      {currentDepartements.map((d) => (
                        <SelectItem key={d.value} value={d.value}>
                          {d.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </div>

            {/* Section 2 : États du bien (Sélection multiple) */}
            <div className="space-y-2.5 border-t border-border pt-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                  <Tag className="h-3.5 w-3.5 text-emerald-600" />
                  <span>États des biens</span>
                </div>
                {localFilters.etatBien.length > 0 && (
                  <button
                    type="button"
                    onClick={() =>
                      setLocalFilters((prev) => ({ ...prev, etatBien: [] }))
                    }
                    className="text-[10px] text-emerald-600 hover:underline cursor-pointer"
                  >
                    Tout désélectionner
                  </button>
                )}
              </div>
              <div className="flex flex-wrap gap-1.5 pl-5">
                {finalEtatOptions.map((e) => {
                  const isChecked = localFilters.etatBien.includes(e.value);
                  return (
                    <button
                      type="button"
                      key={e.value}
                      onClick={() => toggleEtat(e.value)}
                      className={`flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer ${
                        isChecked
                          ? "bg-emerald-600 text-white shadow-xs"
                          : "bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground"
                      }`}
                    >
                      {isChecked && <Check className="h-3 w-3" />}
                      <span>{e.label}</span>
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Section 3 : Statut de gestion */}
            <div className="space-y-2.5 border-t border-border pt-4">
              <div className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                <FileCheck2 className="h-3.5 w-3.5 text-emerald-600" />
                <span>Statut de gestion patrimoniale</span>
              </div>
              <div className="pl-5">
                <Select
                  value={localFilters.statutGestion}
                  onValueChange={(val) =>
                    setLocalFilters((prev) => ({ ...prev, statutGestion: val }))
                  }
                >
                  <SelectTrigger className="h-8.5 text-xs">
                    <SelectValue placeholder="Tous les statuts de gestion" />
                  </SelectTrigger>
                  <SelectContent>
                    {FILTER_OPTIONS.statutsGestion.map((s) => (
                      <SelectItem key={s.value} value={s.value}>
                        {s.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            {/* Section 4 : Documents & Sécurisation */}
            <div className="space-y-3 border-t border-border pt-4">
              <div className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                <ShieldAlert className="h-3.5 w-3.5 text-emerald-600" />
                <span>Conformité & Sécurité</span>
              </div>
              <div className="space-y-3 pl-5">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label className="text-xs font-medium text-foreground">
                      Terrains / Biens en litige uniquement
                    </Label>
                    <p className="text-[10px] text-muted-foreground">
                      Isoler les biens sous contentieux ou contestation
                    </p>
                  </div>
                  <Switch
                    checked={localFilters.enLitigeOnly}
                    onCheckedChange={(checked) =>
                      setLocalFilters((prev) => ({ ...prev, enLitigeOnly: checked }))
                    }
                  />
                </div>

                <div className="space-y-1">
                  <Label className="text-[11px] text-muted-foreground">
                    Disponibilité Titre Foncier / Carte Grise
                  </Label>
                  <Select
                    value={localFilters.hasTitreFoncier || "all"}
                    onValueChange={(val) =>
                      setLocalFilters((prev) => ({ ...prev, hasTitreFoncier: val }))
                    }
                  >
                    <SelectTrigger className="h-8.5 text-xs">
                      <SelectValue placeholder="Tous les biens" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Tous les biens</SelectItem>
                      <SelectItem value="yes">Document officiel disponible</SelectItem>
                      <SelectItem value="no">Document manquant / absent</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </div>

            {/* Section 5 : Recherche par Détenteur */}
            <div className="space-y-2.5 border-t border-border pt-4">
              <div className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                <User className="h-3.5 w-3.5 text-emerald-600" />
                <span>Détenteur / Affectataire</span>
              </div>
              <div className="pl-5">
                <Input
                  type="text"
                  placeholder="Nom, prénom ou matricule..."
                  value={localFilters.searchHolder || ""}
                  onChange={(e) =>
                    setLocalFilters((prev) => ({
                      ...prev,
                      searchHolder: e.target.value,
                    }))
                  }
                  className="h-8.5 text-xs"
                />
              </div>
            </div>
          </div>
        </div>

        <SheetFooter className="border-t border-border pt-4 flex-row gap-2 sm:justify-between">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={handleReset}
            className="flex-1 h-9 gap-1.5 text-xs"
          >
            <RotateCcw className="h-3.5 w-3.5" />
            Réinitialiser
          </Button>
          <Button
            type="button"
            size="sm"
            onClick={handleApply}
            style={{ background: MINEPIA_GREEN }}
            className="flex-1 h-9 gap-1.5 text-xs text-white hover:opacity-90 font-bold"
          >
            <Check className="h-3.5 w-3.5" />
            Appliquer ({activeCount})
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
