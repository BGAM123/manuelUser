import { useMemo, useState, type ReactNode } from "react";
import {
  Plus,
  Search,
  Download,
  MoreVertical,
  ChevronLeft,
  ChevronRight,
  Columns as ColumnsIcon,
  ArrowRightLeft,
  Printer,
  FileCheck2,
  X,
  Pencil,
  DoorOpen,
  Trash2,
  Upload,
  Eye,
  User as UserIcon,
  ArrowLeft,
  ChevronDown,
  Package,
  Calendar as CalendarIcon,
} from "lucide-react";
import { toast } from "sonner";
import { AppShell } from "@/components/shared/AppShell";
import { StatusBadge, statutTone } from "@/components/shared/StatusBadge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
  SheetFooter,
} from "@/components/ui/sheet";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  DropdownMenuCheckboxItem,
} from "@/components/ui/dropdown-menu";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { mockBiens, formatFCFA, unites, type Bien } from "@/utils/mock-data";
import { cn } from "@/utils/utils";

/* =========================================================
   Types dérivés (mock enrichi pour la fiche détail)
   ========================================================= */

type SourceFinancement = "Budget d'Etat" | "Ressources propres" | "Partenaires" | "BIP";
const sourcesFinancement: SourceFinancement[] = [
  "Budget d'Etat",
  "Ressources propres",
  "Partenaires",
  "BIP",
];

type Piece = { name: string; size: string };
type SuiviRow = {
  id: string;
  date: string;
  evenement: string;
  structure: string;
  site: string;
  utilisateur: string;
  observation: string;
};
type MaintRow = {
  id: string;
  date: string;
  type: string;
  fournisseur: string;
  cout: number;
  technicien: string;
  etat: "Terminé" | "En cours" | "Planifié";
};
type ReevalRow = { id: string; date: string; valeur: number; motif: string };
type DeprecRow = {
  id: string;
  date: string;
  statut: string;
  etatUsuel: string;
  valeur: number;
  motif: string;
};

type BienEnriched = Bien & {
  marque: string;
  modele: string;
  numeroSerie: string;
  service: string;
  site: string;
  sourceFinancement: SourceFinancement;
  motifSortie?: string;
  photo?: string;
};

function enrich(b: Bien, i = 0): BienEnriched {
  const marques = ["Toyota", "HP", "Dell", "Yamaha", "Isuzu", "LG", "Canon"];
  const modeles = ["Hilux Double Cab", "LaserJet Pro", "Latitude 7420", "DT 125", "500L"];
  return {
    ...b,
    marque: marques[i % marques.length],
    modele: modeles[i % modeles.length],
    numeroSerie: `SN-${b.id.slice(-4)}-${(i + 1) * 173}`,
    service: b.unite,
    site: b.localisation,
    sourceFinancement: sourcesFinancement[i % sourcesFinancement.length],
  };
}

const allBiens: BienEnriched[] = mockBiens.map((b, i) => enrich(b, i));

function seedSuivi(b: BienEnriched): SuiviRow[] {
  return [
    {
      id: "s1",
      date: "20/05/2025",
      evenement: "Affectation",
      structure: "Service de la Santé Animale",
      site: "Yaoundé — Siège MINEPIA",
      utilisateur: "Pr. Claire MVONDO",
      observation: "Affectation pour suivi des activités terrain.",
    },
    {
      id: "s2",
      date: "18/01/2025",
      evenement: "Transfert",
      structure: "Direction des Moyens Généraux (DMG)",
      site: "Garage Administratif",
      utilisateur: "Jean Baptiste ELONGOU",
      observation: "Transfert pour utilisation administrative.",
    },
    {
      id: "s3",
      date: b.acquisitionDate,
      evenement: "Acquisition",
      structure: b.unite,
      site: b.localisation,
      utilisateur: b.detenteur,
      observation: "Acquisition initiale du bien.",
    },
  ];
}

function seedMaintenances(): MaintRow[] {
  return [
    { id: "m1", date: "05/01/2024", type: "Entretien préventif", fournisseur: "Auto Service", cout: 100000, technicien: "J. Mbarga", etat: "Terminé" },
    { id: "m2", date: "12/09/2023", type: "Réparation", fournisseur: "Garage Central", cout: 320000, technicien: "P. Ngouma", etat: "Terminé" },
  ];
}

function seedReevals(b: BienEnriched): ReevalRow[] {
  return [
    { id: "r1", date: "10/01/2024", valeur: b.valeurAcquisition + 2000000, motif: "Réévaluation annuelle" },
    { id: "r2", date: "15/01/2023", valeur: b.valeurAcquisition, motif: "Valeur d'acquisition" },
  ];
}

function seedDeprecs(b: BienEnriched): DeprecRow[] {
  return [
    { id: "d1", date: "10/01/2025", statut: "État usuel", etatUsuel: "Bon", valeur: b.valeurAcquisition - 1000000, motif: "Dépréciation liée à l'usage" },
  ];
}

function seedPieces(): (Piece & { addedOn: string; addedBy: string })[] {
  return [
    { name: "Carte grise.pdf", size: "245 Ko", addedOn: "20/05/2025", addedBy: "Pr. Claire MVONDO" },
    { name: "Facture_acquisition.pdf", size: "1.2 Mo", addedOn: "20/05/2025", addedBy: "Pr. Claire MVONDO" },
    { name: "Photo_bien.jpg", size: "1.8 Mo", addedOn: "20/05/2025", addedBy: "Pr. Claire MVONDO" },
    { name: "PV_reception.pdf", size: "532 Ko", addedOn: "20/05/2025", addedBy: "Pr. Claire MVONDO" },
  ];
}

/* =========================================================
   Colonnes de la liste
   ========================================================= */

type ColKey =
  | "numero"
  | "designation"
  | "categorie"
  | "type"
  | "structure"
  | "detenteur"
  | "statut"
  | "sourceFinancement"
  | "valeur"
  | "acquisitionDate"
  | "etat";

const allColumns: { key: ColKey; label: string; render: (b: BienEnriched) => ReactNode; className?: string }[] = [
  { key: "numero", label: "Référence", render: (b) => <span className="font-mono text-xs">{b.numero}</span> },
  { key: "designation", label: "Désignation", render: (b) => <span className="font-medium">{b.designation}</span> },
  { key: "categorie", label: "Catégorie", render: (b) => b.categorie },
  { key: "type", label: "Type", render: (b) => b.marque },
  { key: "structure", label: "Structure actuelle", render: (b) => b.unite },
  { key: "detenteur", label: "Détenteur actuel", render: (b) => b.detenteur },
  { key: "statut", label: "Statut", render: (b) => <StatusBadge tone={statutTone(b.statut)}>{b.statut}</StatusBadge> },
  { key: "sourceFinancement", label: "Source de financement", render: (b) => b.sourceFinancement },
  { key: "valeur", label: "Valeur (FCFA)", render: (b) => <span className="tabular-nums">{formatFCFA(b.valeurAcquisition)}</span>, className: "text-right" },
  { key: "acquisitionDate", label: "Date d'acquisition", render: (b) => b.acquisitionDate },
  { key: "etat", label: "État du bien", render: (b) => b.etat },
];

const defaultVisible: ColKey[] = [
  "numero","designation","categorie","type","structure","detenteur","statut","sourceFinancement","valeur","acquisitionDate","etat",
];

/* =========================================================
   Drawer utilitaire
   ========================================================= */

function DrawerShell({
  open,
  onOpenChange,
  title,
  subtitle,
  icon,
  children,
  onCancel,
  onSave,
  saveLabel = "Enregistrer",
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  title: string;
  subtitle?: string;
  icon: React.ComponentType<{ className?: string }>;
  children: ReactNode;
  onCancel: () => void;
  onSave: () => void;
  saveLabel?: string;
}) {
  const Icon = icon;
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
        <SheetHeader className="border-b border-border p-5 text-left">
          <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <Icon className="h-5 w-5" />
            </div>
            <div className="min-w-0 flex-1">
              <SheetTitle className="text-base font-bold uppercase tracking-wide">{title}</SheetTitle>
              {subtitle ? <SheetDescription className="text-xs">{subtitle}</SheetDescription> : null}
            </div>
          </div>
        </SheetHeader>
        <div className="flex-1 overflow-y-auto p-5">{children}</div>
        <SheetFooter className="border-t border-border p-4 sm:justify-end">
          <Button type="button" variant="outline" onClick={onCancel}>
            Annuler
          </Button>
          <Button type="button" onClick={onSave} className="gap-2">
            <FileCheck2 className="h-4 w-4" />
            {saveLabel}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}

/* Bloc "Pièces justificatives" partagé */
function AttachmentsField({
  files,
  onChange,
}: {
  files: Piece[];
  onChange: (f: Piece[]) => void;
}) {
  const add = () => onChange([...files, { name: `document_${files.length + 1}.pdf`, size: "1.0 Mo" }]);
  return (
    <div className="space-y-3">
      <p className="text-sm font-semibold text-foreground">Pièces justificatives</p>
      <button
        type="button"
        onClick={add}
        className="flex w-full flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-primary/40 bg-primary/5 p-4 text-center text-xs text-muted-foreground transition hover:bg-primary/10"
      >
        <Upload className="h-5 w-5 text-primary" />
        <span>
          Glissez-déposez vos fichiers ici ou{" "}
          <span className="font-semibold text-primary underline">cliquez pour parcourir</span>
        </span>
        <span className="text-[10px]">(PDF, JPG, PNG, Docx. Max. 10 Mo par fichier)</span>
      </button>
      {files.length > 0 && (
        <ul className="space-y-2">
          {files.map((f, i) => (
            <li key={i} className="flex items-center gap-2 rounded-md border border-border bg-background px-3 py-2 text-xs">
              <span className="flex-1 truncate font-medium">{f.name}</span>
              <span className="text-muted-foreground">{f.size}</span>
              <button
                type="button"
                aria-label="Supprimer"
                onClick={() => onChange(files.filter((_, j) => j !== i))}
                className="text-destructive hover:text-destructive/80"
              >
                <Trash2 className="h-3.5 w-3.5" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

/* =========================================================
   Shell
   ========================================================= */

type View = "liste" | "detail" | "bulkAffectation" | "formulaire";
type DrawerKey =
  | null
  | "affectation"
  | "sortie"
  | "maintenance"
  | "reevaluation"
  | "depreciation"
  ;

export default function BiensPage() {
  const [view, setView] = useState<View>("liste");
  const [biens, setBiens] = useState<BienEnriched[]>(allBiens);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [current, setCurrent] = useState<BienEnriched | null>(null);
  const [drawer, setDrawer] = useState<DrawerKey>(null);
  const [formMode, setFormMode] = useState<"create" | "edit">("create");

  const openDetail = (b: BienEnriched) => {
    setCurrent(b);
    setView("detail");
  };

  const openForm = (mode: "create" | "edit", b: BienEnriched | null = null) => {
    setFormMode(mode);
    if (mode === "edit" && b) setCurrent(b);
    setView("formulaire");
  };

  return (
    <AppShell>
      {view === "liste" && (
        <BiensList
          data={biens}
          selectedIds={selectedIds}
          onSelectionChange={setSelectedIds}
          onOpen={openDetail}
          onNew={() => openForm("create")}
          onBulkAffectation={() => setView("bulkAffectation")}
        />
      )}

      {view === "bulkAffectation" && (
        <BulkAffectationView
          count={selectedIds.length}
          onCancel={() => setView("liste")}
          onSave={() => {
            toast.success("Affectation enregistrée");
            setSelectedIds([]);
            setView("liste");
          }}
        />
      )}

      {view === "detail" && current && (
        <BienDetail
          bien={current}
          onBack={() => setView("liste")}
          onOpenDrawer={(k) => setDrawer(k)}
          onEdit={() => openForm("edit", current)}
        />
      )}

      {view === "formulaire" && (
        <BienFormPage
          mode={formMode}
          bien={formMode === "edit" ? current : null}
          onCancel={() => setView(formMode === "edit" && current ? "detail" : "liste")}
          onSave={(b, mode) => {
            if (mode === "edit") {
              setBiens((p) => p.map((x) => (x.id === b.id ? b : x)));
              setCurrent(b);
              toast.success("Bien mis à jour");
              setView("detail");
            } else {
              setBiens((p) => [b, ...p]);
              toast.success("Bien enregistré");
              setView("liste");
            }
          }}
        />
      )}

      <AffectationDrawer
        open={drawer === "affectation"}
        onOpenChange={(v) => !v && setDrawer(null)}
        count={1}
        onSave={() => {
          setDrawer(null);
          toast.success("Affectation enregistrée");
        }}
      />
      <SortieDrawer
        open={drawer === "sortie"}
        onOpenChange={(v) => !v && setDrawer(null)}
        onSave={() => { setDrawer(null); toast.success("Sortie enregistrée"); }}
      />
      <MaintenanceDrawer
        open={drawer === "maintenance"}
        onOpenChange={(v) => !v && setDrawer(null)}
        onSave={() => { setDrawer(null); toast.success("Maintenance enregistrée"); }}
      />
      <ReevaluationDrawer
        open={drawer === "reevaluation"}
        onOpenChange={(v) => !v && setDrawer(null)}
        onSave={() => { setDrawer(null); toast.success("Réévaluation enregistrée"); }}
      />
      <DepreciationDrawer
        open={drawer === "depreciation"}
        onOpenChange={(v) => !v && setDrawer(null)}
        onSave={() => { setDrawer(null); toast.success("Dépréciation enregistrée"); }}
      />
    </AppShell>
  );
}

/* =========================================================
   Liste
   ========================================================= */

function BiensList({
  data,
  selectedIds,
  onSelectionChange,
  onOpen,
  onNew,
  onBulkAffectation,
}: {
  data: BienEnriched[];
  selectedIds: string[];
  onSelectionChange: (ids: string[]) => void;
  onOpen: (b: BienEnriched) => void;
  onNew: () => void;
  onBulkAffectation: () => void;
}) {
  const [query, setQuery] = useState("");
  const [fStructure, setFStructure] = useState("all");
  const [fSource, setFSource] = useState("all");
  const [fType, setFType] = useState("all");
  const [fDate, setFDate] = useState("all");
  const [visible, setVisible] = useState<ColKey[]>(defaultVisible);
  const [page, setPage] = useState(1);
  const pageSize = 10;

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return data.filter((b) => {
      if (q && !`${b.numero} ${b.designation} ${b.categorie} ${b.detenteur}`.toLowerCase().includes(q)) return false;
      if (fStructure !== "all" && b.unite !== fStructure) return false;
      if (fSource !== "all" && b.sourceFinancement !== fSource) return false;
      if (fType !== "all" && b.categorie !== fType) return false;
      if (fDate !== "all" && !b.acquisitionDate.startsWith(fDate)) return false;
      return true;
    });
  }, [data, query, fStructure, fSource, fType, fDate]);

  const total = filtered.length;
  const totalPages = Math.max(1, Math.ceil(total / pageSize));
  const currentPage = Math.min(page, totalPages);
  const start = (currentPage - 1) * pageSize;
  const paged = filtered.slice(start, start + pageSize);

  const cols = allColumns.filter((c) => visible.includes(c.key));

  const toggleCol = (k: ColKey) =>
    setVisible((v) => (v.includes(k) ? v.filter((x) => x !== k) : [...v, k]));

  const selectedSet = new Set(selectedIds);
  const allPagedSelected = paged.length > 0 && paged.every((r) => selectedSet.has(r.id));
  const somePagedSelected = paged.some((r) => selectedSet.has(r.id)) && !allPagedSelected;
  const togglePage = () => {
    const ids = paged.map((r) => r.id);
    if (allPagedSelected) onSelectionChange(selectedIds.filter((id) => !ids.includes(id)));
    else onSelectionChange(Array.from(new Set([...selectedIds, ...ids])));
  };

  const pageNumbers = pageList(currentPage, totalPages);

  return (
    <div className="space-y-4">
      {/* Toolbar principale : recherche + Nouveau Bien + Exporter */}
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div className="relative flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={query}
            onChange={(e) => { setQuery(e.target.value); setPage(1); }}
            placeholder="Rechercher un bien, un catégorie, un détenteur..."
            className="h-11 pl-9"
          />
        </div>
        <div className="flex items-center gap-2">
          <Button onClick={onNew} className="h-11 gap-2 bg-primary text-primary-foreground hover:bg-primary/90">
            <Plus className="h-4 w-4" /> Nouveau Bien
          </Button>
          <Button variant="outline" className="h-11 gap-2">
            <Download className="h-4 w-4" /> Exporter
          </Button>
        </div>
      </div>

      {/* 4 filtres + Colonnes */}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <FilterSelect
          label="Structure"
          value={fStructure}
          onChange={(v) => { setFStructure(v); setPage(1); }}
          options={[{ value: "all", label: "Sélectionner une structure" }, ...unites.map((u) => ({ value: u, label: u }))]}
        />
        <FilterSelect
          label="Source de financement"
          value={fSource}
          onChange={(v) => { setFSource(v); setPage(1); }}
          options={[{ value: "all", label: "Sélectionner une source" }, ...sourcesFinancement.map((s) => ({ value: s, label: s }))]}
        />
        <FilterSelect
          label="Type de bien"
          value={fType}
          onChange={(v) => { setFType(v); setPage(1); }}
          options={[
            { value: "all", label: "Sélectionner un type" },
            ...["Immobilier", "Mobilier", "Informatique", "Roulant", "Cheptel"].map((c) => ({ value: c, label: c })),
          ]}
        />
        <FilterSelect
          label="Date d'acquisition"
          value={fDate}
          onChange={(v) => { setFDate(v); setPage(1); }}
          options={[
            { value: "all", label: "Sélectionner une période" },
            ...["2020", "2021", "2022", "2023", "2024", "2025"].map((y) => ({ value: y, label: y })),
          ]}
        />
        <div className="flex flex-col justify-end">
          <span className="mb-1.5 block h-4" />
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" className="h-10 justify-between gap-2">
                <span className="inline-flex items-center gap-2">
                  <ColumnsIcon className="h-4 w-4" /> Colonnes
                </span>
                <ChevronDownIcon />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              <DropdownMenuLabel>Colonnes</DropdownMenuLabel>
              <DropdownMenuSeparator />
              {allColumns.map((c) => (
                <DropdownMenuCheckboxItem
                  key={c.key}
                  checked={visible.includes(c.key)}
                  onCheckedChange={() => toggleCol(c.key)}
                  onSelect={(e) => e.preventDefault()}
                >
                  {c.label}
                </DropdownMenuCheckboxItem>
              ))}
              <DropdownMenuSeparator />
              <DropdownMenuItem onSelect={() => setVisible(defaultVisible)}>Réinitialiser</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      {/* Barre de sélection */}
      {selectedIds.length > 0 && (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 p-3">
          <div className="flex items-center gap-2 pr-2">
            <span className="inline-flex h-4 w-4 items-center justify-center rounded border border-primary bg-primary text-primary-foreground">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
            </span>
            <span className="text-sm font-semibold text-primary">
              {selectedIds.length} bien{selectedIds.length > 1 ? "s" : ""} sélectionné{selectedIds.length > 1 ? "s" : ""}
            </span>
          </div>
          <div className="mx-1 h-6 w-px bg-primary/20" />
          <Button size="sm" onClick={onBulkAffectation} className="h-9 gap-2">
            <ArrowRightLeft className="h-4 w-4" /> Affectation
          </Button>
          <Button size="sm" variant="outline" className="h-9 gap-2" onClick={() => toast.success("Sélection exportée")}>
            <Download className="h-4 w-4" /> Exporter la sélection
          </Button>
          <Button size="sm" variant="outline" className="h-9 gap-2" onClick={() => toast.success("Bordereau imprimé")}>
            <Printer className="h-4 w-4" /> Imprimer Bordereau
          </Button>
          <Button size="sm" variant="outline" className="h-9 gap-2" onClick={() => toast.success("Accusé de réception généré")}>
            <FileCheck2 className="h-4 w-4" /> Accusé de réception
          </Button>
          <button
            type="button"
            onClick={() => onSelectionChange([])}
            className="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
            aria-label="Annuler la sélection"
          >
            <X className="h-4 w-4" />
          </button>
        </div>
      )}

      {/* Tableau */}
      <div className="rounded-xl border border-border bg-card shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1100px] text-sm">
            <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
              <tr>
                <th className="w-10 px-3 py-3">
                  <input
                    type="checkbox"
                    checked={allPagedSelected}
                    ref={(el) => { if (el) el.indeterminate = somePagedSelected; }}
                    onChange={togglePage}
                    className="h-4 w-4 cursor-pointer accent-primary"
                    aria-label="Tout sélectionner"
                  />
                </th>
                {cols.map((c) => (
                  <th key={c.key} className={cn("whitespace-nowrap px-3 py-3 text-left font-semibold", c.className)}>{c.label}</th>
                ))}
                <th className="w-16 px-3 py-3 text-right font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody>
              {paged.length === 0 ? (
                <tr>
                  <td colSpan={cols.length + 2} className="px-4 py-12 text-center text-sm text-muted-foreground">Aucun bien</td>
                </tr>
              ) : (
                paged.map((b, idx) => {
                  const sel = selectedSet.has(b.id);
                  return (
                    <tr
                      key={b.id}
                      className={cn("border-t border-border transition-colors hover:bg-accent/40 cursor-pointer", idx % 2 === 1 && "bg-muted/20", sel && "bg-primary/5")}
                      onClick={() => onOpen(b)}
                    >
                      <td className="px-3 py-3" onClick={(e) => e.stopPropagation()}>
                        <input
                          type="checkbox"
                          checked={sel}
                          onChange={() =>
                            onSelectionChange(sel ? selectedIds.filter((x) => x !== b.id) : [...selectedIds, b.id])
                          }
                          className="h-4 w-4 cursor-pointer accent-primary"
                          aria-label="Sélectionner"
                        />
                      </td>
                      {cols.map((c) => (
                        <td key={c.key} className={cn("px-3 py-3 align-middle", c.className)}>{c.render(b)}</td>
                      ))}
                      <td className="px-3 py-2 text-right" onClick={(e) => e.stopPropagation()}>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <button
                              type="button"
                              className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                              aria-label="Actions"
                            >
                              <MoreVertical className="h-4 w-4" />
                            </button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuItem onSelect={() => onOpen(b)}>
                              <Eye className="mr-2 h-4 w-4" /> Voir la fiche
                            </DropdownMenuItem>
                            <DropdownMenuItem onSelect={() => { onOpen(b); }}>
                              <Pencil className="mr-2 h-4 w-4" /> Modifier
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="text-destructive" onSelect={() => toast.success("Bien supprimé")}>
                              <Trash2 className="mr-2 h-4 w-4" /> Supprimer
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination custom : Affichage de X à Y sur Z + numéros */}
        <div className="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-muted-foreground sm:text-sm">
            Affichage de {total === 0 ? 0 : start + 1} à {Math.min(total, start + pageSize)} sur {total} biens
          </p>
          <div className="flex items-center gap-1">
            <button
              type="button"
              disabled={currentPage <= 1}
              onClick={() => setPage(currentPage - 1)}
              className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground disabled:opacity-40 hover:bg-muted"
              aria-label="Précédent"
            >
              <ChevronLeft className="h-4 w-4" />
            </button>
            {pageNumbers.map((p, i) =>
              p === "..." ? (
                <span key={`e${i}`} className="px-2 text-xs text-muted-foreground">…</span>
              ) : (
                <button
                  key={p}
                  type="button"
                  onClick={() => setPage(p)}
                  className={cn(
                    "inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-xs font-semibold",
                    p === currentPage ? "bg-primary text-primary-foreground" : "border border-border text-foreground/80 hover:bg-muted"
                  )}
                >
                  {p}
                </button>
              )
            )}
            <button
              type="button"
              disabled={currentPage >= totalPages}
              onClick={() => setPage(currentPage + 1)}
              className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground disabled:opacity-40 hover:bg-muted"
              aria-label="Suivant"
            >
              <ChevronRight className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

function pageList(current: number, total: number): (number | "...")[] {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  const out: (number | "...")[] = [1];
  const s = Math.max(2, current - 1);
  const e = Math.min(total - 1, current + 1);
  if (s > 2) out.push("...");
  for (let i = s; i <= e; i++) out.push(i);
  if (e < total - 1) out.push("...");
  out.push(total);
  return out;
}

function ChevronDownIcon() {
  return (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="6 9 12 15 18 9" />
    </svg>
  );
}

function FilterSelect({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  options: { value: string; label: string }[];
}) {
  return (
    <div className="flex flex-col gap-1.5">
      <Label className="text-xs font-medium text-muted-foreground">{label}</Label>
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger className="h-10">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {options.map((o) => (
            <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}

/* =========================================================
   Fiche détail — 8 sections numérotées
   ========================================================= */

function BienDetail({
  bien,
  onBack,
  onOpenDrawer,
  onEdit,
}: {
  bien: BienEnriched;
  onBack: () => void;
  onOpenDrawer: (k: DrawerKey) => void;
  onEdit: () => void;
}) {
  const [suivi] = useState(() => seedSuivi(bien));
  const [maintenances] = useState(() => seedMaintenances());
  const [pieces] = useState(() => seedPieces());
  const [reevals] = useState(() => seedReevals(bien));
  const [deprecs] = useState(() => seedDeprecs(bien));
  type InlineKey =
    | "sortie"
    | "maintenance"
    | "reevaluation"
    | "depreciation"
    | "affectation"
    | null;
  const [inline, setInline] = useState<InlineKey>(null);
  const closeInline = () => setInline(null);
  const saveInline = (msg: string) => { setInline(null); toast.success(msg); };

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <Button variant="outline" size="sm" onClick={onBack} className="gap-1">
          <ArrowLeft className="h-4 w-4" /> Retour à la liste
        </Button>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" className="gap-2">
            <Download className="h-4 w-4" /> Exporter
          </Button>
        </div>
      </div>

      {/* 1. Information générale */}
      <Section
        num={1}
        title="Information générale"
        action={
          (
            <Button variant="outline" size="sm" className="gap-2" onClick={onEdit}>
              <Pencil className="h-4 w-4" /> Modifier
            </Button>
          )
        }
      >
        <div className="grid gap-6 md:grid-cols-[1fr_auto]">
          <div className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
            <KV k="Référence" v={bien.numero} />
            <KV k="Modèle" v={bien.modele} />
            <KV k="Localisation" v="" />
            <KV k="Désignation" v={bien.designation} />
            <KV k="Numéro de série" v={bien.numeroSerie} />
            <KV k="Service" v={bien.service} />
            <KV k="Catégorie" v={bien.categorie} />
            <KV k="Statut" v={<StatusBadge tone={statutTone(bien.statut)}>{bien.statut}</StatusBadge>} />
            <KV k="Site / Siège" v="Siège MINEPIA" />
            <KV k="Type" v="Automobile" />
            <KV k="Marque" v={bien.marque} />
          </div>
          <div className="w-full max-w-[240px] overflow-hidden rounded-lg border border-border bg-muted md:w-60">
            <div className="flex aspect-[4/3] items-center justify-center text-xs text-muted-foreground">
              Photo du bien
            </div>
          </div>
        </div>
      </Section>

      {/* 2. Détenteur du bien */}
      <Section num={2} title="Détenteur du bien">
        <div className="flex flex-wrap items-center gap-4">
          <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
            <UserIcon className="h-6 w-6" />
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold">{bien.detenteur}</p>
            <p className="text-xs text-muted-foreground">Chauffeur</p>
            <p className="text-xs text-muted-foreground">{bien.service}</p>
          </div>
          <Button variant="outline" size="sm" className="gap-2" onClick={() => toast.info("Détail du détenteur")}>
            <UserIcon className="h-4 w-4" /> Voir tous ses biens
          </Button>
        </div>
      </Section>

      {/* 3. Affectation */}
      <Section
        num={3}
        title="Affectation"
        action={
          inline === "sortie" || inline === "affectation" ? null : (
            <div className="flex flex-wrap items-center gap-2">
              <Button variant="outline" size="sm" className="gap-2 border-destructive/50 text-destructive hover:bg-destructive/10" onClick={() => setInline("sortie")}>
                <DoorOpen className="h-4 w-4" /> Sortir
              </Button>
              <Button size="sm" className="gap-2" onClick={() => setInline("affectation")}>
                <Plus className="h-4 w-4" /> Nouvelle affectation
              </Button>
            </div>
          )
        }
      >
        {inline === "sortie" ? (
          <InlineFormWrapper title="Nouvelle sortie" subtitle="Enregistrer la sortie d'un bien du patrimoine" onCancel={closeInline} onSave={() => saveInline("Sortie enregistrée")}>
            <SortieFormBody />
          </InlineFormWrapper>
        ) : inline === "affectation" ? (
          <InlineFormWrapper title="Nouvelle affectation" subtitle="Affecter ce bien à un utilisateur / service" onCancel={closeInline} onSave={() => saveInline("Affectation enregistrée")}>
            <AffectationFormBody />
          </InlineFormWrapper>
        ) : (<>
        <p className="mb-2 text-xs font-semibold text-muted-foreground">Suivi du bien (Historique complet)</p>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[720px] text-xs">
            <thead className="text-[11px] uppercase tracking-wide text-muted-foreground">
              <tr className="border-b border-border">
                <th className="px-2 py-2 text-left font-semibold">Date</th>
                <th className="px-2 py-2 text-left font-semibold">Événement</th>
                <th className="px-2 py-2 text-left font-semibold">Structure / Délégation</th>
                <th className="px-2 py-2 text-left font-semibold">Site / Emplacement</th>
                <th className="px-2 py-2 text-left font-semibold">Utilisateur</th>
                <th className="px-2 py-2 text-left font-semibold">Observation</th>
              </tr>
            </thead>
            <tbody>
              {suivi.map((s) => (
                <tr key={s.id} className="border-b border-border/60">
                  <td className="px-2 py-2.5 tabular-nums">
                    <span className="mr-2 inline-block h-2 w-2 rounded-full bg-primary" />
                    {s.date}
                  </td>
                  <td className="px-2 py-2.5 font-medium">{s.evenement}</td>
                  <td className="px-2 py-2.5">{s.structure}</td>
                  <td className="px-2 py-2.5">{s.site}</td>
                  <td className="px-2 py-2.5">{s.utilisateur}</td>
                  <td className="px-2 py-2.5 text-muted-foreground">{s.observation}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        </>)}
      </Section>

      {/* 4. Informations financières */}
      <Section num={4} title="Informations financières">
        <div className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-5">
          <KV k="Valeur d'acquisition" v={formatFCFA(bien.valeurAcquisition)} />
          <KV k="Valeur actuelle" v={formatFCFA(bien.valeurAcquisition)} />
          <KV k="Amortissement cumulé" v={formatFCFA(Math.round(bien.valeurAcquisition * 0.1))} />
          <KV k="Durée de vie" v="10 ans" />
          <KV k="Date d'acquisition" v={bien.acquisitionDate} />
        </div>
      </Section>

      {/* 5. Maintenance */}
      <Section
        num={5}
        title="Maintenance"
        action={
          inline === "maintenance" ? null : (
            <Button size="sm" variant="outline" className="gap-2" onClick={() => setInline("maintenance")}>
              <Plus className="h-4 w-4" /> Ajouter une maintenance
            </Button>
          )
        }
      >
        {inline === "maintenance" ? (
          <InlineFormWrapper title="Nouvelle maintenance" subtitle="Enregistrer une nouvelle opération de maintenance" onCancel={closeInline} onSave={() => saveInline("Maintenance enregistrée")}>
            <MaintenanceFormBody />
          </InlineFormWrapper>
        ) : (<>
        <div className="mb-4 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
          <KV k="État actuel" v={bien.etat} />
          <KV k="Dernière intervention" v={maintenances[0]?.date ?? "—"} />
          <KV k="Date de récupération" v="05/06/2025" />
        </div>
        <p className="mb-2 text-xs font-semibold text-muted-foreground">Liste des maintenances</p>
        <SimpleTable
          columns={["Date", "Type d'intervention", "Fournisseur", "Coût (FCFA)", "Technicien", "État"]}
          rows={maintenances.map((m) => [
            m.date,
            m.type,
            m.fournisseur,
            formatFCFA(m.cout),
            m.technicien,
            <StatusBadge key={m.id} tone="normal">{m.etat}</StatusBadge>,
          ])}
        />
        </>)}
      </Section>

      {/* 6. Pièces jointes */}
      <Section num={6} title="Pièces jointes">
        <SimpleTable
          columns={["Fichier", "Taille", "Ajouté le", "Ajouté par", "Actions"]}
          rows={pieces.map((p) => [
            <span key={p.name} className="font-medium text-primary">📄 {p.name}</span>,
            p.size,
            p.addedOn,
            p.addedBy,
            <div key={`a${p.name}`} className="flex items-center gap-1">
              <button className="inline-flex h-7 w-7 items-center justify-center rounded border border-primary/40 text-primary hover:bg-primary/10" aria-label="Voir">
                <Eye className="h-3.5 w-3.5" />
              </button>
              <button className="inline-flex h-7 w-7 items-center justify-center rounded border border-primary/40 text-primary hover:bg-primary/10" aria-label="Télécharger">
                <Download className="h-3.5 w-3.5" />
              </button>
            </div>,
          ])}
        />
        <div className="mt-3 flex justify-center">
          <Button variant="outline" size="sm">Voir toutes les pièces</Button>
        </div>
      </Section>

      {/* 7. Réévaluations */}
      <Section
        num={7}
        title="Réévaluations"
        action={
          inline === "reevaluation" ? null : (
            <Button size="sm" variant="outline" className="gap-2" onClick={() => setInline("reevaluation")}>
              <Plus className="h-4 w-4" /> Nouvelle réévaluation
            </Button>
          )
        }
      >
        {inline === "reevaluation" ? (
          <InlineFormWrapper title="Nouvelle réévaluation" subtitle="Enregistrer une nouvelle réévaluation" onCancel={closeInline} onSave={() => saveInline("Réévaluation enregistrée")}>
            <ReevaluationFormBody />
          </InlineFormWrapper>
        ) : (
        <SimpleTable
          columns={["Date", "Nouvelle valeur (FCFA)", "Motif"]}
          rows={reevals.map((r) => [r.date, <span key={r.id} className="tabular-nums">{formatFCFA(r.valeur)}</span>, r.motif])}
        />
        )}
      </Section>

      {/* 8. Dépréciations */}
      <Section
        num={8}
        title="Dépréciations"
        action={
          inline === "depreciation" ? null : (
            <Button size="sm" variant="outline" className="gap-2" onClick={() => setInline("depreciation")}>
              <Plus className="h-4 w-4" /> Nouvelle dépréciation
            </Button>
          )
        }
      >
        {inline === "depreciation" ? (
          <InlineFormWrapper title="Nouvelle dépréciation" subtitle="Enregistrer une nouvelle dépréciation" onCancel={closeInline} onSave={() => saveInline("Dépréciation enregistrée")}>
            <DepreciationFormBody />
          </InlineFormWrapper>
        ) : (
        <SimpleTable
          columns={["Date", "Statut (État)", "État usuel", "Nouvelle valeur (FCFA)", "Motif"]}
          rows={deprecs.map((d) => [
            <span key={d.id} className="text-destructive">{d.date}</span>,
            d.statut,
            d.etatUsuel,
            <span key={`v${d.id}`} className="tabular-nums">{formatFCFA(d.valeur)}</span>,
            d.motif,
          ])}
        />
        )}
      </Section>
    </div>
  );
}

function Section({
  num,
  title,
  action,
  children,
}: {
  num: number;
  title: string;
  action?: ReactNode;
  children: ReactNode;
}) {
  return (
    <section className="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
        <h2 className="text-base font-bold text-foreground sm:text-lg">
          <span className="mr-1">{num}.</span> {title}
        </h2>
        {action}
      </div>
      {children}
    </section>
  );
}

function KV({ k, v }: { k: string; v: ReactNode }) {
  return (
    <div>
      <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{k}</p>
      <div className="mt-0.5 text-sm text-foreground">{v || "—"}</div>
    </div>
  );
}

function SimpleTable({ columns, rows }: { columns: string[]; rows: ReactNode[][] }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[640px] text-xs">
        <thead className="text-[11px] uppercase tracking-wide text-muted-foreground">
          <tr className="border-b border-border">
            {columns.map((c) => (
              <th key={c} className="px-2 py-2 text-left font-semibold">{c}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td colSpan={columns.length} className="px-2 py-4 text-center text-muted-foreground">Aucun enregistrement</td>
            </tr>
          ) : (
            rows.map((r, i) => (
              <tr key={i} className="border-b border-border/60">
                {r.map((cell, j) => (
                  <td key={j} className="px-2 py-2.5 align-middle">{cell}</td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}

/* =========================================================
   Création (page pleine, in-place — pas de drawer)
   ========================================================= */

function Field({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
  return (
    <div className={cn("space-y-1.5", className)}>
      <Label className="text-xs font-medium text-muted-foreground">{label}</Label>
      {children}
    </div>
  );
}

/* =========================================================
   Drawers
   ========================================================= */

/* =========================================================
   BienFormDrawer — création ET modification (accordéon 5 sections)
   ========================================================= */

type BienFormState = {
  designation: string;
  numeroSerie: string;
  projet: string;
  unite: string; // Structure
  categorie: string;
  typeBien: string;
  acquisitionDate: string;
  etat: string;
  description: string;
  fournisseurNom: string;
  fournisseurEmail: string;
  fournisseurTel: string;
  valeurAcquisition: string;
  sourceFinancement: string;
  modeAcquisition: string;
  dureeVie: string;
  photo: string | null;
  pieces: (Piece & { libelle?: string })[];
};

const emptyBienForm: BienFormState = {
  designation: "",
  numeroSerie: "",
  projet: "",
  unite: "",
  categorie: "",
  typeBien: "",
  acquisitionDate: "",
  etat: "",
  description: "",
  fournisseurNom: "",
  fournisseurEmail: "",
  fournisseurTel: "",
  valeurAcquisition: "",
  sourceFinancement: "",
  modeAcquisition: "",
  dureeVie: "",
  photo: null,
  pieces: [],
};

const projetsList = ["PRODEL", "PADRP", "PRIP", "Projet SANTÉ ANIMALE"];
const typesBien = ["Véhicule", "Ordinateur", "Imprimante", "Mobilier de bureau", "Bâtiment", "Équipement", "Cheptel"];
const etatsBien: Bien["etat"][] = ["Bon", "Passable", "Mauvais"];
const modesAcquisition = ["Achat", "Don", "Legs", "Transfert", "Fabrication interne"];

function AccordionSection({
  index,
  title,
  open,
  onToggle,
  children,
}: {
  index: number;
  title: string;
  open: boolean;
  onToggle: () => void;
  children: ReactNode;
}) {
  return (
    <section className="border-b border-border last:border-b-0">
      <button
        type="button"
        onClick={onToggle}
        className="flex w-full items-center justify-between gap-3 py-3 text-left"
      >
        <span className="text-sm font-bold text-primary">
          {index}. {title}
        </span>
        <ChevronDown
          className={cn(
            "h-4 w-4 shrink-0 text-primary transition-transform",
            open && "rotate-180"
          )}
        />
      </button>
      {open ? <div className="pb-5 pt-1">{children}</div> : null}
    </section>
  );
}

function ReqLabel({ children, required }: { children: ReactNode; required?: boolean }) {
  return (
    <Label className="text-xs font-semibold text-foreground">
      {children}
      {required ? <span className="ml-0.5 text-destructive">*</span> : null}
    </Label>
  );
}

function BienFormPage({
  mode,
  bien,
  onCancel,
  onSave,
}: {
  mode: "create" | "edit";
  bien: BienEnriched | null;
  onCancel: () => void;
  onSave: (b: BienEnriched, mode: "create" | "edit") => void;
}) {
  const [form, setForm] = useState<BienFormState>(() => {
    if (mode === "edit" && bien) {
      return {
        designation: bien.designation,
        numeroSerie: bien.numeroSerie ?? "",
        projet: "",
        unite: bien.unite,
        categorie: bien.categorie,
        typeBien: bien.categorie,
        acquisitionDate: bien.acquisitionDate,
        etat: bien.etat,
        description: "",
        fournisseurNom: "",
        fournisseurEmail: "",
        fournisseurTel: "",
        valeurAcquisition: String(bien.valeurAcquisition ?? ""),
        sourceFinancement: bien.sourceFinancement,
        modeAcquisition: "Achat",
        dureeVie: "",
        photo: bien.photo ?? null,
        pieces: [],
      };
    }
    return emptyBienForm;
  });
  const [openSection, setOpenSection] = useState<number>(1);

  const set = <K extends keyof BienFormState>(k: K, v: BienFormState[K]) =>
    setForm((f) => ({ ...f, [k]: v }));

  const toggleSection = (n: number) => setOpenSection((s) => (s === n ? 0 : n));

  const submit = () => {
    // Validation obligatoire
    const req: [keyof BienFormState, string][] = [
      ["designation", "Nom du bien"],
      ["unite", "Structure"],
      ["categorie", "Catégorie"],
      ["typeBien", "Type de bien"],
      ["acquisitionDate", "Date d'acquisition"],
      ["etat", "État du bien"],
      ["fournisseurNom", "Nom du fournisseur"],
      ["fournisseurEmail", "Email fournisseur"],
      ["fournisseurTel", "Téléphone fournisseur"],
      ["valeurAcquisition", "Valeur du bien"],
      ["sourceFinancement", "Source de financement"],
      ["modeAcquisition", "Mode d'acquisition"],
    ];
    for (const [k, label] of req) {
      if (!String(form[k] ?? "").trim()) {
        toast.error(`${label} est obligatoire`);
        // Ouvrir la section correspondante
        if (["designation", "numeroSerie", "projet", "unite", "categorie", "typeBien", "acquisitionDate", "etat", "description"].includes(k as string))
          setOpenSection(1);
        else if (["fournisseurNom", "fournisseurEmail", "fournisseurTel"].includes(k as string)) setOpenSection(2);
        else setOpenSection(3);
        return;
      }
    }
    if (!form.photo) {
      toast.error("La photo du bien est obligatoire");
      setOpenSection(4);
      return;
    }

    const base: Bien = {
      id: mode === "edit" && bien ? bien.id : `B-${Date.now().toString().slice(-6)}`,
      numero:
        mode === "edit" && bien
          ? bien.numero
          : `MINEPIA-${new Date().getFullYear()}-${String(Math.floor(Math.random() * 9999)).padStart(4, "0")}`,
      designation: form.designation,
      categorie: (["Immobilier", "Mobilier", "Informatique", "Roulant", "Cheptel"].includes(form.categorie)
        ? form.categorie
        : "Mobilier") as Bien["categorie"],
      acquisitionDate: form.acquisitionDate,
      valeurAcquisition: Number(form.valeurAcquisition) || 0,
      detenteur: bien?.detenteur ?? "—",
      unite: form.unite,
      etat: (etatsBien.includes(form.etat as Bien["etat"]) ? form.etat : "Bon") as Bien["etat"],
      statut: bien?.statut ?? "Actif",
      localisation: bien?.localisation ?? "—",
    };
    const enriched: BienEnriched = {
      ...(mode === "edit" && bien ? bien : enrich(base, 0)),
      ...base,
      numeroSerie: form.numeroSerie || `SN-${base.id.slice(-4)}`,
      sourceFinancement: (sourcesFinancement.includes(form.sourceFinancement as SourceFinancement)
        ? form.sourceFinancement
        : "Budget d'Etat") as SourceFinancement,
      photo: form.photo ?? undefined,
    };
    onSave(enriched, mode);
  };

  const onPickPhoto = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => set("photo", String(reader.result));
    reader.readAsDataURL(file);
  };

  return (
    <div className="mx-auto w-full max-w-5xl space-y-4">
      {/* En-tête */}
      <div className="flex items-center gap-3">
        <Button variant="outline" size="sm" onClick={onCancel} className="gap-1">
          <ArrowLeft className="h-4 w-4" /> Retour
        </Button>
      </div>

      <div className="rounded-xl border border-border bg-card shadow-sm">
        <div className="flex items-start gap-3 border-b border-border p-5">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
            <Package className="h-5 w-5" />
          </div>
          <div className="min-w-0 flex-1">
            <h2 className="text-base font-bold uppercase tracking-wide text-primary">
              {mode === "edit" ? "Modifier le bien" : "Nouveau bien"}
            </h2>
            <p className="text-xs text-muted-foreground">
              {mode === "edit"
                ? "Mettre à jour les informations du bien"
                : "Enregistrer un nouveau bien dans le patrimoine"}
            </p>
          </div>
        </div>

        <div className="px-5">
          {/* Section 1 */}
          <AccordionSection index={1} title="Informations générales" open={openSection === 1} onToggle={() => toggleSection(1)}>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div className="space-y-1.5">
                <ReqLabel required>Nom du bien</ReqLabel>
                <Input value={form.designation} onChange={(e) => set("designation", e.target.value)} placeholder="Saisir le nom du bien" />
              </div>
              <div className="space-y-1.5">
                <ReqLabel>Numéro de série</ReqLabel>
                <Input value={form.numeroSerie} onChange={(e) => set("numeroSerie", e.target.value)} placeholder="Saisir le numéro de série" />
              </div>
              <div className="space-y-1.5">
                <ReqLabel>Projet</ReqLabel>
                <Select value={form.projet} onValueChange={(v) => set("projet", v)}>
                  <SelectTrigger><SelectValue placeholder="Sélectionner un projet" /></SelectTrigger>
                  <SelectContent>
                    {projetsList.map((p) => <SelectItem key={p} value={p}>{p}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <ReqLabel required>Structure</ReqLabel>
                <Select value={form.unite} onValueChange={(v) => set("unite", v)}>
                  <SelectTrigger><SelectValue placeholder="Sélectionner une structure" /></SelectTrigger>
                  <SelectContent>
                    {unites.map((u) => <SelectItem key={u} value={u}>{u}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <ReqLabel required>Catégorie</ReqLabel>
                <Select value={form.categorie} onValueChange={(v) => set("categorie", v)}>
                  <SelectTrigger><SelectValue placeholder="Sélectionner une catégorie" /></SelectTrigger>
                  <SelectContent>
                    {["Immobilier", "Mobilier", "Informatique", "Roulant", "Cheptel"].map((c) => (
                      <SelectItem key={c} value={c}>{c}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <ReqLabel required>Type de bien</ReqLabel>
                <Select value={form.typeBien} onValueChange={(v) => set("typeBien", v)}>
                  <SelectTrigger><SelectValue placeholder="Sélectionner un type" /></SelectTrigger>
                  <SelectContent>
                    {typesBien.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <ReqLabel required>Date d'acquisition</ReqLabel>
                <div className="relative">
                  <Input
                    type="date"
                    value={form.acquisitionDate}
                    onChange={(e) => set("acquisitionDate", e.target.value)}
                    placeholder="jj/mm/aaaa"
                  />
                  <CalendarIcon className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                </div>
              </div>
              <div className="space-y-1.5">
                <ReqLabel required>État du bien</ReqLabel>
                <Select value={form.etat} onValueChange={(v) => set("etat", v)}>
                  <SelectTrigger><SelectValue placeholder="Sélectionner l'état du bien" /></SelectTrigger>
                  <SelectContent>
                    {etatsBien.map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5 sm:col-span-2">
                <ReqLabel>Description</ReqLabel>
                <Textarea
                  value={form.description}
                  onChange={(e) => set("description", e.target.value)}
                  placeholder="Décrire le bien (caractéristiques, état, détails...)"
                  rows={3}
                />
              </div>
            </div>
          </AccordionSection>

          {/* Section 2 */}
          <AccordionSection index={2} title="Informations du fournisseur" open={openSection === 2} onToggle={() => toggleSection(2)}>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div className="space-y-1.5">
                <ReqLabel required>Nom du fournisseur</ReqLabel>
                <Input value={form.fournisseurNom} onChange={(e) => set("fournisseurNom", e.target.value)} placeholder="Saisir le nom du fournisseur" />
              </div>
              <div className="space-y-1.5">
                <ReqLabel required>Email</ReqLabel>
                <Input value={form.fournisseurEmail} onChange={(e) => set("fournisseurEmail", e.target.value)} placeholder="Saisir l'email du fournisseur" />
              </div>
              <div className="space-y-1.5 sm:col-span-2">
                <ReqLabel required>Téléphone</ReqLabel>
                <Input value={form.fournisseurTel} onChange={(e) => set("fournisseurTel", e.target.value)} placeholder="Saisir le numéro de téléphone" />
              </div>
            </div>
          </AccordionSection>

          {/* Section 3 */}
          <AccordionSection index={3} title="Informations financières" open={openSection === 3} onToggle={() => toggleSection(3)}>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div className="space-y-1.5">
                <ReqLabel required>Valeur du bien (FCFA)</ReqLabel>
                <Input type="number" value={form.valeurAcquisition} onChange={(e) => set("valeurAcquisition", e.target.value)} placeholder="Saisir la valeur du bien" />
              </div>
              <div className="space-y-1.5">
                <ReqLabel required>Source de financement</ReqLabel>
                <Select value={form.sourceFinancement} onValueChange={(v) => set("sourceFinancement", v)}>
                  <SelectTrigger><SelectValue placeholder="Sélectionner la source" /></SelectTrigger>
                  <SelectContent>
                    {sourcesFinancement.map((s) => <SelectItem key={s} value={s}>{s}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <ReqLabel required>Mode d'acquisition</ReqLabel>
                <Select value={form.modeAcquisition} onValueChange={(v) => set("modeAcquisition", v)}>
                  <SelectTrigger><SelectValue placeholder="Sélectionner le mode" /></SelectTrigger>
                  <SelectContent>
                    {modesAcquisition.map((m) => <SelectItem key={m} value={m}>{m}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <ReqLabel>Durée de vie (années)</ReqLabel>
                <Input type="number" value={form.dureeVie} onChange={(e) => set("dureeVie", e.target.value)} placeholder="Saisir la durée de vie estimée" />
              </div>
            </div>
          </AccordionSection>

          {/* Section 4 */}
          <AccordionSection index={4} title="Photo du bien" open={openSection === 4} onToggle={() => toggleSection(4)}>
            <div className="space-y-2">
              <ReqLabel required>Sélectionner une photo</ReqLabel>
              {form.photo ? (
                <div className="relative overflow-hidden rounded-lg border border-border">
                  <img src={form.photo} alt="Aperçu du bien" className="h-48 w-full object-cover" />
                  <button
                    type="button"
                    aria-label="Supprimer la photo"
                    onClick={() => set("photo", null)}
                    className="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-background/90 text-foreground shadow hover:bg-background"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </div>
              ) : (
                <label className="flex h-32 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-primary/40 bg-primary/5 p-4 text-center text-xs text-muted-foreground transition hover:bg-primary/10">
                  <Upload className="h-5 w-5 text-primary" />
                  <span>Cliquez pour sélectionner une photo</span>
                  <input type="file" accept="image/*" className="hidden" onChange={onPickPhoto} />
                </label>
              )}
            </div>
          </AccordionSection>

          {/* Section 5 */}
          <AccordionSection index={5} title="Pièces justificatives" open={openSection === 5} onToggle={() => toggleSection(5)}>
            <div className="space-y-3">
              <p className="text-xs font-medium text-muted-foreground">Ajouter des pièces jointes</p>
              <button
                type="button"
                onClick={() =>
                  set("pieces", [
                    ...form.pieces,
                    { name: `document_${form.pieces.length + 1}.pdf`, size: "1.0 Mo", libelle: "Pièce jointe" },
                  ])
                }
                className="flex w-full flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-primary/40 bg-primary/5 p-4 text-center text-xs text-muted-foreground transition hover:bg-primary/10"
              >
                <Upload className="h-5 w-5 text-primary" />
                <span>
                  Glissez-déposez vos fichiers ici ou{" "}
                  <span className="font-semibold text-primary underline">cliquez pour parcourir</span>
                </span>
                <span className="text-[10px]">PDF, Image, Word, Excel (Max 10 Mo)</span>
              </button>
              {form.pieces.length > 0 && (
                <ul className="space-y-2">
                  {form.pieces.map((f, i) => (
                    <li key={i} className="flex items-center gap-2 rounded-md border border-border bg-background px-3 py-2 text-xs">
                      <span className="min-w-[110px] font-medium">{f.libelle ?? "Document"}</span>
                      <span className="flex-1 truncate text-primary underline">{f.name}</span>
                      <span className="text-muted-foreground">{f.size}</span>
                      <button
                        type="button"
                        aria-label="Supprimer"
                        onClick={() => set("pieces", form.pieces.filter((_, j) => j !== i))}
                        className="text-destructive hover:text-destructive/80"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                      <button
                        type="button"
                        aria-label="Retirer"
                        onClick={() => set("pieces", form.pieces.filter((_, j) => j !== i))}
                        className="text-muted-foreground hover:text-foreground"
                      >
                        <X className="h-3.5 w-3.5" />
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </AccordionSection>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border p-4">
          <Button type="button" variant="outline" onClick={onCancel}>
            Annuler
          </Button>
          <Button type="button" onClick={submit} className="gap-2">
            <FileCheck2 className="h-4 w-4" />
            {mode === "edit" ? "Enregistrer les modifications" : "Enregistrer le bien"}
          </Button>
        </div>
      </div>
    </div>
  );
}

/* =========================================================
   Inline forms (transformation in-place)
   ========================================================= */

function InlineFormWrapper({
  title,
  subtitle,
  onCancel,
  onSave,
  children,
}: {
  title: string;
  subtitle?: string;
  onCancel: () => void;
  onSave: () => void;
  children: ReactNode;
}) {
  return (
    <div className="animate-in fade-in-50 duration-150">
      <div className="mb-4 flex items-center gap-2 rounded-md bg-primary/5 px-3 py-2">
        <Button variant="ghost" size="sm" onClick={onCancel} className="gap-1 -ml-1">
          <ArrowLeft className="h-4 w-4" /> Retour
        </Button>
        <div className="min-w-0">
          <p className="text-sm font-semibold text-primary">{title}</p>
          {subtitle ? <p className="text-xs text-muted-foreground">{subtitle}</p> : null}
        </div>
      </div>
      <div className="space-y-5">{children}</div>
      <div className="mt-5 flex justify-end gap-2 border-t border-border pt-4">
        <Button variant="outline" size="sm" onClick={onCancel}>Annuler</Button>
        <Button size="sm" onClick={onSave}>Enregistrer</Button>
      </div>
    </div>
  );
}

function SortieFormBody() {
  const [motif, setMotif] = useState("");
  const [destination, setDestination] = useState<string>(unites[0]);
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [pieces, setPieces] = useState<Piece[]>([]);
  return (
    <>
      <Field label="Motif de sortie *"><Input value={motif} onChange={(e) => setMotif(e.target.value)} placeholder="Ex : Réforme, Vol, Perte..." /></Field>
      <Field label="Organigramme (Destination) *">
        <Select value={destination} onValueChange={setDestination}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>{unites.map((u) => <SelectItem key={u} value={u}>{u}</SelectItem>)}</SelectContent>
        </Select>
      </Field>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Date de sortie *"><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
        <Field label="Protocole / Référence"><Input placeholder="N° document..." /></Field>
      </div>
      <Field label="Observations"><Textarea rows={2} placeholder="Informations complémentaires..." /></Field>
      <AttachmentsField files={pieces} onChange={setPieces} />
    </>
  );
}

function ModifierFormBody({ bien }: { bien: BienEnriched }) {
  const [form, setForm] = useState<BienEnriched>(bien);
  const [pieces, setPieces] = useState<Piece[]>([]);
  return (
    <>
      <Field label="Désignation *"><Input value={form.designation} onChange={(e) => setForm({ ...form, designation: e.target.value })} /></Field>
      <Field label="Organigramme *">
        <Select value={form.unite} onValueChange={(v) => setForm({ ...form, unite: v })}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            {unites.map((u) => <SelectItem key={u} value={u}>{u}</SelectItem>)}
          </SelectContent>
        </Select>
      </Field>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Marque"><Input value={form.marque} onChange={(e) => setForm({ ...form, marque: e.target.value })} /></Field>
        <Field label="Modèle"><Input value={form.modele} onChange={(e) => setForm({ ...form, modele: e.target.value })} /></Field>
      </div>
      <Field label="Numéro de série"><Input value={form.numeroSerie} onChange={(e) => setForm({ ...form, numeroSerie: e.target.value })} /></Field>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Valeur (FCFA)"><Input type="number" value={form.valeurAcquisition} onChange={(e) => setForm({ ...form, valeurAcquisition: Number(e.target.value) })} /></Field>
        <Field label="Source de financement">
          <Select value={form.sourceFinancement} onValueChange={(v) => setForm({ ...form, sourceFinancement: v as SourceFinancement })}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              {sourcesFinancement.map((s) => <SelectItem key={s} value={s}>{s}</SelectItem>)}
            </SelectContent>
          </Select>
        </Field>
      </div>
      <AttachmentsField files={pieces} onChange={setPieces} />
    </>
  );
}

function AffectationFormBody() {
  const [unite, setUnite] = useState<string>(unites[0]);
  const [debut, setDebut] = useState(new Date().toISOString().slice(0, 10));
  const [fin, setFin] = useState("");
  const [commentaires, setCommentaires] = useState("");
  const [pieces, setPieces] = useState<Piece[]>([]);
  return (
    <>
      <div>
        <p className="mb-3 text-sm font-semibold">Affectation</p>
        <Field label="Organigramme *">
          <Select value={unite} onValueChange={setUnite}>
            <SelectTrigger><SelectValue placeholder="Sélectionner une position dans l'organigramme" /></SelectTrigger>
            <SelectContent>
              {unites.map((u) => <SelectItem key={u} value={u}>{u}</SelectItem>)}
            </SelectContent>
          </Select>
        </Field>
      </div>
      <div>
        <p className="mb-3 text-sm font-semibold">Détails</p>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Date de début *"><Input type="date" value={debut} onChange={(e) => setDebut(e.target.value)} /></Field>
          <Field label="Date de fin (optionnelle)"><Input type="date" value={fin} onChange={(e) => setFin(e.target.value)} /></Field>
        </div>
        <Field label="Commentaires" className="mt-3">
          <Textarea rows={3} value={commentaires} onChange={(e) => setCommentaires(e.target.value)} placeholder="Affectation pour les missions de terrain..." />
        </Field>
      </div>
      <AttachmentsField files={pieces} onChange={setPieces} />
    </>
  );
}

function MaintenanceFormBody() {
  const [pieces, setPieces] = useState<Piece[]>([]);
  return (
    <>
      <Field label="État *">
        <Select><SelectTrigger><SelectValue placeholder="Sélectionner…" /></SelectTrigger>
          <SelectContent>{["Bon", "Passable", "Mauvais"].map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}</SelectContent>
        </Select>
      </Field>
      <Field label="Motif *"><Textarea rows={3} placeholder="Décrivez les travaux réalisés..." /></Field>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Coût (FCFA) *"><Input type="number" placeholder="0" /></Field>
        <Field label="Date d'intervention *"><Input type="date" defaultValue={new Date().toISOString().slice(0, 10)} /></Field>
      </div>
      <Field label="Date de récupération"><Input type="date" /></Field>
      <AttachmentsField files={pieces} onChange={setPieces} />
    </>
  );
}

function ReevaluationFormBody() {
  const [pieces, setPieces] = useState<Piece[]>([]);
  return (
    <>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Valeur actuelle"><Input value="25 000 000 FCFA" readOnly /></Field>
        <Field label="Nouvelle valeur *"><Input type="number" placeholder="FCFA" /></Field>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Méthode d'évaluation *">
          <Select><SelectTrigger><SelectValue placeholder="Sélectionner…" /></SelectTrigger>
            <SelectContent>{["Comparable", "Coût de remplacement", "DCF"].map((m) => <SelectItem key={m} value={m}>{m}</SelectItem>)}</SelectContent>
          </Select>
        </Field>
        <Field label="Expert / Responsable *">
          <Select><SelectTrigger><SelectValue placeholder="Sélectionner…" /></SelectTrigger>
            <SelectContent>{["M. NDONGO Paul", "Mme MBALLA Rose"].map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}</SelectContent>
          </Select>
        </Field>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Date de réévaluation *"><Input type="date" defaultValue={new Date().toISOString().slice(0, 10)} /></Field>
        <Field label="Motif *"><Input placeholder="Le motif…" /></Field>
      </div>
      <Field label="Observations"><Textarea rows={2} placeholder="Observations sur la réévaluation…" /></Field>
      <AttachmentsField files={pieces} onChange={setPieces} />
    </>
  );
}

function DepreciationFormBody() {
  const [pieces, setPieces] = useState<Piece[]>([]);
  return (
    <>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Type de dépréciation *">
          <Select><SelectTrigger><SelectValue placeholder="Sélectionner…" /></SelectTrigger>
            <SelectContent>{["Linéaire", "Dégressif", "Progressif"].map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}</SelectContent>
          </Select>
        </Field>
        <Field label="Méthode d'amortissement *">
          <Select><SelectTrigger><SelectValue placeholder="Sélectionner…" /></SelectTrigger>
            <SelectContent>{["Linéaire", "Dégressif"].map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}</SelectContent>
          </Select>
        </Field>
        <Field label="Durée de vie (années) *"><Input type="number" placeholder="0" /></Field>
        <Field label="Valeur actuelle (FCFA)"><Input type="number" placeholder="0" /></Field>
        <Field label="Taux de dépréciation (%)"><Input type="number" placeholder="0" /></Field>
        <Field label="Montant de dépréciation (FCFA)"><Input type="number" placeholder="0" /></Field>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Date de dépréciation *"><Input type="date" defaultValue={new Date().toISOString().slice(0, 10)} /></Field>
        <Field label="Motif *"><Input placeholder="Sélectionner le motif…" /></Field>
      </div>
      <Field label="Observations"><Textarea rows={2} placeholder="Observations sur la dépréciation…" /></Field>
      <AttachmentsField files={pieces} onChange={setPieces} />
    </>
  );
}

/* =========================================================
   Vue Affectation groupée — la liste se transforme EN PLACE
   en un formulaire d'affectation, avec l'en-tête indiquant
   le nombre de biens sélectionnés (retirables).
   ========================================================= */
function BulkAffectationView({
  count,
  onCancel,
  onSave,
}: {
  count: number;
  onCancel: () => void;
  onSave: () => void;
}) {
  const [unite, setUnite] = useState<string>(unites[0]);
  const [debut, setDebut] = useState(new Date().toISOString().slice(0, 10));
  const [fin, setFin] = useState("");
  const [commentaires, setCommentaires] = useState("");
  const [pieces, setPieces] = useState<Piece[]>([]);

  return (
    <div className="space-y-4">
      {/* En-tête */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="outline" size="sm" onClick={onCancel} className="h-9 gap-2">
            <ArrowLeft className="h-4 w-4" /> Retour à la liste
          </Button>
          <div>
            <h2 className="text-lg font-semibold">Nouvelle affectation</h2>
            <p className="text-sm text-muted-foreground">
              Affecter <span className="font-semibold text-primary">{count}</span> bien{count > 1 ? "s" : ""} à un utilisateur / service
            </p>
          </div>
        </div>
      </div>

      {/* Formulaire d'affectation */}
      <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
        <div className="space-y-5">
          <div>
            <p className="mb-3 text-sm font-semibold">Affectation</p>
            <Field label="Organigramme *">
              <Select value={unite} onValueChange={setUnite}>
                <SelectTrigger><SelectValue placeholder="Sélectionner une position dans l'organigramme" /></SelectTrigger>
                <SelectContent>
                  {unites.map((u) => <SelectItem key={u} value={u}>{u}</SelectItem>)}
                </SelectContent>
              </Select>
            </Field>
          </div>
          <div>
            <p className="mb-3 text-sm font-semibold">Détails</p>
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Date de début *"><Input type="date" value={debut} onChange={(e) => setDebut(e.target.value)} /></Field>
              <Field label="Date de fin (optionnelle)"><Input type="date" value={fin} onChange={(e) => setFin(e.target.value)} /></Field>
            </div>
            <Field label="Commentaires" className="mt-3">
              <Textarea rows={3} value={commentaires} onChange={(e) => setCommentaires(e.target.value)} placeholder="Affectation pour les missions de terrain et suivi des activités..." />
            </Field>
          </div>
          <AttachmentsField files={pieces} onChange={setPieces} />
        </div>
        <div className="mt-6 flex justify-end gap-2 border-t border-border pt-4">
          <Button variant="outline" size="sm" onClick={onCancel} className="h-9">Annuler</Button>
          <Button size="sm" onClick={onSave} disabled={count === 0} className="h-9">Enregistrer</Button>
        </div>
      </div>
    </div>
  );
}

function AffectationDrawer({
  open, onOpenChange, count, onSave,
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  count: number;
  onSave: () => void;
}) {
  const [unite, setUnite] = useState<string>(unites[0]);
  const [debut, setDebut] = useState(new Date().toISOString().slice(0, 10));
  const [fin, setFin] = useState("");
  const [commentaires, setCommentaires] = useState("");
  const [pieces, setPieces] = useState<Piece[]>([]);
  return (
    <DrawerShell
      open={open}
      onOpenChange={onOpenChange}
      title="Nouvelle affectation"
      subtitle={count > 1 ? `Affecter ${count} biens à un utilisateur / service` : "Affecter un bien à un utilisateur / service"}
      icon={UserIcon}
      onCancel={() => onOpenChange(false)}
      onSave={onSave}
    >
      <div className="space-y-5">
        <div>
          <p className="mb-3 text-sm font-semibold">Affectation</p>
          <Field label="Organigramme *">
            <Select value={unite} onValueChange={setUnite}>
              <SelectTrigger><SelectValue placeholder="Sélectionner une position dans l'organigramme" /></SelectTrigger>
              <SelectContent>
                {unites.map((u) => <SelectItem key={u} value={u}>{u}</SelectItem>)}
              </SelectContent>
            </Select>
          </Field>
        </div>
        <div>
          <p className="mb-3 text-sm font-semibold">Détails</p>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Date de début *"><Input type="date" value={debut} onChange={(e) => setDebut(e.target.value)} /></Field>
            <Field label="Date de fin (optionnelle)"><Input type="date" value={fin} onChange={(e) => setFin(e.target.value)} /></Field>
          </div>
          <Field label="Commentaires" className="mt-3">
            <Textarea rows={3} value={commentaires} onChange={(e) => setCommentaires(e.target.value)} placeholder="Affectation pour les missions de terrain et suivi des activités..." />
          </Field>
        </div>
        <AttachmentsField files={pieces} onChange={setPieces} />
      </div>
    </DrawerShell>
  );
}

function SortieDrawer({ open, onOpenChange, onSave }: { open: boolean; onOpenChange: (v: boolean) => void; onSave: () => void }) {
  const [motif, setMotif] = useState("");
  const [destination, setDestination] = useState<string>(unites[0]);
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [pieces, setPieces] = useState<Piece[]>([]);
  return (
    <DrawerShell open={open} onOpenChange={onOpenChange} title="Nouvelle sortie" subtitle="Enregistrer la sortie d'un bien du patrimoine" icon={DoorOpen} onCancel={() => onOpenChange(false)} onSave={onSave}>
      <div className="space-y-5">
        <Field label="Motif de sortie *"><Input value={motif} onChange={(e) => setMotif(e.target.value)} placeholder="Ex : Réforme, Vol, Perte..." /></Field>
        <Field label="Organigramme (Destination) *">
          <Select value={destination} onValueChange={setDestination}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>{unites.map((u) => <SelectItem key={u} value={u}>{u}</SelectItem>)}</SelectContent>
          </Select>
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Date de sortie *"><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
          <Field label="Protocole / Référence"><Input placeholder="N° document..." /></Field>
        </div>
        <Field label="Observations"><Textarea rows={2} placeholder="Informations complémentaires..." /></Field>
        <AttachmentsField files={pieces} onChange={setPieces} />
      </div>
    </DrawerShell>
  );
}

function MaintenanceDrawer({ open, onOpenChange, onSave }: { open: boolean; onOpenChange: (v: boolean) => void; onSave: () => void }) {
  const [pieces, setPieces] = useState<Piece[]>([]);
  return (
    <DrawerShell open={open} onOpenChange={onOpenChange} title="Nouvelle maintenance" subtitle="Enregistrer une nouvelle opération de maintenance" icon={FileCheck2} onCancel={() => onOpenChange(false)} onSave={onSave}>
      <div className="space-y-5">
        <Field label="État *">
          <Select><SelectTrigger><SelectValue placeholder="Sélectionner…" /></SelectTrigger>
            <SelectContent>{["Bon", "Passable", "Mauvais"].map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}</SelectContent>
          </Select>
        </Field>
        <Field label="Motif *"><Textarea rows={3} placeholder="Décrivez les travaux réalisés..." /></Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Coût (FCFA) *"><Input type="number" placeholder="0" /></Field>
          <Field label="Date d'intervention *"><Input type="date" defaultValue={new Date().toISOString().slice(0, 10)} /></Field>
        </div>
        <Field label="Date de récupération"><Input type="date" /></Field>
        <AttachmentsField files={pieces} onChange={setPieces} />
      </div>
    </DrawerShell>
  );
}

function ReevaluationDrawer({ open, onOpenChange, onSave }: { open: boolean; onOpenChange: (v: boolean) => void; onSave: () => void }) {
  const [pieces, setPieces] = useState<Piece[]>([]);
  return (
    <DrawerShell open={open} onOpenChange={onOpenChange} title="Nouvelle réévaluation" subtitle="Enregistrer une nouvelle réévaluation" icon={FileCheck2} onCancel={() => onOpenChange(false)} onSave={onSave}>
      <div className="space-y-5">
        <div>
          <p className="mb-3 text-sm font-semibold">7. Réévaluations</p>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Valeur actuelle"><Input value="25 000 000 FCFA" readOnly /></Field>
            <Field label="Nouvelle valeur *"><Input type="number" placeholder="FCFA" /></Field>
          </div>
          <div className="mt-3 grid grid-cols-2 gap-3">
            <Field label="Méthode d'évaluation *">
              <Select><SelectTrigger><SelectValue placeholder="Sélectionner…" /></SelectTrigger>
                <SelectContent>{["Comparable", "Coût de remplacement", "DCF"].map((m) => <SelectItem key={m} value={m}>{m}</SelectItem>)}</SelectContent>
              </Select>
            </Field>
            <Field label="Expert / Responsable *">
              <Select><SelectTrigger><SelectValue placeholder="Sélectionner…" /></SelectTrigger>
                <SelectContent>{["M. NDONGO Paul", "Mme MBALLA Rose"].map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}</SelectContent>
              </Select>
            </Field>
          </div>
        </div>
        <div>
          <p className="mb-3 text-sm font-semibold">Détails de la réévaluation</p>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Date de réévaluation *"><Input type="date" defaultValue={new Date().toISOString().slice(0, 10)} /></Field>
            <Field label="Motif *"><Input placeholder="Le motif…" /></Field>
          </div>
          <Field label="Observations" className="mt-3"><Textarea rows={2} placeholder="Observations sur la réévaluation…" /></Field>
        </div>
        <AttachmentsField files={pieces} onChange={setPieces} />
      </div>
    </DrawerShell>
  );
}

function DepreciationDrawer({ open, onOpenChange, onSave }: { open: boolean; onOpenChange: (v: boolean) => void; onSave: () => void }) {
  const [pieces, setPieces] = useState<Piece[]>([]);
  return (
    <DrawerShell open={open} onOpenChange={onOpenChange} title="Nouvelle dépréciation" subtitle="Enregistrer une nouvelle dépréciation" icon={FileCheck2} onCancel={() => onOpenChange(false)} onSave={onSave}>
      <div className="space-y-5">
        <div>
          <p className="mb-3 text-sm font-semibold">Informations de dépréciation</p>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Type de dépréciation *">
              <Select><SelectTrigger><SelectValue placeholder="Sélectionner…" /></SelectTrigger>
                <SelectContent>{["Linéaire", "Dégressif", "Progressif"].map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}</SelectContent>
              </Select>
            </Field>
            <Field label="Méthode d'amortissement *">
              <Select><SelectTrigger><SelectValue placeholder="Sélectionner…" /></SelectTrigger>
                <SelectContent>{["Linéaire", "Dégressif"].map((e) => <SelectItem key={e} value={e}>{e}</SelectItem>)}</SelectContent>
              </Select>
            </Field>
            <Field label="Durée de vie (années) *"><Input type="number" placeholder="0" /></Field>
            <Field label="Valeur actuelle (FCFA)"><Input type="number" placeholder="0" /></Field>
            <Field label="Taux de dépréciation (%)"><Input type="number" placeholder="0" /></Field>
            <Field label="Montant de dépréciation (FCFA)"><Input type="number" placeholder="0" /></Field>
          </div>
        </div>
        <div>
          <p className="mb-3 text-sm font-semibold">Détails</p>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Date de dépréciation *"><Input type="date" defaultValue={new Date().toISOString().slice(0, 10)} /></Field>
            <Field label="Motif *"><Input placeholder="Sélectionner le motif…" /></Field>
          </div>
          <Field label="Observations" className="mt-3"><Textarea rows={2} placeholder="Observations sur la dépréciation…" /></Field>
        </div>
        <AttachmentsField files={pieces} onChange={setPieces} />
      </div>
    </DrawerShell>
  );
}