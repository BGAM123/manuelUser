import { useMemo, useState } from "react";
import {
  Plus,
  Eye,
  Pencil,
  Trash2,
  Save,
  Link2,
  ArrowRightLeft,
  History,
  BarChart3,
  FolderKanban,
  CheckSquare,
  Square,
} from "lucide-react";
import { toast } from "sonner";
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
  PieChart,
  Pie,
  Cell,
  Legend,
  LineChart,
  Line,
} from "recharts";
import { AppShell } from "@/components/shared/AppShell";
import { ViewShell } from "@/components/shared/ViewShell";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { ExportButton } from "@/components/shared/ExportButton";
import { StatCard } from "@/components/shared/StatCard";
import { StatusBadge, statutTone } from "@/components/shared/StatusBadge";
import { useViewStack } from "@/hooks/useViewStack";
import { useT } from "@/utils/i18n";
import {
  mockProjets,
  mockProjetBiens,
  mockMouvementsProjet,
  mockHistoriqueProjet,
  mockBiens,
  formatFCFA,
  formatAmount,
  type Projet,
  type ProjetBien,
  type MouvementProjet,
  type MouvementProjetType,
  type HistoriqueProjet,
  type Bien,
} from "@/utils/mock-data";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

type View =
  | "liste"
  | "detail"
  | "creation"
  | "edition"
  | "affectation"
  | "mouvements"
  | "historique"
  | "statistiques";

const CURRENT_USER = "M. NDONGO Paul";
const STATUTS: Projet["statut"][] = ["Planifié", "En cours", "Suspendu", "Clôturé"];
const MOUVEMENT_TYPES: MouvementProjetType[] = [
  "Entrée",
  "Sortie",
  "Transfert",
  "Retour stock",
  "Réforme",
  "Perte",
  "Vol",
  "Destruction",
];
const CHART_COLORS = ["#2e7d32", "#0288d1", "#ed6c02", "#9c27b0", "#c62828", "#00838f"];

function nowIso() {
  return new Date().toISOString().slice(0, 19);
}

function ProjetsShell() {
  const t = useT();
  const { view, go, back, reset } = useViewStack<View>("liste");
  const [projets, setProjets] = useState<Projet[]>(mockProjets);
  const [projetBiens, setProjetBiens] = useState<ProjetBien[]>(mockProjetBiens);
  const [mouvements, setMouvements] = useState<MouvementProjet[]>(mockMouvementsProjet);
  const [historique, setHistorique] = useState<HistoriqueProjet[]>(mockHistoriqueProjet);
  const [selected, setSelected] = useState<Projet | null>(null);

  const pushHistorique = (projetId: string, action: string, oldV: string, newV: string) => {
    setHistorique((prev) => [
      {
        id: `HP-${projetId}-${Date.now()}`,
        projetId,
        date: nowIso(),
        utilisateur: CURRENT_USER,
        action,
        ancienneValeur: oldV,
        nouvelleValeur: newV,
      },
      ...prev,
    ]);
  };

  const countBiens = (id: string) =>
    projetBiens.filter((pb) => pb.projetId === id && pb.actif).length;

  const valeurProjet = (id: string) =>
    projetBiens
      .filter((pb) => pb.projetId === id && pb.actif)
      .reduce((acc, pb) => {
        const b = mockBiens.find((x) => x.id === pb.bienId);
        return acc + (b?.valeurAcquisition ?? 0);
      }, 0);

  const columns: Column<Projet>[] = [
    { key: "code", label: "Code", className: "font-mono text-xs" },
    {
      key: "intitule",
      label: "Intitulé",
      render: (r) => <span className="font-medium">{r.intitule}</span>,
    },
    { key: "responsable", label: "Responsable" },
    { key: "dateDebut", label: "Début" },
    { key: "dateFin", label: "Fin" },
    {
      key: "biens",
      label: "Biens",
      className: "text-right",
      render: (r) => <span className="tabular-nums">{countBiens(r.id)}</span>,
      sortValue: (r) => countBiens(r.id),
      exportFormat: (r) => countBiens(r.id),
    },
    {
      key: "valeur",
      label: "Valeur patrimoine (FCFA)",
      className: "text-right",
      render: (r) => <span className="tabular-nums">{formatAmount(valeurProjet(r.id))}</span>,
      sortValue: (r) => valeurProjet(r.id),
      exportFormat: (r) => formatFCFA(valeurProjet(r.id)),
    },
    {
      key: "statut",
      label: "Statut",
      render: (r) => <StatusBadge tone={statutTone(r.statut)}>{r.statut}</StatusBadge>,
    },
  ];

  const openDetail = (p: Projet) => {
    setSelected(p);
    go("detail");
  };

  return (
    <AppShell>
      {view === "liste" && (
        <ViewShell
          title="Gestion des projets"
          subtitle="Suivi patrimonial des projets du MINEPIA"
          actions={
            <Button
              onClick={() => {
                setSelected(null);
                go("creation");
              }}
              className="gap-2"
            >
              <Plus className="h-4 w-4" /> Créer un projet
            </Button>
          }
        >
          <DataTable
            data={projets}
            columns={columns}
            getRowId={(p) => p.id}
            exportFilename="projets-minepia"
            exportTitle="MINEPIA — Projets"
            searchKeys={["code", "intitule", "responsable", "structure"]}
            rowActions={(p) => (
              <>
                <RowIconButton icon={Eye} label={t("action.view")} tone="primary" onClick={() => openDetail(p)} />
                <RowIconButton
                  icon={Pencil}
                  label={t("action.edit")}
                  onClick={() => {
                    setSelected(p);
                    go("edition");
                  }}
                />
                <RowIconButton
                  icon={Link2}
                  label="Affecter des biens"
                  onClick={() => {
                    setSelected(p);
                    go("affectation");
                  }}
                />
                <RowIconButton
                  icon={ArrowRightLeft}
                  label="Mouvements"
                  onClick={() => {
                    setSelected(p);
                    go("mouvements");
                  }}
                />
                <RowIconButton
                  icon={History}
                  label="Historique"
                  onClick={() => {
                    setSelected(p);
                    go("historique");
                  }}
                />
                <RowIconButton
                  icon={Trash2}
                  label={t("action.delete")}
                  tone="danger"
                  onClick={() => {
                    setProjets((prev) => prev.filter((x) => x.id !== p.id));
                    toast.success(t("toast.deleted"));
                  }}
                />
              </>
            )}
          />
        </ViewShell>
      )}

      {(view === "creation" || view === "edition") && (
        <ProjetForm
          initial={view === "edition" ? selected : null}
          onCancel={back}
          onSave={(p, isNew) => {
            if (isNew) {
              setProjets((prev) => [p, ...prev]);
              pushHistorique(p.id, "Création du projet", "—", p.intitule);
            } else {
              const prev = projets.find((x) => x.id === p.id);
              setProjets((list) => list.map((x) => (x.id === p.id ? p : x)));
              if (prev && prev.statut !== p.statut) {
                pushHistorique(p.id, "Changement de statut", prev.statut, p.statut);
              } else {
                pushHistorique(p.id, "Modification du projet", prev?.intitule ?? "", p.intitule);
              }
            }
            toast.success(t("toast.saved"));
            reset("liste");
          }}
        />
      )}

      {view === "detail" && selected && (
        <ProjetDetail
          projet={selected}
          biens={projetBiens.filter((pb) => pb.projetId === selected.id && pb.actif)}
          mouvements={mouvements.filter((m) => m.projetId === selected.id)}
          valeur={valeurProjet(selected.id)}
          onBack={() => reset("liste")}
          onEdit={() => go("edition")}
          onAffect={() => go("affectation")}
          onMouvements={() => go("mouvements")}
          onHistorique={() => go("historique")}
          onStatistiques={() => go("statistiques")}
        />
      )}

      {view === "affectation" && selected && (
        <ProjetAffectation
          projet={selected}
          projetBiens={projetBiens}
          onBack={back}
          onAffecter={(bienIds, commentaire) => {
            const now = nowIso();
            setProjetBiens((prev) => {
              let next = [...prev];
              bienIds.forEach((bid) => {
                // désaffecter tout projet actif précédent pour ce bien
                next = next.map((pb) =>
                  pb.bienId === bid && pb.actif && pb.projetId !== selected.id
                    ? { ...pb, actif: false }
                    : pb
                );
                const existing = next.find(
                  (pb) => pb.bienId === bid && pb.projetId === selected.id
                );
                if (existing) {
                  next = next.map((pb) =>
                    pb === existing ? { ...pb, actif: true } : pb
                  );
                } else {
                  next.push({
                    id: `PB-${selected.id}-${bid}-${Date.now()}`,
                    projetId: selected.id,
                    bienId: bid,
                    dateAffectation: now.slice(0, 10),
                    utilisateur: CURRENT_USER,
                    commentaire,
                    actif: true,
                  });
                }
              });
              return next;
            });
            setMouvements((prev) => [
              ...bienIds.map((bid) => ({
                id: `MP-${selected.id}-${bid}-${Date.now()}`,
                projetId: selected.id,
                bienId: bid,
                type: "Entrée" as MouvementProjetType,
                date: now.slice(0, 10),
                utilisateur: CURRENT_USER,
                observation: commentaire || "Affectation au projet",
              })),
              ...prev,
            ]);
            bienIds.forEach((bid) =>
              pushHistorique(selected.id, "Affectation de bien", "—", bid)
            );
            toast.success(`${bienIds.length} bien(s) affecté(s)`);
          }}
          onDesaffecter={(bienId, commentaire) => {
            setProjetBiens((prev) =>
              prev.map((pb) =>
                pb.projetId === selected.id && pb.bienId === bienId
                  ? { ...pb, actif: false }
                  : pb
              )
            );
            setMouvements((prev) => [
              {
                id: `MP-${selected.id}-${bienId}-${Date.now()}`,
                projetId: selected.id,
                bienId,
                type: "Sortie",
                date: nowIso().slice(0, 10),
                utilisateur: CURRENT_USER,
                observation: commentaire || "Désaffectation du projet",
              },
              ...prev,
            ]);
            pushHistorique(selected.id, "Désaffectation de bien", bienId, "—");
            toast.success("Bien désaffecté");
          }}
        />
      )}

      {view === "mouvements" && selected && (
        <ProjetMouvements
          projet={selected}
          mouvements={mouvements.filter((m) => m.projetId === selected.id)}
          onBack={back}
          onAdd={(m) => {
            setMouvements((prev) => [m, ...prev]);
            pushHistorique(selected.id, `Mouvement — ${m.type}`, "—", m.bienId);
            toast.success("Mouvement enregistré");
          }}
        />
      )}

      {view === "historique" && selected && (
        <ProjetHistorique
          projet={selected}
          historique={historique.filter((h) => h.projetId === selected.id)}
          onBack={back}
        />
      )}

      {view === "statistiques" && selected && (
        <ProjetStatistiques
          projet={selected}
          biens={projetBiens.filter((pb) => pb.projetId === selected.id && pb.actif)}
          mouvements={mouvements.filter((m) => m.projetId === selected.id)}
          onBack={back}
        />
      )}
    </AppShell>
  );
}

// -------- Formulaire (création / édition) --------
function ProjetForm({
  initial,
  onCancel,
  onSave,
}: {
  initial: Projet | null;
  onCancel: () => void;
  onSave: (p: Projet, isNew: boolean) => void;
}) {
  const t = useT();
  const isNew = !initial;
  const [form, setForm] = useState<Projet>(
    initial ?? {
      id: `PRJ-${Date.now().toString().slice(-6)}`,
      code: `PRJ-${new Date().getFullYear()}-NEW`,
      intitule: "",
      description: "",
      responsable: "",
      structure: "",
      dateDebut: new Date().toISOString().slice(0, 10),
      dateFin: new Date().toISOString().slice(0, 10),
      budget: 0,
      statut: "Planifié",
    }
  );
  return (
    <ViewShell
      title={isNew ? "Nouveau projet" : "Modifier le projet"}
      onBack={onCancel}
    >
      <form
        onSubmit={(e) => {
          e.preventDefault();
          onSave(form, isNew);
        }}
        className="rounded-xl border border-border bg-card p-6 shadow-sm"
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label>Code projet</Label>
            <Input value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} required />
          </div>
          <div className="space-y-1.5">
            <Label>Intitulé</Label>
            <Input value={form.intitule} onChange={(e) => setForm({ ...form, intitule: e.target.value })} required />
          </div>
          <div className="space-y-1.5 sm:col-span-2">
            <Label>Description</Label>
            <Textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={3} />
          </div>
          <div className="space-y-1.5">
            <Label>Responsable</Label>
            <Input value={form.responsable} onChange={(e) => setForm({ ...form, responsable: e.target.value })} />
          </div>
          <div className="space-y-1.5">
            <Label>Structure porteuse</Label>
            <Input value={form.structure} onChange={(e) => setForm({ ...form, structure: e.target.value })} />
          </div>
          <div className="space-y-1.5">
            <Label>Date de début</Label>
            <Input type="date" value={form.dateDebut} onChange={(e) => setForm({ ...form, dateDebut: e.target.value })} />
          </div>
          <div className="space-y-1.5">
            <Label>Date de fin</Label>
            <Input type="date" value={form.dateFin} onChange={(e) => setForm({ ...form, dateFin: e.target.value })} />
          </div>
          <div className="space-y-1.5">
            <Label>Budget prévisionnel (FCFA)</Label>
            <Input
              type="number"
              value={form.budget}
              onChange={(e) => setForm({ ...form, budget: Number(e.target.value) })}
            />
          </div>
          <div className="space-y-1.5">
            <Label>Statut</Label>
            <Select
              value={form.statut}
              onValueChange={(v) => setForm({ ...form, statut: v as Projet["statut"] })}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {STATUTS.map((s) => (
                  <SelectItem key={s} value={s}>
                    {s}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
        <div className="mt-6 flex justify-end gap-2 border-t border-border pt-4">
          <Button type="button" variant="outline" onClick={onCancel}>
            {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2">
            <Save className="h-4 w-4" /> {t("action.save")}
          </Button>
        </div>
      </form>
    </ViewShell>
  );
}

// -------- Détail --------
function ProjetDetail({
  projet,
  biens,
  mouvements,
  valeur,
  onBack,
  onEdit,
  onAffect,
  onMouvements,
  onHistorique,
  onStatistiques,
}: {
  projet: Projet;
  biens: ProjetBien[];
  mouvements: MouvementProjet[];
  valeur: number;
  onBack: () => void;
  onEdit: () => void;
  onAffect: () => void;
  onMouvements: () => void;
  onHistorique: () => void;
  onStatistiques: () => void;
}) {
  const t = useT();
  const derniers = mouvements.slice(0, 5);
  const infos: [string, string][] = [
    ["Code", projet.code],
    ["Intitulé", projet.intitule],
    ["Responsable", projet.responsable],
    ["Structure porteuse", projet.structure],
    ["Date de début", projet.dateDebut],
    ["Date de fin", projet.dateFin],
    ["Budget prévisionnel", formatFCFA(projet.budget)],
    ["Statut", projet.statut],
  ];
  return (
    <ViewShell
      title={projet.intitule}
      subtitle={projet.code}
      onBack={onBack}
      actions={
        <>
          <Button variant="outline" onClick={onEdit} className="gap-2">
            <Pencil className="h-4 w-4" /> {t("action.edit")}
          </Button>
          <Button variant="outline" onClick={onAffect} className="gap-2">
            <Link2 className="h-4 w-4" /> Affecter
          </Button>
          <Button variant="outline" onClick={onMouvements} className="gap-2">
            <ArrowRightLeft className="h-4 w-4" /> Mouvements
          </Button>
          <Button variant="outline" onClick={onHistorique} className="gap-2">
            <History className="h-4 w-4" /> Historique
          </Button>
          <Button variant="outline" onClick={onStatistiques} className="gap-2">
            <BarChart3 className="h-4 w-4" /> Statistiques
          </Button>
        </>
      }
    >
      <div className="grid gap-4 lg:grid-cols-3">
        <StatCard label="Biens affectés" value={String(biens.length)} icon={FolderKanban} tone="primary" />
        <StatCard label="Valeur patrimoniale" value={formatFCFA(valeur)} icon={BarChart3} tone="secondary" />
        <StatCard label="Mouvements" value={String(mouvements.length)} icon={ArrowRightLeft} tone="low" />
      </div>

      <div className="mt-6 grid gap-4 lg:grid-cols-2">
        <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
          <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
            Informations générales
          </h3>
          <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
            {infos.map(([k, v]) => (
              <div key={k} className="border-b border-border/60 pb-2">
                <dt className="text-xs uppercase tracking-wide text-muted-foreground">{k}</dt>
                <dd className="mt-0.5 text-sm font-medium">{v}</dd>
              </div>
            ))}
            <div className="border-b border-border/60 pb-2 sm:col-span-2">
              <dt className="text-xs uppercase tracking-wide text-muted-foreground">Description</dt>
              <dd className="mt-0.5 text-sm">{projet.description || "—"}</dd>
            </div>
          </dl>
        </div>

        <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
          <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
            Derniers mouvements
          </h3>
          {derniers.length === 0 ? (
            <p className="text-sm text-muted-foreground">Aucun mouvement enregistré.</p>
          ) : (
            <ul className="space-y-3">
              {derniers.map((m) => {
                const bien = mockBiens.find((b) => b.id === m.bienId);
                return (
                  <li key={m.id} className="flex items-start justify-between gap-3 border-b border-border/60 pb-2">
                    <div className="min-w-0">
                      <p className="truncate text-sm font-medium">{bien?.designation ?? m.bienId}</p>
                      <p className="text-xs text-muted-foreground">{m.observation}</p>
                    </div>
                    <div className="text-right">
                      <StatusBadge tone="info">{m.type}</StatusBadge>
                      <p className="mt-1 text-[11px] text-muted-foreground">{m.date}</p>
                    </div>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      </div>

      <div className="mt-6">
        <BiensAffectesTable projetBiens={biens} />
      </div>
    </ViewShell>
  );
}

function BiensAffectesTable({ projetBiens }: { projetBiens: ProjetBien[] }) {
  type Row = ProjetBien & { bien: Bien | undefined };
  const rows: Row[] = projetBiens.map((pb) => ({
    ...pb,
    bien: mockBiens.find((b) => b.id === pb.bienId),
  }));
  const cols: Column<Row>[] = [
    { key: "bienId", label: "N° bien", className: "font-mono text-xs" },
    {
      key: "designation",
      label: "Désignation",
      render: (r) => <span className="font-medium">{r.bien?.designation ?? "—"}</span>,
      sortValue: (r) => r.bien?.designation ?? "",
      exportFormat: (r) => r.bien?.designation ?? "",
    },
    {
      key: "categorie",
      label: "Catégorie",
      render: (r) => r.bien?.categorie ?? "—",
      exportFormat: (r) => r.bien?.categorie ?? "",
    },
    {
      key: "valeur",
      label: "Valeur (FCFA)",
      className: "text-right",
      render: (r) => (
        <span className="tabular-nums">{formatFCFA(r.bien?.valeurAcquisition ?? 0)}</span>
      ),
      sortValue: (r) => r.bien?.valeurAcquisition ?? 0,
      exportFormat: (r) => formatFCFA(r.bien?.valeurAcquisition ?? 0),
    },
    { key: "dateAffectation", label: "Affecté le" },
    { key: "utilisateur", label: "Par" },
  ];
  return (
    <DataTable
      data={rows}
      columns={cols}
      getRowId={(r) => r.id}
      exportFilename="biens-projet"
      exportTitle="Biens affectés au projet"
      searchKeys={["bienId", "utilisateur", "commentaire"]}
    />
  );
}

// -------- Affectation --------
function ProjetAffectation({
  projet,
  projetBiens,
  onBack,
  onAffecter,
  onDesaffecter,
}: {
  projet: Projet;
  projetBiens: ProjetBien[];
  onBack: () => void;
  onAffecter: (bienIds: string[], commentaire: string) => void;
  onDesaffecter: (bienId: string, commentaire: string) => void;
}) {
  const [query, setQuery] = useState("");
  const [selection, setSelection] = useState<Set<string>>(new Set());
  const [commentaire, setCommentaire] = useState("");
  const affectedIds = useMemo(
    () => new Set(projetBiens.filter((pb) => pb.projetId === projet.id && pb.actif).map((pb) => pb.bienId)),
    [projetBiens, projet.id]
  );
  const q = query.toLowerCase();
  const filtered = mockBiens.filter(
    (b) =>
      !q ||
      b.designation.toLowerCase().includes(q) ||
      b.numero.toLowerCase().includes(q) ||
      b.categorie.toLowerCase().includes(q)
  );
  const toggle = (id: string) => {
    setSelection((prev) => {
      const n = new Set(prev);
      if (n.has(id)) n.delete(id);
      else n.add(id);
      return n;
    });
  };

  return (
    <ViewShell title="Affectation de biens" subtitle={projet.intitule} onBack={onBack}>
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm lg:col-span-2">
          <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
            <Input
              placeholder="Rechercher un bien (désignation, numéro, catégorie)…"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              className="sm:max-w-md"
            />
            <div className="text-xs text-muted-foreground">
              {selection.size} sélectionné(s) · {filtered.length} résultats
            </div>
          </div>
          <div className="max-h-[520px] overflow-y-auto rounded-lg border border-border">
            <table className="w-full text-sm">
              <thead className="sticky top-0 bg-muted/60 text-xs uppercase text-muted-foreground">
                <tr>
                  <th className="w-10 px-3 py-2"></th>
                  <th className="px-3 py-2 text-left">Désignation</th>
                  <th className="px-3 py-2 text-left">N°</th>
                  <th className="px-3 py-2 text-left">Catégorie</th>
                  <th className="px-3 py-2 text-left">État</th>
                  <th className="px-3 py-2 text-left">Affectation</th>
                </tr>
              </thead>
              <tbody>
                {filtered.slice(0, 100).map((b) => {
                  const isAffected = affectedIds.has(b.id);
                  const isSelected = selection.has(b.id);
                  return (
                    <tr key={b.id} className="border-t border-border hover:bg-accent/40">
                      <td className="px-3 py-2">
                        <button
                          type="button"
                          onClick={() => toggle(b.id)}
                          className="inline-flex h-6 w-6 items-center justify-center text-muted-foreground hover:text-primary"
                          aria-label={isSelected ? "Désélectionner" : "Sélectionner"}
                        >
                          {isSelected ? (
                            <CheckSquare className="h-4 w-4 text-primary" />
                          ) : (
                            <Square className="h-4 w-4" />
                          )}
                        </button>
                      </td>
                      <td className="px-3 py-2 font-medium">{b.designation}</td>
                      <td className="px-3 py-2 font-mono text-xs">{b.numero}</td>
                      <td className="px-3 py-2">{b.categorie}</td>
                      <td className="px-3 py-2">{b.etat}</td>
                      <td className="px-3 py-2">
                        {isAffected ? (
                          <StatusBadge tone="normal">Affecté</StatusBadge>
                        ) : (
                          <StatusBadge tone="muted">Libre</StatusBadge>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>

        <div className="space-y-4">
          <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h3 className="mb-3 text-sm font-semibold">Commentaire</h3>
            <Textarea
              rows={3}
              value={commentaire}
              onChange={(e) => setCommentaire(e.target.value)}
              placeholder="Motif de l'affectation / désaffectation"
            />
            <div className="mt-3 flex flex-col gap-2">
              <Button
                className="gap-2"
                disabled={selection.size === 0}
                onClick={() => {
                  onAffecter(Array.from(selection), commentaire);
                  setSelection(new Set());
                  setCommentaire("");
                }}
              >
                <Link2 className="h-4 w-4" /> Affecter au projet
              </Button>
            </div>
          </div>

          <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h3 className="mb-3 text-sm font-semibold">Biens déjà affectés</h3>
            {affectedIds.size === 0 ? (
              <p className="text-xs text-muted-foreground">Aucun bien affecté.</p>
            ) : (
              <ul className="space-y-2 text-sm">
                {Array.from(affectedIds).map((bid) => {
                  const b = mockBiens.find((x) => x.id === bid);
                  return (
                    <li key={bid} className="flex items-center justify-between gap-2 border-b border-border/60 pb-1.5">
                      <span className="truncate">{b?.designation ?? bid}</span>
                      <button
                        type="button"
                        onClick={() => onDesaffecter(bid, commentaire)}
                        className="text-xs font-medium text-destructive hover:underline"
                      >
                        Retirer
                      </button>
                    </li>
                  );
                })}
              </ul>
            )}
          </div>
        </div>
      </div>
    </ViewShell>
  );
}

// -------- Mouvements --------
function ProjetMouvements({
  projet,
  mouvements,
  onBack,
  onAdd,
}: {
  projet: Projet;
  mouvements: MouvementProjet[];
  onBack: () => void;
  onAdd: (m: MouvementProjet) => void;
}) {
  const [typeFilter, setTypeFilter] = useState<string>("all");
  const [catFilter, setCatFilter] = useState<string>("all");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [showForm, setShowForm] = useState(false);

  const filtered = mouvements.filter((m) => {
    if (typeFilter !== "all" && m.type !== typeFilter) return false;
    if (from && m.date < from) return false;
    if (to && m.date > to) return false;
    if (catFilter !== "all") {
      const b = mockBiens.find((x) => x.id === m.bienId);
      if (b?.categorie !== catFilter) return false;
    }
    return true;
  });

  const cols: Column<MouvementProjet>[] = [
    { key: "date", label: "Date" },
    {
      key: "bienId",
      label: "Bien",
      render: (r) => {
        const b = mockBiens.find((x) => x.id === r.bienId);
        return <span className="font-medium">{b?.designation ?? r.bienId}</span>;
      },
      sortValue: (r) => mockBiens.find((x) => x.id === r.bienId)?.designation ?? r.bienId,
      exportFormat: (r) => mockBiens.find((x) => x.id === r.bienId)?.designation ?? r.bienId,
    },
    {
      key: "type",
      label: "Type",
      render: (r) => <StatusBadge tone={mouvementTone(r.type)}>{r.type}</StatusBadge>,
    },
    { key: "utilisateur", label: "Utilisateur" },
    { key: "observation", label: "Observation" },
  ];

  return (
    <ViewShell
      title="Mouvements du projet"
      subtitle={projet.intitule}
      onBack={onBack}
      actions={
        <Button className="gap-2" onClick={() => setShowForm((v) => !v)}>
          <Plus className="h-4 w-4" /> Nouveau mouvement
        </Button>
      }
    >
      {showForm && (
        <MouvementForm
          projetId={projet.id}
          onCancel={() => setShowForm(false)}
          onSubmit={(m) => {
            onAdd(m);
            setShowForm(false);
          }}
        />
      )}

      <div className="mb-4 grid grid-cols-2 gap-3 rounded-xl border border-border bg-card p-3 shadow-sm sm:flex sm:flex-wrap sm:p-4">
        <div className="space-y-1 sm:w-44">
          <Label className="text-xs">Type</Label>
          <Select value={typeFilter} onValueChange={setTypeFilter}>
            <SelectTrigger className="h-9">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Tous</SelectItem>
              {MOUVEMENT_TYPES.map((tp) => (
                <SelectItem key={tp} value={tp}>
                  {tp}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1 sm:w-44">
          <Label className="text-xs">Catégorie</Label>
          <Select value={catFilter} onValueChange={setCatFilter}>
            <SelectTrigger className="h-9">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Toutes</SelectItem>
              {["Immobilier", "Mobilier", "Informatique", "Roulant", "Cheptel"].map((c) => (
                <SelectItem key={c} value={c}>
                  {c}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1 sm:w-40">
          <Label className="text-xs">Du</Label>
          <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="h-9" />
        </div>
        <div className="space-y-1 sm:w-40">
          <Label className="text-xs">Au</Label>
          <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="h-9" />
        </div>
        <div className="col-span-2 flex items-end sm:col-span-1">
          <Button
            variant="outline"
            className="w-full sm:w-auto"
            onClick={() => {
              setTypeFilter("all");
              setCatFilter("all");
              setFrom("");
              setTo("");
            }}
          >
            Réinitialiser
          </Button>
        </div>
      </div>

      <DataTable
        data={filtered}
        columns={cols}
        getRowId={(r) => r.id}
        exportFilename={`mouvements-${projet.code}`}
        exportTitle={`Mouvements — ${projet.intitule}`}
        searchKeys={["type", "utilisateur", "observation"]}
      />
    </ViewShell>
  );
}

function mouvementTone(type: MouvementProjetType) {
  switch (type) {
    case "Entrée":
      return "normal" as const;
    case "Sortie":
    case "Transfert":
    case "Retour stock":
      return "info" as const;
    default:
      return "urgent" as const;
  }
}

function MouvementForm({
  projetId,
  onCancel,
  onSubmit,
}: {
  projetId: string;
  onCancel: () => void;
  onSubmit: (m: MouvementProjet) => void;
}) {
  const [bienId, setBienId] = useState(mockBiens[0]?.id ?? "");
  const [type, setType] = useState<MouvementProjetType>("Entrée");
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [observation, setObservation] = useState("");
  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        onSubmit({
          id: `MP-${projetId}-${Date.now()}`,
          projetId,
          bienId,
          type,
          date,
          utilisateur: CURRENT_USER,
          observation,
        });
      }}
      className="mb-4 rounded-xl border border-border bg-card p-4 shadow-sm"
    >
      <div className="grid gap-3 sm:grid-cols-4">
        <div className="space-y-1.5 sm:col-span-2">
          <Label className="text-xs">Bien</Label>
          <Select value={bienId} onValueChange={setBienId}>
            <SelectTrigger className="h-9">
              <SelectValue />
            </SelectTrigger>
            <SelectContent className="max-h-64">
              {mockBiens.slice(0, 60).map((b) => (
                <SelectItem key={b.id} value={b.id}>
                  {b.designation}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label className="text-xs">Type</Label>
          <Select value={type} onValueChange={(v) => setType(v as MouvementProjetType)}>
            <SelectTrigger className="h-9">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {MOUVEMENT_TYPES.map((tp) => (
                <SelectItem key={tp} value={tp}>
                  {tp}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label className="text-xs">Date</Label>
          <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="h-9" />
        </div>
        <div className="space-y-1.5 sm:col-span-4">
          <Label className="text-xs">Observation</Label>
          <Input value={observation} onChange={(e) => setObservation(e.target.value)} />
        </div>
      </div>
      <div className="mt-3 flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          Annuler
        </Button>
        <Button type="submit" className="gap-2">
          <Save className="h-4 w-4" /> Enregistrer
        </Button>
      </div>
    </form>
  );
}

// -------- Historique --------
function ProjetHistorique({
  projet,
  historique,
  onBack,
}: {
  projet: Projet;
  historique: HistoriqueProjet[];
  onBack: () => void;
}) {
  const cols: Column<HistoriqueProjet>[] = [
    { key: "date", label: "Date & heure" },
    { key: "utilisateur", label: "Utilisateur" },
    { key: "action", label: "Action", render: (r) => <span className="font-medium">{r.action}</span> },
    { key: "ancienneValeur", label: "Ancienne valeur" },
    { key: "nouvelleValeur", label: "Nouvelle valeur" },
  ];
  return (
    <ViewShell title="Historique du projet" subtitle={projet.intitule} onBack={onBack}>
      <DataTable
        data={historique}
        columns={cols}
        getRowId={(r) => r.id}
        exportFilename={`historique-${projet.code}`}
        exportTitle={`Historique — ${projet.intitule}`}
        searchKeys={["action", "utilisateur", "ancienneValeur", "nouvelleValeur"]}
      />
    </ViewShell>
  );
}

// -------- Statistiques --------
function ProjetStatistiques({
  projet,
  biens,
  mouvements,
  onBack,
}: {
  projet: Projet;
  biens: ProjetBien[];
  mouvements: MouvementProjet[];
  onBack: () => void;
}) {
  const enriched = biens.map((pb) => mockBiens.find((b) => b.id === pb.bienId)).filter(Boolean) as Bien[];
  const valeurTotale = enriched.reduce((a, b) => a + b.valeurAcquisition, 0);
  const entrees = mouvements.filter((m) => m.type === "Entrée").length;
  const sorties = mouvements.filter((m) => m.type !== "Entrée").length;
  const aReformer = enriched.filter((b) => b.statut === "À réformer" || b.statut === "Réformé").length;

  const parCategorie = Object.entries(
    enriched.reduce<Record<string, number>>((acc, b) => {
      acc[b.categorie] = (acc[b.categorie] ?? 0) + 1;
      return acc;
    }, {})
  ).map(([name, value]) => ({ name, value }));

  const parEtat = Object.entries(
    enriched.reduce<Record<string, number>>((acc, b) => {
      acc[b.etat] = (acc[b.etat] ?? 0) + 1;
      return acc;
    }, {})
  ).map(([name, value]) => ({ name, value }));

  // évolution mensuelle des mouvements
  const parMois: Record<string, number> = {};
  mouvements.forEach((m) => {
    const key = m.date.slice(0, 7);
    parMois[key] = (parMois[key] ?? 0) + 1;
  });
  const evolution = Object.entries(parMois)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([mois, count]) => ({ mois, count }));

  const exportRows = [
    { indicateur: "Biens affectés", valeur: enriched.length },
    { indicateur: "Valeur patrimoniale (FCFA)", valeur: valeurTotale },
    { indicateur: "Mouvements d'entrée", valeur: entrees },
    { indicateur: "Mouvements de sortie", valeur: sorties },
    { indicateur: "Biens à réformer", valeur: aReformer },
  ];

  return (
    <ViewShell
      title="Statistiques du projet"
      subtitle={projet.intitule}
      onBack={onBack}
      actions={
        <ExportButton
          data={exportRows}
          columns={[
            { key: "indicateur", label: "Indicateur" },
            { key: "valeur", label: "Valeur" },
          ]}
          filename={`statistiques-${projet.code}`}
          title={`Statistiques — ${projet.intitule}`}
        />
      }
    >
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Biens affectés" value={String(enriched.length)} icon={FolderKanban} tone="primary" />
        <StatCard label="Valeur patrimoniale" value={formatFCFA(valeurTotale)} icon={BarChart3} tone="secondary" />
        <StatCard label="Entrées / Sorties" value={`${entrees} / ${sorties}`} icon={ArrowRightLeft} tone="low" />
        <StatCard label="Biens à réformer" value={String(aReformer)} icon={History} tone="urgent" />
      </div>

      <div className="mt-6 grid gap-4 lg:grid-cols-2">
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <h3 className="mb-3 text-sm font-semibold">Répartition par catégorie</h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={parCategorie}>
                <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                <XAxis dataKey="name" fontSize={11} />
                <YAxis fontSize={11} allowDecimals={false} />
                <Tooltip />
                <Bar dataKey="value" fill="#2e7d32" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <h3 className="mb-3 text-sm font-semibold">Répartition par état</h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie data={parEtat} dataKey="value" nameKey="name" outerRadius={90} label>
                  {parEtat.map((_, i) => (
                    <Cell key={i} fill={CHART_COLORS[i % CHART_COLORS.length]} />
                  ))}
                </Pie>
                <Legend />
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="rounded-xl border border-border bg-card p-4 shadow-sm lg:col-span-2">
          <h3 className="mb-3 text-sm font-semibold">Évolution des mouvements</h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={evolution}>
                <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                <XAxis dataKey="mois" fontSize={11} />
                <YAxis fontSize={11} allowDecimals={false} />
                <Tooltip />
                <Line type="monotone" dataKey="count" stroke="#0288d1" strokeWidth={2} dot={{ r: 3 }} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>
    </ViewShell>
  );
}

export default ProjetsShell;