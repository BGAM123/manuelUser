/**
 * Section de configuration — Gestion des projets patrimoniaux.
 * CRUD complet avec gestion des responsables (multi-select), statuts colorés,
 * archivage logique et suppression physique.
 */

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Plus,
  Pencil,
  Trash2,
  Save,
  X,
  ArrowLeft,
  Loader2,
  Briefcase,
  Archive,
  RotateCcw,
  ChevronDown,
  Check,
} from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import { YearStepper } from "@/components/shared/YearStepper";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { useT } from "@/utils/i18n";
import {
  listProjects,
  createProject,
  updateProject,
  deleteProject,
  softDeleteProject,
  restoreProject,
  updateProjectStatus,
  type ApiProject,
  type ProjectStatut,
  type CreateProjectPayload,
  type UpdateProjectPayload,
} from "@/api/projects/projects.api";

// ─── Config statut ──────────────────────────────────────────────────────────

const STATUT_CONFIG: Record<ProjectStatut, { className: string }> = {
  PLANIFIE: {
    className:
      "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
  },
  EN_COURS: {
    className:
      "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
  },
  TERMINE: {
    className:
      "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400",
  },
};

// ─── Section principale ─────────────────────────────────────────────────────

export function ProjetsSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<"liste" | "form">("liste");
  const [selected, setSelected] = useState<ApiProject | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<ApiProject | null>(null);
  const [desarchiveTarget, setDesarchiveTarget] = useState<ApiProject | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiProject | null>(null);
  const [showArchived, setShowArchived] = useState(false);
  const [exerciceFilter, setExerciceFilter] = useState(new Date().getFullYear());

  const STATUT_LABELS: Record<ProjectStatut, string> = {
    PLANIFIE: t("projects.status.planifie"),
    EN_COURS: t("projects.status.en_cours"),
    TERMINE: t("projects.status.termine"),
  };

  const { data, isLoading, isError } = useQuery({
    queryKey: ["projects", showArchived, exerciceFilter],
    queryFn: () =>
      listProjects({
        page: 1,
        limit: 1000,
        exercice: exerciceFilter,
        is_delete: showArchived,
      }),
  });

  const projects = data?.data ?? [];

  const statusMutation = useMutation({
    mutationFn: ({
      id,
      statut,
    }: {
      id: number;
      statut: ProjectStatut;
    }) => updateProjectStatus(id, statut),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["projects"] }),
    onError: () => toast.error(t("toast.error")),
  });

  const archiveMutation = useMutation({
    mutationFn: (id: number) => softDeleteProject(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["projects"] });
      toast.success(t("toast.deleted"));
      setArchiveTarget(null);
    },
    onError: () => toast.error(t("toast.error")),
  });

  const restoreMutation = useMutation({
    mutationFn: (id: number) => restoreProject(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["projects"] });
      toast.success(t("toast.saved"));
      setDesarchiveTarget(null);
    },
    onError: () => toast.error(t("toast.error")),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteProject(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["projects"] });
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
    },
    onError: () => toast.error(t("toast.error")),
  });

  const columns: Column<ApiProject>[] = [
    {
      key: "nom",
      label: t("projects.field.nom"),
      render: (p) => <span className="font-semibold">{p.nom}</span>,
      sortValue: (p) => p.nom,
    },
    {
      key: "statut",
      label: t("projects.field.statut"),
      render: (p) => {
        const cfg = STATUT_CONFIG[p.statut];
        return (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                className={`inline-flex cursor-pointer items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium transition-opacity hover:opacity-75 focus:outline-none ${cfg.className}`}
              >
                {STATUT_LABELS[p.statut]}
                <ChevronDown className="h-3 w-3 opacity-60" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-36">
              {(["PLANIFIE", "EN_COURS", "TERMINE"] as ProjectStatut[]).map((s) => (
                <DropdownMenuItem
                  key={s}
                  onSelect={() => {
                    if (s !== p.statut)
                      statusMutation.mutate({ id: p.id, statut: s });
                  }}
                  className="flex items-center justify-between gap-2 text-xs"
                >
                  <span
                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUT_CONFIG[s].className}`}
                  >
                    {STATUT_LABELS[s]}
                  </span>
                  {s === p.statut && <Check className="h-3.5 w-3.5 text-primary" />}
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>
        );
      },
      exportFormat: (p) => STATUT_LABELS[p.statut],
    },
    {
      key: "exercice",
      label: t("stats.filter.exercice"),
      render: (p) => <span className="text-xs tabular-nums">{p.exercice || "—"}</span>,
      sortValue: (p) => Number(p.exercice) || 0,
    },
    {
      key: "dateDebut",
      label: t("projects.field.debut"),
      render: (p) => <span className="text-xs tabular-nums">{p.dateDebut || "—"}</span>,
      sortValue: (p) => p.dateDebut,
    },
    {
      key: "date_fin",
      label: t("projects.field.fin"),
      render: (p) => (
        <span className="text-xs tabular-nums">
          {p.dateFinPrevue || "—"}
        </span>
      ),
      sortValue: (p) => p.dateFinPrevue ?? "",
    },
  ];

  if (isLoading) {
    return (
      <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin" />
        <span>{t("common.loading")}</span>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="flex h-40 items-center justify-center text-destructive">
        {t("toast.error")}
      </div>
    );
  }

  if (view === "form") {
    return (
      <ProjectForm
        initial={selected}
        onCancel={() => setView("liste")}
        onSaved={() => {
          queryClient.invalidateQueries({ queryKey: ["projects"] });
          toast.success(t("toast.saved"));
          setView("liste");
        }}
      />
    );
  }

  return (
    <>
      <div>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-lg font-semibold">{t("admin.fundingSource.plural")}</h2>
          <div className="flex items-center gap-3">
            <label className="flex cursor-pointer items-center gap-2 text-sm">
              <Switch checked={showArchived} onCheckedChange={setShowArchived} />
              {t("categories.showArchived")}
            </label>
            <Button
              className="gap-2"
              onClick={() => {
                setSelected(null);
                setView("form");
              }}
            >
              <Plus className="h-4 w-4" /> {t("admin.fundingSource.create")}
            </Button>
          </div>
        </div>
        <DataTable
          data={projects}
          columns={columns}
          getRowId={(p) => String(p.id)}
          exportFilename="sources-financement-minepia"
          exportTitle={`MINEPIA — ${t("admin.fundingSource.plural")}`}
          searchKeys={["nom", "description", "exercice"]}
          toolbar={(
            <div className="w-full sm:w-32">
              <YearStepper value={exerciceFilter} onChange={setExerciceFilter} />
            </div>
          )}
          rowActions={(p) => (
            <>
              {!showArchived && (
                <RowIconButton
                  icon={Pencil}
                  label={t("action.edit")}
                  onClick={() => {
                    setSelected(p);
                    setView("form");
                  }}
                />
              )}
              {showArchived ? (
                <RowIconButton
                  icon={RotateCcw}
                  label={t("action.restore")}
                  onClick={() => setDesarchiveTarget(p)}
                />
              ) : (
                <RowIconButton
                  icon={Archive}
                  label={t("projects.archive.title")}
                  onClick={() => setArchiveTarget(p)}
                />
              )}
              {showArchived && (
                <RowIconButton
                  icon={Trash2}
                  label={t("common.permanentDelete")}
                  tone="danger"
                  onClick={() => setDeleteTarget(p)}
                />
              )}
            </>
          )}
        />
      </div>

      {/* ── Dialog archivage ── */}
      <AlertDialog
        open={archiveTarget !== null}
        onOpenChange={(open) => {
          if (!open) setArchiveTarget(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("projects.archive.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("projects.archive.desc")}{" "}
              <span className="font-semibold text-foreground">
                {archiveTarget?.nom}
              </span>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-amber-600 text-white hover:bg-amber-700"
              onClick={() => {
                if (archiveTarget) archiveMutation.mutate(archiveTarget.id);
              }}
            >
              <Archive className="mr-2 h-4 w-4" /> {t("action.archive")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* ── Dialog désarchivage ── */}
      <AlertDialog
        open={desarchiveTarget !== null}
        onOpenChange={(open) => {
          if (!open) setDesarchiveTarget(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("projects.restore.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("projects.restore.desc.before")}{" "}
              <span className="font-semibold text-foreground">
                {desarchiveTarget?.nom}
              </span>{" "}
              {t("projects.restore.desc.after")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              disabled={restoreMutation.isPending}
              onClick={() => {
                if (desarchiveTarget) restoreMutation.mutate(desarchiveTarget.id);
              }}
            >
              <RotateCcw className="mr-2 h-4 w-4" /> {t("action.restore")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* ── Dialog suppression définitive ── */}
      <AlertDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => {
          if (!open) setDeleteTarget(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("projects.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("projects.delete.desc")}{" "}
              <span className="font-semibold text-foreground">
                {deleteTarget?.nom}
              </span>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => {
                if (deleteTarget) deleteMutation.mutate(deleteTarget.id);
              }}
            >
              {t("action.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

// ─── Formulaire création / édition ─────────────────────────────────────────

function ProjectForm({
  initial,
  onCancel,
  onSaved,
}: {
  initial: ApiProject | null;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [description, setDescription] = useState(initial?.description ?? "");
  const [exercice, setExercice] = useState(
    String(initial?.exercice ?? new Date().getFullYear()),
  );
  const [dateDebut, setDateDebut] = useState(initial?.dateDebut ?? "");
  const [dateFin, setDateFin] = useState(initial?.dateFinPrevue ?? "");
  const [statut, setStatut] = useState<ProjectStatut>(
    initial?.statut ?? "PLANIFIE",
  );

  const STATUT_LABELS: Record<ProjectStatut, string> = {
    PLANIFIE: t("projects.status.planifie"),
    EN_COURS: t("projects.status.en_cours"),
    TERMINE: t("projects.status.termine"),
  };

  const onMutationError = (err: unknown) => {
    // "La validation a échoué" (data.message) est générique — le détail par
    // champ est ailleurs selon le cas : data.errors[].constraints (array) ou
    // data.data en tant qu'objet { champ: "message" }. On privilégie le
    // détail le plus précis avant de retomber sur le message générique.
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const data = (err as { response?: { data?: any } })?.response?.data;
    const details = Array.isArray(data?.errors)
      ? (data.errors as Array<{ constraints?: Record<string, string> }>)
          .flatMap((e) => Object.values(e.constraints ?? {})).join(" · ")
      : undefined;
    const fieldErrors = data?.data && typeof data.data === "object" && !Array.isArray(data.data)
      ? Object.values(data.data as Record<string, string>).join(" · ")
      : undefined;
    toast.error(fieldErrors || details || data?.message || t("toast.error"));
  };

  const createMutation = useMutation({
    mutationFn: (payload: CreateProjectPayload) => createProject(payload),
    onSuccess: () => onSaved(),
    onError: onMutationError,
  });

  const updateMutation = useMutation({
    mutationFn: async ({
      id,
      payload,
    }: {
      id: number;
      payload: UpdateProjectPayload;
    }) => {
      const result = await updateProject(id, payload);
      return { result, requestedExercice: payload.exercice };
    },
    onSuccess: ({ result, requestedExercice }) => {
      // Le serveur documente lui-même qu'il ignore l'exercice sur cet
      // endpoint (fixé à la création) — on vérifie si c'est vraiment le cas
      // plutôt que de laisser croire silencieusement que ça a marché.
      const savedExercice = result.data?.exercice;
      if (requestedExercice != null && Number(savedExercice) !== Number(requestedExercice)) {
        toast.error(
          t("projects.error.exerciceLocked", {
            exercice: savedExercice != null ? String(savedExercice) : t("common.unchanged"),
          }),
          { duration: 10000 },
        );
      }
      onSaved();
    },
    onError: onMutationError,
  });

  const isPending = createMutation.isPending || updateMutation.isPending;

  const buildPayload = (): CreateProjectPayload => ({
    nom: nom.trim(),
    description: description.trim() || undefined,
    exercice: Number(exercice),
    date_debut: dateDebut,
    date_fin_prevue: dateFin || undefined,
    statut,
    responsable_ids: [],
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!nom.trim() || !dateDebut) return;
    if (!/^\d{4}$/.test(exercice) || Number(exercice) < 2000 || Number(exercice) > 2100) {
      toast.error(t("projects.error.exerciceRange"));
      return;
    }
    if (!initial) {
      createMutation.mutate(buildPayload());
    } else {
      const payload = buildPayload();
      updateMutation.mutate({
        id: initial.id,
        payload: {
          nom: payload.nom,
          description: payload.description,
          exercice: payload.exercice,
          date_debut: payload.date_debut,
          date_fin_prevue: payload.date_fin_prevue,
          responsable_ids: payload.responsable_ids,
        },
      });
    }
  };

  return (
    <div className="mx-auto w-full max-w-3xl space-y-3">
      <Button
        type="button"
        variant="ghost"
        size="sm"
        onClick={onCancel}
        className="-ml-2 gap-1"
      >
        <ArrowLeft className="h-4 w-4" />
        {t("action.back")}
      </Button>
      <form
        onSubmit={handleSubmit}
        className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
      >
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Briefcase className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {initial ? `${t("action.edit")} — ${initial.nom}` : t("admin.fundingSource.create")}
            </h2>
            <p className="text-xs text-muted-foreground">{t("admin.fundingSource.plural")}</p>
          </div>
        </div>

        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <div className="grid gap-4 sm:grid-cols-2">
            {/* Nom */}
            <div className="space-y-1.5 sm:col-span-2">
              <Label>
                {t("admin.fundingSource.singular")} ({t("common.name")}) <span className="text-destructive">*</span>
              </Label>
              <Input
                value={nom}
                onChange={(e) => setNom(e.target.value)}
                placeholder="Recensement du patrimoine 2026"
                required
              />
            </div>

            {/* Exercice — fixé à la création, jamais modifiable côté backend (ignoré si envoyé lors d'une modification) */}
            <div className="space-y-1.5">
              <Label>
                {t("stats.filter.exercice")} <span className="text-destructive">*</span>
              </Label>
              <Input
                type="number"
                min={2000}
                max={2100}
                value={exercice}
                onChange={(e) => setExercice(e.target.value)}
                required
              />
            </div>

            {/* Description */}
            <div className="space-y-1.5 sm:col-span-2">
              <Label>{t("projects.field.description")}</Label>
              <Textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder={t("projects.field.descriptionPlaceholder")}
                rows={3}
              />
            </div>

            {/* Dates */}
            <div className="space-y-1.5">
              <Label>
                {t("projects.field.debut")} <span className="text-destructive">*</span>
              </Label>
              <Input
                type="date"
                value={dateDebut}
                onChange={(e) => setDateDebut(e.target.value)}
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("projects.field.fin")}</Label>
              <Input
                type="date"
                value={dateFin}
                onChange={(e) => setDateFin(e.target.value)}
              />
            </div>

            {/* Statut */}
            <div className="space-y-1.5">
              <Label>{t("projects.field.statut")}</Label>
              <Select
                value={statut}
                onValueChange={(v) => setStatut(v as ProjectStatut)}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {(
                    ["PLANIFIE", "EN_COURS", "TERMINE"] as ProjectStatut[]
                  ).map((s) => (
                    <SelectItem key={s} value={s}>
                      {STATUT_LABELS[s]}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button
            type="button"
            variant="outline"
            onClick={onCancel}
            disabled={isPending}
          >
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2" disabled={isPending}>
            {isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Save className="h-4 w-4" />
            )}
            {t("action.save")}
          </Button>
        </div>
      </form>
    </div>
  );
}
