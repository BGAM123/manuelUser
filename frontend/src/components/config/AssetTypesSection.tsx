/**
 * Section de configuration — Gestion des types de biens patrimoniaux.
 * CRUD complet : liste (avec filtre catégorie), création, édition,
 * suppression logique, restauration.
 * Pas de suppression physique.
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
  Box,
} from "lucide-react";
import { toast } from "sonner";
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
  listAssetTypes,
  createAssetType,
  updateAssetType,
  softDeleteAssetType,
  type ApiAssetType,
  type CreateAssetTypePayload,
  type UpdateAssetTypePayload,
} from "@/api/asset-types/asset-types.api";
import { listCategories, type ApiCategory } from "@/api/categories/categories.api";

type ATView = "liste" | "form";

// ─── Section principale ─────────────────────────────────────────────────────

export function AssetTypesSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<ATView>("liste");
  const [selected, setSelected] = useState<ApiAssetType | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiAssetType | null>(null);
  const [categoryFilter, setCategoryFilter] = useState<string>("all");

  const { data, isLoading, isError } = useQuery({
    queryKey: ["asset-types", categoryFilter],
    queryFn: () =>
      listAssetTypes({
        page: 1,
        limit: 1000,
        include_deleted: false,
        category_id: categoryFilter !== "all" ? Number(categoryFilter) : undefined,
      }),
  });

  const assetTypes: ApiAssetType[] = [...(data?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom, "fr"),
  );

  // Toutes les catégories actives pour les filtres et formulaires
  const { data: catsData } = useQuery({
    queryKey: ["categories"],
    queryFn: () => listCategories({ page: 1, limit: 1000, include_deleted: false }),
  });
  const allCategories: ApiCategory[] = catsData?.data?.data ?? [];
  const activeCategories = allCategories;
  const getCategoryName = (id: number) =>
    allCategories.find((c) => c.id === id)?.nom ?? `#${id}`;

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["asset-types"] });

  const softDeleteMutation = useMutation({
    mutationFn: (id: number) => softDeleteAssetType(id),
    onSuccess: () => {
      invalidate();
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
    },
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string } } })
        ?.response?.data?.message;
      const status = (err as { response?: { status?: number } })?.response?.status;
      toast.error(status === 409 ? (apiMsg ?? t("toast.error")) : t("toast.error"));
    },
  });


  const columns: Column<ApiAssetType>[] = [
    {
      key: "nom",
      label: t("assetTypes.field.nom"),
      render: (at) => (
        <span className="font-semibold">{at.nom}</span>
      ),
      sortValue: (at) => at.nom,
    },
    {
      key: "category_id",
      label: t("assetTypes.field.category"),
      render: (at) => {
        const cat = allCategories.find((c) => c.id === at.category_id);
        return (
          <span className="text-sm">
            {cat ? cat.nom : `#${at.category_id}`}
          </span>
        );
      },
      sortValue: (at) => getCategoryName(at.category_id),
      exportFormat: (at) => getCategoryName(at.category_id),
    },
    {
      key: "description",
      label: t("assetTypes.field.description"),
      render: (at) => (
        <span className="text-sm text-muted-foreground">{at.description || "—"}</span>
      ),
    },
    {
      key: "dureeVie",
      label: "Durée de vie (ans)",
      render: (at) => (
        <span className="text-sm tabular-nums">{at.dureeVie != null ? `${at.dureeVie} ans` : "—"}</span>
      ),
      sortValue: (at) => at.dureeVie ?? 0,
    },
    {
      key: "taux",
      label: "Taux amort. (%)",
      render: (at) => (
        <span className="text-sm tabular-nums">{at.taux != null ? `${at.taux} %` : "—"}</span>
      ),
      sortValue: (at) => at.taux ?? 0,
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
      <AssetTypeForm
        initial={selected}
        activeCategories={activeCategories}
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
          <h2 className="text-lg font-semibold">{t("config.types")}</h2>
          <div className="flex flex-wrap items-center gap-3">
            <Button
              className="gap-2"
              onClick={() => {
                setSelected(null);
                setView("form");
              }}
            >
              <Plus className="h-4 w-4" /> {t("assetTypes.create")}
            </Button>
          </div>
        </div>

        {/* Filtre par catégorie */}
        <div className="mb-3 flex items-center gap-2">
          <span className="text-sm text-muted-foreground">{t("assetTypes.filterCategory")} :</span>
          <Select value={categoryFilter} onValueChange={setCategoryFilter}>
            <SelectTrigger className="h-8 w-48 text-sm">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t("common.all")}</SelectItem>
              {activeCategories.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>
                  {c.nom}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <DataTable
          data={assetTypes}
          columns={columns}
          getRowId={(at) => String(at.id)}
          exportFilename="types-biens-minepia"
          exportTitle="MINEPIA — Types de biens"
          searchKeys={["nom", "description"]}
          rowActions={(at) => (
            <>
              <RowIconButton
                icon={Pencil}
                label={t("action.edit")}
                onClick={() => {
                  setSelected(at);
                  setView("form");
                }}
              />
              <RowIconButton
                icon={Trash2}
                label={t("assetTypes.delete.title")}
                tone="danger"
                onClick={() => setDeleteTarget(at)}
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
            <AlertDialogTitle>{t("assetTypes.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("assetTypes.delete.desc")}{" "}
              <span className="font-semibold text-foreground">{deleteTarget?.nom}</span>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => {
                if (deleteTarget) softDeleteMutation.mutate(deleteTarget.id);
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

function AssetTypeForm({
  initial,
  activeCategories,
  onCancel,
  onSaved,
}: {
  initial: ApiAssetType | null;
  activeCategories: ApiCategory[];
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [description, setDescription] = useState(initial?.description ?? "");
  const [categoryId, setCategoryId] = useState<string>(
    initial?.category_id ? String(initial.category_id) : "",
  );
  const [dureeVie, setDureeVie] = useState<string>(initial?.dureeVie != null ? String(initial.dureeVie) : "");
  const [taux, setTaux] = useState<string>(initial?.taux != null ? String(initial.taux) : "");

  const createMutation = useMutation({
    mutationFn: (payload: CreateAssetTypePayload) => createAssetType(payload),
    onSuccess: () => onSaved(),
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string }; status?: number } })
        ?.response?.data?.message;
      const status = (err as { response?: { status?: number } })?.response?.status;
      toast.error(status === 409 ? (apiMsg ?? t("toast.error")) : t("toast.error"));
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateAssetTypePayload }) =>
      updateAssetType(id, payload),
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
    if (!nom.trim() || !categoryId) return;
    const payload = {
      nom: nom.trim(),
      description: description.trim() || undefined,
      category_id: Number(categoryId),
      dureeVie: dureeVie ? Number(dureeVie) : undefined,
      taux: taux ? Number(taux) : undefined,
    };
    if (!initial) {
      createMutation.mutate(payload);
    } else {
      updateMutation.mutate({ id: initial.id, payload });
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
            <Box className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {initial ? `${t("action.edit")} — ${initial.nom}` : t("assetTypes.create")}
            </h2>
            <p className="text-xs text-muted-foreground">{t("assetTypes.subtitle")}</p>
          </div>
        </div>

        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <div className="grid gap-4">
            <div className="space-y-1.5">
              <Label>
                {t("assetTypes.field.nom")} <span className="text-destructive">*</span>
              </Label>
              <Input
                value={nom}
                onChange={(e) => setNom(e.target.value)}
                placeholder="Véhicule léger"
                required
              />
            </div>

            <div className="space-y-1.5">
              <Label>
                {t("assetTypes.field.category")} <span className="text-destructive">*</span>
              </Label>
              <Select value={categoryId} onValueChange={setCategoryId} required>
                <SelectTrigger>
                  <SelectValue placeholder={t("assetTypes.selectCategory")} />
                </SelectTrigger>
                <SelectContent>
                  {activeCategories.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.nom}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5">
              <Label>{t("assetTypes.field.description")}</Label>
              <Textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Description facultative…"
                rows={3}
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>Durée de vie <span className="text-xs text-muted-foreground">(années)</span></Label>
                <Input
                  type="number"
                  min={0}
                  value={dureeVie}
                  onChange={(e) => setDureeVie(e.target.value)}
                  placeholder="Ex : 10"
                />
              </div>
              <div className="space-y-1.5">
                <Label>Taux d’amortissement <span className="text-xs text-muted-foreground">(%)</span></Label>
                <Input
                  type="number"
                  min={0}
                  max={100}
                  step={0.01}
                  value={taux}
                  onChange={(e) => setTaux(e.target.value)}
                  placeholder="Ex : 20"
                />
              </div>
            </div>
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button type="button" variant="outline" onClick={onCancel} disabled={isPending}>
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2" disabled={isPending || !categoryId}>
            {isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            {t("action.save")}
          </Button>
        </div>
      </form>
    </div>
  );
}
