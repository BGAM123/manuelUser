/**
 * Section de configuration — Gestion des catégories de biens.
 * CRUD complet : liste, création, édition, suppression logique, restauration.
 * Pas de suppression physique (uniquement soft-delete + restore).
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
  FolderOpen,
  Tags,
} from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
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
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { SearchableMultiSelect, type MultiSelectOption } from "@/components/shared/SearchableMultiSelect";
import { useT } from "@/utils/i18n";
import {
  listCategories,
  createCategory,
  updateCategory,
  softDeleteCategory,
  assignChampsToCategory,
  type ApiCategory,
  type CreateCategoryPayload,
  type UpdateCategoryPayload,
} from "@/api/categories/categories.api";
import { listChamps } from "@/api/champs/champs.api";

type CatView = "liste" | "form";

// ─── Section principale ─────────────────────────────────────────────────────

export function CategoriesSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<CatView>("liste");
  const [selected, setSelected] = useState<ApiCategory | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiCategory | null>(null);
  const [assignTarget, setAssignTarget] = useState<ApiCategory | null>(null);
  const [assignedChampIds, setAssignedChampIds] = useState<number[]>([]);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["categories"],
    queryFn: () => listCategories({ page: 1, limit: 1000, include_deleted: false }),
  });

  const categories: ApiCategory[] = [...(data?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom, "fr"),
  );

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["categories"] });

  // Champs disponibles pour l'affectation
  const { data: champsData } = useQuery({
    queryKey: ["champs", false],
    queryFn: () => listChamps({ page: 1, limit: 1000, is_delete: false }),
    enabled: !!assignTarget,
  });
  const champOptions: MultiSelectOption[] = (champsData?.data?.data ?? []).map((c) => ({
    value: c.id,
    label: c.nom,
  }));

  const assignMutation = useMutation({
    mutationFn: ({ id, ids }: { id: number; ids: number[] }) =>
      assignChampsToCategory(id, ids),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["champs"] });
      toast.success("Champs affectés avec succès");
      setAssignTarget(null);
    },
    onError: () => toast.error(t("toast.error")),
  });

  const softDeleteMutation = useMutation({
    mutationFn: (id: number) => softDeleteCategory(id),
    onSuccess: () => {
      invalidate();
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
    },
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string }; status?: number } })
        ?.response?.data?.message;
      const status = (err as { response?: { status?: number } })?.response?.status;
      if (status === 409) {
        toast.error(apiMsg ?? t("categories.delete.hasTypes"));
      } else {
        toast.error(t("toast.error"));
      }
    },
  });


  const columns: Column<ApiCategory>[] = [
    {
      key: "nom",
      label: t("categories.field.nom"),
      render: (c) => (
        <span className="font-semibold">{c.nom}</span>
      ),
      sortValue: (c) => c.nom,
    },
    {
      key: "description",
      label: t("categories.field.description"),
      render: (c) => (
        <span className="text-sm text-muted-foreground">{c.description || "—"}</span>
      ),
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
      <CategoryForm
        initial={selected}
        onCancel={() => setView("liste")}
        onSaved={() => {
          invalidate();
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
          <h2 className="text-lg font-semibold">{t("config.categories")}</h2>
          <div className="flex items-center gap-3">
            <Button
              className="gap-2"
              onClick={() => {
                setSelected(null);
                setView("form");
              }}
            >
              <Plus className="h-4 w-4" /> {t("categories.create")}
            </Button>
          </div>
        </div>

        <DataTable
          data={categories}
          columns={columns}
          getRowId={(c) => String(c.id)}
          exportFilename="categories-minepia"
          exportTitle="MINEPIA — Catégories"
          searchKeys={["nom", "description"]}
          rowActions={(c) => (
            <>
              <RowIconButton
                icon={Tags}
                label="Affecter des champs"
                onClick={() => {
                  setAssignTarget(c);
                  setAssignedChampIds([]);
                }}
              />
              <RowIconButton
                icon={Pencil}
                label={t("action.edit")}
                onClick={() => {
                  setSelected(c);
                  setView("form");
                }}
              />
              <RowIconButton
                icon={Trash2}
                label={t("categories.delete.title")}
                tone="danger"
                onClick={() => setDeleteTarget(c)}
              />
            </>
          )}
        />
      </div>

      <AlertDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => {
          if (!open) setDeleteTarget(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("categories.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("categories.delete.desc")}{" "}
              <span className="font-semibold text-foreground">{deleteTarget?.nom}</span>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-amber-600 text-white hover:bg-amber-700"
              onClick={() => {
                if (deleteTarget) softDeleteMutation.mutate(deleteTarget.id);
              }}
            >
              {t("categories.delete.title")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* ── Dialog affectation champs ── */}
      <Dialog open={!!assignTarget} onOpenChange={(v) => !v && setAssignTarget(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Tags className="h-5 w-5 text-primary" />
              Affecter des champs
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <p className="text-sm text-muted-foreground">
              Catégorie : <strong className="text-foreground">{assignTarget?.nom}</strong>
            </p>
            <p className="text-xs text-muted-foreground">
              Sélectionnez les champs à associer. Cette opération <strong>remplace</strong> l&apos;association existante.
            </p>
            <SearchableMultiSelect
              options={champOptions}
              value={assignedChampIds}
              onChange={setAssignedChampIds}
              placeholder="Rechercher un champ..."
            />
            {assignedChampIds.length > 0 && (
              <div className="flex flex-wrap gap-1 pt-1">
                {assignedChampIds.map((id) => {
                  const label = champOptions.find((o) => o.value === id)?.label;
                  return label ? (
                    <Badge key={id} variant="secondary" className="text-xs">{label}</Badge>
                  ) : null;
                })}
              </div>
            )}
          </div>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setAssignTarget(null)} disabled={assignMutation.isPending}>
              {t("action.cancel")}
            </Button>
            <Button
              onClick={() =>
                assignTarget &&
                assignMutation.mutate({ id: assignTarget.id, ids: assignedChampIds })
              }
              disabled={assignMutation.isPending}
              className="gap-2"
            >
              {assignMutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
              <Save className="h-4 w-4" />
              Enregistrer
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

// ─── Formulaire création / édition ─────────────────────────────────────────

function CategoryForm({
  initial,
  onCancel,
  onSaved,
}: {
  initial: ApiCategory | null;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [description, setDescription] = useState(initial?.description ?? "");

  const createMutation = useMutation({
    mutationFn: (payload: CreateCategoryPayload) => createCategory(payload),
    onSuccess: () => onSaved(),
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string }; status?: number } })
        ?.response?.data?.message;
      const status = (err as { response?: { status?: number } })?.response?.status;
      toast.error(status === 409 ? (apiMsg ?? t("toast.error")) : t("toast.error"));
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateCategoryPayload }) =>
      updateCategory(id, payload),
    onSuccess: () => onSaved(),
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string }; status?: number } })
        ?.response?.data?.message;
      const status = (err as { response?: { status?: number } })?.response?.status;
      toast.error(status === 409 ? (apiMsg ?? t("toast.error")) : t("toast.error"));
    },
  });

  const isPending = createMutation.isPending || updateMutation.isPending;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!nom.trim()) return;
    if (!initial) {
      createMutation.mutate({ nom: nom.trim(), description: description.trim() || undefined });
    } else {
      updateMutation.mutate({
        id: initial.id,
        payload: { nom: nom.trim(), description: description.trim() || undefined },
      });
    }
  };

  return (
    <div className="mx-auto w-full max-w-2xl space-y-3">
      <Button type="button" variant="ghost" size="sm" onClick={onCancel} className="-ml-2 gap-1">
        <ArrowLeft className="h-4 w-4" />
        {t("action.back")}
      </Button>

      <form
        onSubmit={handleSubmit}
        className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
      >
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <FolderOpen className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {initial ? `${t("action.edit")} — ${initial.nom}` : t("categories.create")}
            </h2>
            <p className="text-xs text-muted-foreground">{t("categories.subtitle")}</p>
          </div>
        </div>

        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <div className="grid gap-4">
            <div className="space-y-1.5">
              <Label>
                {t("categories.field.nom")} <span className="text-destructive">*</span>
              </Label>
              <Input
                value={nom}
                onChange={(e) => setNom(e.target.value)}
                placeholder="Véhicules"
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("categories.field.description")}</Label>
              <Textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Description facultative…"
                rows={3}
              />
            </div>
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button type="button" variant="outline" onClick={onCancel} disabled={isPending}>
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2" disabled={isPending}>
            {isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            {t("action.save")}
          </Button>
        </div>
      </form>
    </div>
  );
}
