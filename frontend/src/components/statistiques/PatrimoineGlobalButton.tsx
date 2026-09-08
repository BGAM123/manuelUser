/**
 * Bouton + dialogue de configuration pour l'export "Patrimoine Global".
 *
 * Ouvre une boîte de dialogue permettant de :
 *  1) choisir les regroupements à inclure dans le document Excel généré
 *     (une feuille par regroupement, mise en forme avec bordures/couleurs
 *     MINEPIA) ;
 *  2) filtrer les données incluses, en reprenant EXACTEMENT les paramètres
 *     documentés sur le Swagger de `GET /patrimoine_global` : service,
 *     categorie, type, statut, etat, sousType (ids/valeurs séparés par
 *     virgule côté API).
 *
 * Autonome : ne dépend pas des filtres de la page Statistiques, gère son
 * propre état de filtres.
 */
import { useMemo, useState } from "react";
import { FileSpreadsheet, Loader2 } from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { OrgTreeMultiSelect } from "@/components/shared/OrgTreeMultiSelect";
import { SearchableMultiSelect, type MultiSelectOption } from "@/components/shared/SearchableMultiSelect";
import { FilterCategorieTreeMulti } from "./FilterCategorieTreeMulti";
import { useEtatBienOptions } from "@/hooks/useFilterReferentiel";
import { listAssetSubtypes } from "@/api/asset-subtypes/asset-subtypes.api";
import { getPatrimoineGlobal } from "@/api/patrimoine-global/patrimoine-global.api";
import {
  DEFAULT_PATRIMOINE_GLOBAL_SECTIONS,
  downloadPatrimoineGlobalExcel,
  type PatrimoineGlobalSections,
} from "./patrimoineGlobalExcel";
import { MINEPIA_GREEN } from "./mock-stats-data";

const SECTION_OPTIONS: { key: keyof PatrimoineGlobalSections; label: string }[] = [
  { key: "synthese", label: "Synthèse générale (totaux, affectations, restitutions, statuts)" },
  { key: "parEtat", label: "Biens par état" },
  { key: "parService", label: "Biens par service" },
  { key: "parProjet", label: "Biens par projet" },
  { key: "parCategorie", label: "Biens par catégorie" },
  { key: "parType", label: "Biens par type" },
  { key: "parRegion", label: "Biens par région" },
  { key: "parDepartement", label: "Biens par département" },
  { key: "parArrondissement", label: "Biens par arrondissement" },
  { key: "consommablesParService", label: "Consommables par service" },
  { key: "consommablesParCategorie", label: "Consommables par catégorie" },
];

// Valeurs de statut de bien réellement utilisées côté back-end (Asset.statut).
const STATUT_OPTIONS = ["ACTIF", "EN MAINTENANCE", "SORTIS", "INACTIF"];

export function PatrimoineGlobalButton() {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [sections, setSections] = useState<PatrimoineGlobalSections>(DEFAULT_PATRIMOINE_GLOBAL_SECTIONS);

  // ── Filtres Swagger (GET /patrimoine_global) ──────────────────────────
  const [serviceIds, setServiceIds] = useState<number[]>([]);
  const [categorieIds, setCategorieIds] = useState<number[]>([]);
  const [assetTypeIds, setAssetTypeIds] = useState<number[]>([]);
  const [etatIds, setEtatIds] = useState<number[]>([]);
  const [sousTypeIds, setSousTypeIds] = useState<number[]>([]);
  const [statuts, setStatuts] = useState<string[]>([]);

  const { data: etatOptionsRaw = [] } = useEtatBienOptions();
  const etatOptions: MultiSelectOption[] = etatOptionsRaw
    .filter((o) => o.id != null)
    .map((o) => ({ value: o.id as number, label: o.label }));

  const { data: sousTypeOptions = [] } = useQuery({
    queryKey: ["asset-sub-types", "filter-options"],
    queryFn: () => listAssetSubtypes({ page: 1, limit: 500 }),
    staleTime: Infinity,
    select: (res): MultiSelectOption[] =>
      (res.data?.data ?? []).map((s) => ({ value: s.id, label: s.nom })),
  });

  const toggleSection = (key: keyof PatrimoineGlobalSections) => {
    setSections((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  const allChecked = SECTION_OPTIONS.every((o) => sections[o.key]);
  const toggleAll = () => {
    const next = !allChecked;
    setSections((prev) => {
      const updated = { ...prev };
      SECTION_OPTIONS.forEach((o) => (updated[o.key] = next));
      return updated;
    });
  };

  const toggleStatut = (s: string) => {
    setStatuts((prev) => (prev.includes(s) ? prev.filter((v) => v !== s) : [...prev, s]));
  };

  const activeFilterCount = useMemo(
    () =>
      [serviceIds, categorieIds, assetTypeIds, etatIds, sousTypeIds, statuts].filter((arr) => arr.length > 0).length,
    [serviceIds, categorieIds, assetTypeIds, etatIds, sousTypeIds, statuts]
  );

  const resetFilters = () => {
    setServiceIds([]);
    setCategorieIds([]);
    setAssetTypeIds([]);
    setEtatIds([]);
    setSousTypeIds([]);
    setStatuts([]);
  };

  const handleGenerate = async () => {
    const hasSelection = SECTION_OPTIONS.some((o) => sections[o.key]);
    if (!hasSelection) {
      toast.error("Veuillez sélectionner au moins un élément à inclure dans le document.");
      return;
    }
    setLoading(true);
    try {
      const csv = (ids: number[]) => (ids.length > 0 ? ids.join(",") : undefined);
      const response = await getPatrimoineGlobal({
        service: csv(serviceIds),
        categorie: csv(categorieIds),
        type: csv(assetTypeIds),
        etat: csv(etatIds),
        sousType: csv(sousTypeIds),
        statut: statuts.length > 0 ? statuts.join(",") : undefined,
      });
      const dateStr = new Date().toISOString().slice(0, 10);
      await downloadPatrimoineGlobalExcel(response, sections, `Patrimoine_Global_MINEPIA_${dateStr}.xlsx`);
      toast.success("Document Excel généré avec succès !");
      setOpen(false);
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de la génération du document Patrimoine Global.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <Button
        size="sm"
        style={{ background: MINEPIA_GREEN }}
        className="h-9 gap-1.5 text-xs text-white hover:opacity-90 font-bold shadow-xs cursor-pointer"
        onClick={() => setOpen(true)}
      >
        <FileSpreadsheet className="h-3.5 w-3.5" />
        <span>Patrimoine Global</span>
      </Button>

      <Dialog open={open} onOpenChange={(v) => !loading && setOpen(v)}>
        <DialogContent className="max-w-2xl max-h-[85vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <FileSpreadsheet className="h-5 w-5" style={{ color: MINEPIA_GREEN }} />
              Générer le document Patrimoine Global
            </DialogTitle>
            <DialogDescription>
              Choisissez les filtres et les éléments à inclure dans le fichier Excel (une feuille par élément sélectionné).
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            {/* ── Filtres (identiques au Swagger de /patrimoine_global) ──────── */}
            <div>
              <div className="flex items-center justify-between border-b border-border pb-1.5">
                <p className="text-xs font-bold text-foreground uppercase tracking-wide">Filtres</p>
                {activeFilterCount > 0 && (
                  <button
                    type="button"
                    onClick={resetFilters}
                    className="text-[11px] font-semibold hover:underline cursor-pointer text-muted-foreground"
                  >
                    Réinitialiser les filtres
                  </button>
                )}
              </div>

              <div className="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div className="flex flex-col gap-1">
                  <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Service</Label>
                  <OrgTreeMultiSelect value={serviceIds} onChange={setServiceIds} placeholder="Tous les services" selectableType="Poste" />
                </div>

                <div className="flex flex-col gap-1">
                  <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                    Catégorie / Type de bien
                  </Label>
                  <FilterCategorieTreeMulti
                    categorieIds={categorieIds}
                    assetTypeIds={assetTypeIds}
                    onChange={(cIds, tIds) => {
                      setCategorieIds(cIds);
                      setAssetTypeIds(tIds);
                    }}
                  />
                </div>

                <div className="flex flex-col gap-1">
                  <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">État du bien</Label>
                  <SearchableMultiSelect
                    options={etatOptions}
                    value={etatIds}
                    onChange={setEtatIds}
                    placeholder="Tous les états"
                    displayStyle="compact"
                  />
                </div>

                <div className="flex flex-col gap-1">
                  <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Sous-type de bien</Label>
                  <SearchableMultiSelect
                    options={sousTypeOptions}
                    value={sousTypeIds}
                    onChange={setSousTypeIds}
                    placeholder="Tous les sous-types"
                    displayStyle="compact"
                  />
                </div>
              </div>

              <div className="mt-3 flex flex-col gap-1.5">
                <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Statut</Label>
                <div className="flex flex-wrap gap-3">
                  {STATUT_OPTIONS.map((s) => (
                    <label key={s} className="flex cursor-pointer items-center gap-1.5 text-xs">
                      <Checkbox checked={statuts.includes(s)} onCheckedChange={() => toggleStatut(s)} />
                      <span>{s}</span>
                    </label>
                  ))}
                </div>
              </div>
            </div>

            {/* ── Contenu du document ─────────────────────────────────────── */}
            <div>
              <div className="flex items-center justify-between border-b border-border pb-1.5">
                <p className="text-xs font-bold text-foreground uppercase tracking-wide">Contenu du document</p>
                <button
                  type="button"
                  onClick={toggleAll}
                  className="text-[11px] font-semibold hover:underline cursor-pointer"
                  style={{ color: MINEPIA_GREEN }}
                >
                  {allChecked ? "Tout désélectionner" : "Tout sélectionner"}
                </button>
              </div>

              <div className="mt-2 max-h-56 space-y-1.5 overflow-y-auto pr-1">
                {SECTION_OPTIONS.map((o) => (
                  <label
                    key={o.key}
                    className="flex cursor-pointer items-center gap-2.5 text-xs hover:bg-muted/40 p-1.5 rounded-md transition-colors"
                  >
                    <Checkbox checked={sections[o.key]} onCheckedChange={() => toggleSection(o.key)} />
                    <span className="text-foreground leading-tight">{o.label}</span>
                  </label>
                ))}
              </div>

              <label className="mt-2 flex cursor-pointer items-center gap-2.5 rounded-md border-t border-border p-1.5 pt-2.5 text-xs hover:bg-muted/40 transition-colors">
                <Checkbox
                  checked={sections.inclureGraphiques}
                  onCheckedChange={() => setSections((prev) => ({ ...prev, inclureGraphiques: !prev.inclureGraphiques }))}
                />
                <span className="text-foreground leading-tight">
                  Inclure des graphiques visuels (courbes, barres, secteurs)
                </span>
              </label>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" size="sm" disabled={loading} onClick={() => setOpen(false)}>
              Annuler
            </Button>
            <Button
              size="sm"
              style={{ background: MINEPIA_GREEN }}
              className="text-white hover:opacity-90"
              disabled={loading}
              onClick={handleGenerate}
            >
              {loading ? (
                <>
                  <Loader2 className="h-3.5 w-3.5 animate-spin mr-1.5" />
                  Génération...
                </>
              ) : (
                <>
                  <FileSpreadsheet className="h-3.5 w-3.5 mr-1.5" />
                  Générer le fichier Excel
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

