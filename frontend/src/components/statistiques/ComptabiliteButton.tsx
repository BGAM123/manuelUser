/**
 * Bouton "Comptabilité" — ouvre un pop-up de choix entre les documents
 * comptables disponibles (Livre Journal, Fiche de détenteur, Fiche de stock),
 * chacun ouvrant ensuite sa propre boîte de dialogue de filtres (paramètres
 * Swagger exacts des endpoints GET /comptables/livre-journal,
 * GET /fiche-detenteur/{userId} et GET /comptables/fiches-stock).
 *
 * Documents générés en PDF, mise en page identique aux maquettes officielles
 * partagées : Livre Journal en A3 paysage, Fiche de détenteur en A4 paysage,
 * Fiche de stock en A4 portrait (une page par fiche consommable x service).
 */
import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import { BookOpenText, Calculator, FileText, IdCard, Loader2, PackageSearch } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { OrgTreeMultiSelect } from "@/components/shared/OrgTreeMultiSelect";
import { SearchableSelect, type SelectOption } from "@/components/shared/SearchableSelect";
import { FilterCategorieTreeMulti } from "./FilterCategorieTreeMulti";
import { listAllUsers } from "@/api/users/users.api";
import { listServices } from "@/api/services/services.api";
import { listConsumables } from "@/api/consumables/consumables.api";
import {
  getFicheDetenteur,
  getFichesStock,
  getLivreJournal,
  type FicheDetenteurType,
} from "@/api/comptabilite/comptabilite.api";
import { downloadFicheDetenteurPdf } from "./ficheDetenteurPdf";
import { downloadLivreJournalPdf } from "./livreJournalPdf";
import { downloadFicheStockPdf } from "./ficheStockPdf";
import { MINEPIA_GREEN } from "./mock-stats-data";

type ActiveDoc = "livre-journal" | "fiche-detenteur" | "fiche-stock" | null;

export function ComptabiliteButton() {
  const [mainOpen, setMainOpen] = useState(false);
  const [activeDoc, setActiveDoc] = useState<ActiveDoc>(null);

  const openDoc = (doc: ActiveDoc) => {
    setMainOpen(false);
    setActiveDoc(doc);
  };

  return (
    <>
      <Button
        size="sm"
        style={{ background: MINEPIA_GREEN }}
        className="h-9 gap-1.5 text-xs text-white hover:opacity-90 font-bold shadow-xs cursor-pointer"
        onClick={() => setMainOpen(true)}
      >
        <Calculator className="h-3.5 w-3.5" />
        <span>Comptabilité</span>
      </Button>

      <Dialog open={mainOpen} onOpenChange={setMainOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Calculator className="h-5 w-5" style={{ color: MINEPIA_GREEN }} />
              Documents comptables
            </DialogTitle>
            <DialogDescription>Choisissez le document officiel à générer.</DialogDescription>
          </DialogHeader>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <button
              type="button"
              onClick={() => openDoc("livre-journal")}
              className="flex flex-col items-center gap-2 rounded-lg border border-border p-4 text-center transition-colors hover:border-emerald-600 hover:bg-muted/40 cursor-pointer"
            >
              <BookOpenText className="h-8 w-8" style={{ color: MINEPIA_GREEN }} />
              <span className="text-sm font-semibold text-foreground">Livre Journal</span>
              <span className="text-[11px] text-muted-foreground">Format A3 paysage</span>
            </button>

            <button
              type="button"
              onClick={() => openDoc("fiche-detenteur")}
              className="flex flex-col items-center gap-2 rounded-lg border border-border p-4 text-center transition-colors hover:border-emerald-600 hover:bg-muted/40 cursor-pointer"
            >
              <IdCard className="h-8 w-8" style={{ color: MINEPIA_GREEN }} />
              <span className="text-sm font-semibold text-foreground">Fiche du détenteur</span>
              <span className="text-[11px] text-muted-foreground">Format A4 paysage</span>
            </button>

            <button
              type="button"
              onClick={() => openDoc("fiche-stock")}
              className="flex flex-col items-center gap-2 rounded-lg border border-border p-4 text-center transition-colors hover:border-emerald-600 hover:bg-muted/40 cursor-pointer"
            >
              <PackageSearch className="h-8 w-8" style={{ color: MINEPIA_GREEN }} />
              <span className="text-sm font-semibold text-foreground">Fiche de stock</span>
              <span className="text-[11px] text-muted-foreground">Format A4 portrait</span>
            </button>
          </div>
        </DialogContent>
      </Dialog>

      <LivreJournalDialog open={activeDoc === "livre-journal"} onOpenChange={(v) => !v && setActiveDoc(null)} />
      <FicheDetenteurDialog open={activeDoc === "fiche-detenteur"} onOpenChange={(v) => !v && setActiveDoc(null)} />
      <FicheStockDialog open={activeDoc === "fiche-stock"} onOpenChange={(v) => !v && setActiveDoc(null)} />
    </>
  );
}

// ─── Livre Journal ──────────────────────────────────────────────────────────

const TYPE_OPTIONS: { value: "BIEN" | "CONSOMMABLE"; label: string }[] = [
  { value: "BIEN", label: "Biens" },
  { value: "CONSOMMABLE", label: "Consommables" },
];

function LivreJournalDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (v: boolean) => void }) {
  const [loading, setLoading] = useState(false);
  const [type, setType] = useState<"BIEN" | "CONSOMMABLE" | "">("");
  const [serviceIds, setServiceIds] = useState<number[]>([]);
  const [categorieIds, setCategorieIds] = useState<number[]>([]);
  const [assetTypeIds, setAssetTypeIds] = useState<number[]>([]);
  const [dateDebut, setDateDebut] = useState("");
  const [dateFin, setDateFin] = useState("");
  const [exercice, setExercice] = useState("");

  const handleGenerate = async () => {
    setLoading(true);
    try {
      const lignes = await getLivreJournal({
        type: type || undefined,
        service: serviceIds.length > 0 ? serviceIds.join(",") : undefined,
        categorie: categorieIds.length > 0 ? categorieIds.join(",") : undefined,
        dateDebut: dateDebut || undefined,
        dateFin: dateFin || undefined,
        exercice: exercice ? Number(exercice) : undefined,
      });
      await downloadLivreJournalPdf(lignes);
      toast.success("Livre Journal généré avec succès !");
      onOpenChange(false);
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de la génération du Livre Journal.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(v) => !loading && onOpenChange(v)}>
      <DialogContent className="max-w-xl max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <BookOpenText className="h-5 w-5" style={{ color: MINEPIA_GREEN }} />
            Générer le Livre Journal
          </DialogTitle>
          <DialogDescription>
            Filtres identiques au Swagger de <code>GET /comptables/livre-journal</code>.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-3">
          <div className="flex flex-col gap-1.5">
            <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Type</Label>
            <div className="flex flex-wrap gap-3">
              <label className="flex cursor-pointer items-center gap-1.5 text-xs">
                <input
                  type="radio"
                  checked={type === ""}
                  onChange={() => setType("")}
                  className="h-3.5 w-3.5 cursor-pointer accent-emerald-700"
                />
                <span>Tous</span>
              </label>
              {TYPE_OPTIONS.map((o) => (
                <label key={o.value} className="flex cursor-pointer items-center gap-1.5 text-xs">
                  <input
                    type="radio"
                    checked={type === o.value}
                    onChange={() => setType(o.value)}
                    className="h-3.5 w-3.5 cursor-pointer accent-emerald-700"
                  />
                  <span>{o.label}</span>
                </label>
              ))}
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="flex flex-col gap-1">
              <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Service</Label>
              <OrgTreeMultiSelect value={serviceIds} onChange={setServiceIds} placeholder="Tous les services" selectableType="Poste" />
            </div>
            <div className="flex flex-col gap-1">
              <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Catégorie</Label>
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
              <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Date de début</Label>
              <Input type="date" value={dateDebut} onChange={(e) => setDateDebut(e.target.value)} />
            </div>
            <div className="flex flex-col gap-1">
              <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Date de fin</Label>
              <Input type="date" value={dateFin} onChange={(e) => setDateFin(e.target.value)} />
            </div>
            <div className="flex flex-col gap-1">
              <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Exercice (année)</Label>
              <Input
                type="number"
                placeholder="ex. 2026"
                value={exercice}
                onChange={(e) => setExercice(e.target.value)}
              />
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" size="sm" disabled={loading} onClick={() => onOpenChange(false)}>
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
                <FileText className="h-3.5 w-3.5 mr-1.5" />
                Générer le PDF
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ─── Fiche de détenteur ─────────────────────────────────────────────────────

const FICHE_TYPE_OPTIONS: { value: FicheDetenteurType; label: string }[] = [
  { value: "tous", label: "Tous (biens + consommables)" },
  { value: "biens", label: "Biens uniquement" },
  { value: "consommables", label: "Consommables uniquement" },
];

function FicheDetenteurDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (v: boolean) => void }) {
  const [loading, setLoading] = useState(false);
  const [userId, setUserId] = useState<number | null>(null);
  const [type, setType] = useState<FicheDetenteurType>("tous");

  const { data: users = [], isLoading: usersLoading } = useQuery({
    queryKey: ["users", "all", "fiche-detenteur"],
    queryFn: listAllUsers,
    staleTime: 5 * 60 * 1000,
    enabled: open,
  });

  const userOptions: SelectOption<number>[] = useMemo(
    () =>
      users.map((u) => ({
        value: u.id,
        label: `${u.firstName} ${u.lastName}${u.matricule ? ` — ${u.matricule}` : ""}`,
      })),
    [users]
  );

  const handleGenerate = async () => {
    if (!userId) {
      toast.error("Veuillez sélectionner un détenteur.");
      return;
    }
    setLoading(true);
    try {
      const response = await getFicheDetenteur(userId, type);
      await downloadFicheDetenteurPdf(response, type);
      toast.success("Fiche du détenteur générée avec succès !");
      onOpenChange(false);
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de la génération de la fiche du détenteur.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(v) => !loading && onOpenChange(v)}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <IdCard className="h-5 w-5" style={{ color: MINEPIA_GREEN }} />
            Générer la Fiche du détenteur
          </DialogTitle>
          <DialogDescription>
            Filtres identiques au Swagger de <code>GET /fiche-detenteur/{"{userId}"}</code>.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-3">
          <div className="flex flex-col gap-1">
            <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Détenteur</Label>
            <SearchableSelect
              options={userOptions}
              value={userId}
              onChange={setUserId}
              placeholder={usersLoading ? "Chargement..." : "Sélectionner un utilisateur"}
              searchPlaceholder="Rechercher un utilisateur..."
              disabled={usersLoading}
            />
          </div>

          <div className="flex flex-col gap-1.5">
            <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Contenu</Label>
            <RadioGroup value={type} onValueChange={(v) => setType(v as FicheDetenteurType)}>
              {FICHE_TYPE_OPTIONS.map((o) => (
                <label key={o.value} className="flex cursor-pointer items-center gap-2 text-xs">
                  <RadioGroupItem value={o.value} />
                  <span>{o.label}</span>
                </label>
              ))}
            </RadioGroup>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" size="sm" disabled={loading} onClick={() => onOpenChange(false)}>
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
                <FileText className="h-3.5 w-3.5 mr-1.5" />
                Générer le PDF
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ─── Fiche de stock ─────────────────────────────────────────────────────────

function FicheStockDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (v: boolean) => void }) {
  const [loading, setLoading] = useState(false);
  const [serviceId, setServiceId] = useState<number | null>(null);
  const [consumableId, setConsumableId] = useState<number | null>(null);
  const [dateDebut, setDateDebut] = useState("");
  const [dateFin, setDateFin] = useState("");
  const [search, setSearch] = useState("");

  const { data: services = [], isLoading: servicesLoading } = useQuery({
    queryKey: ["services", "all", "fiche-stock"],
    queryFn: async () => (await listServices({ page: 1, limit: 1000 })).data?.data ?? [],
    staleTime: 5 * 60 * 1000,
    enabled: open,
  });

  const { data: consumables = [], isLoading: consumablesLoading } = useQuery({
    queryKey: ["consumables", "all", "fiche-stock"],
    queryFn: async () => (await listConsumables({ page: 1, limit: 1000 })).data?.data ?? [],
    staleTime: 5 * 60 * 1000,
    enabled: open,
  });

  const serviceOptions: SelectOption<number>[] = useMemo(
    () => services.map((s) => ({ value: s.id, label: s.nom })),
    [services]
  );

  const consumableOptions: SelectOption<number>[] = useMemo(
    () => consumables.map((c) => ({ value: c.id, label: c.nom })),
    [consumables]
  );

  const handleGenerate = async () => {
    setLoading(true);
    try {
      const fiches = await getFichesStock({
        service_id: serviceId ?? undefined,
        consumable_id: consumableId ?? undefined,
        dateDebut: dateDebut || undefined,
        dateFin: dateFin || undefined,
        search: search || undefined,
      });
      if (fiches.length === 0) {
        toast.error("Aucune fiche de stock ne correspond à ces filtres.");
        return;
      }
      await downloadFicheStockPdf(fiches);
      toast.success("Fiche(s) de stock générée(s) avec succès !");
      onOpenChange(false);
    } catch (err) {
      console.error(err);
      toast.error("Erreur lors de la génération de la fiche de stock.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(v) => !loading && onOpenChange(v)}>
      <DialogContent className="max-w-lg max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <PackageSearch className="h-5 w-5" style={{ color: MINEPIA_GREEN }} />
            Générer la Fiche de stock
          </DialogTitle>
          <DialogDescription>
            Filtres identiques au Swagger de <code>GET /comptables/fiches-stock</code>.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-3">
          <div className="flex flex-col gap-1">
            <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Service</Label>
            <SearchableSelect
              options={serviceOptions}
              value={serviceId}
              onChange={setServiceId}
              placeholder={servicesLoading ? "Chargement..." : "Tous les services"}
              searchPlaceholder="Rechercher un service..."
              disabled={servicesLoading}
            />
          </div>

          <div className="flex flex-col gap-1">
            <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Consommable</Label>
            <SearchableSelect
              options={consumableOptions}
              value={consumableId}
              onChange={setConsumableId}
              placeholder={consumablesLoading ? "Chargement..." : "Tous les consommables"}
              searchPlaceholder="Rechercher un consommable..."
              disabled={consumablesLoading}
            />
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="flex flex-col gap-1">
              <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Date de début</Label>
              <Input type="date" value={dateDebut} onChange={(e) => setDateDebut(e.target.value)} />
            </div>
            <div className="flex flex-col gap-1">
              <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Date de fin</Label>
              <Input type="date" value={dateFin} onChange={(e) => setDateFin(e.target.value)} />
            </div>
          </div>

          <div className="flex flex-col gap-1">
            <Label className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Recherche</Label>
            <Input
              placeholder="Nom du consommable, service..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" size="sm" disabled={loading} onClick={() => onOpenChange(false)}>
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
                <FileText className="h-3.5 w-3.5 mr-1.5" />
                Générer le PDF
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
