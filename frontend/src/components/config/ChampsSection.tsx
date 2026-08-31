/**
 * Section de configuration — Gestion des champs personnalisés.
 * CRUD complet : liste, création, édition, suppression logique, restauration.
 *
 * Spec v2 : typeChamp/valeur supprimés ; catégories associées via SearchableMultiSelect
 * alimenté par GET /categories, envoyant category_ids[] (remplacement total).
 */

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Plus,
  Pencil,
  Trash2,
  Save,
  ArrowLeft,
  Loader2,
  RotateCcw,
  AlertCircle,
  Sliders,
  Tags,
  X,
} from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { SearchableMultiSelect, type MultiSelectOption } from "@/components/shared/SearchableMultiSelect";
import { useT, type Key } from "@/utils/i18n";
import {
  listChamps,
  getChampById,
  createChamp,
  updateChamp,
  softDeleteChamp,
  restoreChamp,
  type ApiChamp,
  type ChampPayload,
  type ChampType,
  type ChampSubtype,
} from "@/api/champs/champs.api";
import { listCategories, assignChampsToCategory } from "@/api/categories/categories.api";

// ─── Types et sous-types de champs prédéfinis ──────────────────────────────

const TYPE_LABEL_KEYS: Record<ChampType, Key> = {
  text: "champs.type.text",
  textarea: "champs.type.textarea",
  number: "champs.type.number",
  date: "champs.type.date",
  select: "champs.type.select",
  file: "champs.type.file",
};

const TYPE_OPTIONS: ChampType[] = ["text", "textarea", "number", "date", "select", "file"];

const SUBTYPE_LABEL_KEYS: Record<ChampSubtype, Key> = {
  single: "champs.subtype.single",
  boolean: "champs.subtype.boolean",
  region: "champs.subtype.region",
  departement: "champs.subtype.departement",
  arrondissement: "champs.subtype.arrondissement",
  structure: "champs.subtype.structure",
  cartographie: "champs.subtype.cartographie",
};

const SUBTYPE_OPTIONS: ChampSubtype[] = ["single", "boolean", "region", "departement", "arrondissement", "structure", "cartographie"];

// ─── Section principale ─────────────────────────────────────────────────────

export function ChampsSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<"liste" | "form">("liste");
  const [selected, setSelected] = useState<ApiChamp | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiChamp | null>(null);
  const [showDeleted, setShowDeleted] = useState(false);
  const [filterCategoryIds, setFilterCategoryIds] = useState<number[]>([]);
  const [loadingEditId, setLoadingEditId] = useState<number | null>(null);
  // ─ Dialog affectation de champs à une catégorie ─
  const [affectOpen, setAffectOpen] = useState(false);
  const [affectCategoryId, setAffectCategoryId] = useState<string>("");
  const [affectChampIds, setAffectChampIds] = useState<number[]>([]);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["champs", showDeleted, filterCategoryIds],
    queryFn: () =>
      listChamps({
        page: 1,
        limit: 1000,
        is_delete: showDeleted,
        category_ids: filterCategoryIds.length > 0 ? filterCategoryIds.join(",") : undefined,
      }),
  });

  const champs: ApiChamp[] = [...(data?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom, "fr"),
  );

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["champs"] });

  // Catégories — utilisées à la fois pour le dialog d'affectation et le
  // filtre par catégorie du tableau.
  const { data: catsForAssign } = useQuery({
    queryKey: ["categories", "active"],
    queryFn: () => listCategories({ page: 1, limit: 1000, include_deleted: false }),
  });
  const categoryList = (catsForAssign?.data?.data ?? []).filter((c) => !c.is_delete);

  const affectMutation = useMutation({
    mutationFn: ({ catId, ids }: { catId: number; ids: number[] }) =>
      assignChampsToCategory(catId, ids),
    onSuccess: () => {
      invalidate();
      toast.success(t("champs.affectSuccess"));
      setAffectOpen(false);
      setAffectCategoryId("");
      setAffectChampIds([]);
    },
    onError: () => toast.error(t("toast.error")),
  });

  const softDeleteMutation = useMutation({
    mutationFn: (id: number) => softDeleteChamp(id),
    onSuccess: () => {
      invalidate();
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
    },
    onError: () => {
      toast.error(t("toast.error"));
      setDeleteTarget(null);
    },
  });

  const restoreMutation = useMutation({
    mutationFn: (id: number) => restoreChamp(id),
    onSuccess: () => {
      invalidate();
      toast.success(t("toast.saved"));
    },
    onError: () => toast.error(t("toast.error")),
  });

  const columns: Column<ApiChamp>[] = [
    {
      key: "nom",
      label: t("common.name"),
      render: (c) => <span className="font-semibold">{c.nom}</span>,
      sortValue: (c) => c.nom,
    },
    {
      key: "type",
      label: t("consumables.field.type"),
      render: (c) => (
        <span className="text-xs">
          {c.type ? t(TYPE_LABEL_KEYS[c.type]) : "—"}
          {c.type === "select" && c.subtype && (
            <span className="text-muted-foreground"> · {t(SUBTYPE_LABEL_KEYS[c.subtype])}</span>
          )}
        </span>
      ),
      exportFormat: (c) => (c.type ? t(TYPE_LABEL_KEYS[c.type]) : "—"),
    },
    {
      key: "categories",
      label: t("config.categories"),
      render: (c) =>
        (c.categories ?? []).length === 0 ? (
          <span className="text-xs text-muted-foreground">—</span>
        ) : (
          <div className="flex flex-wrap gap-1">
            {(c.categories ?? []).map((cat) => (
              <Badge
                key={cat.id}
                variant="outline"
                className="text-xs bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-900/20 dark:text-violet-300 dark:border-violet-700/40"
              >
                {cat.nom}
              </Badge>
            ))}
          </div>
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
      <div className="flex h-40 items-center justify-center gap-2 text-destructive">
        <AlertCircle className="h-5 w-5" />
        <span>{t("toast.error")}</span>
      </div>
    );
  }

  if (view === "form") {
    return (
      <ChampForm
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
          <h2 className="text-lg font-semibold">{t("config.champs")}</h2>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              className="gap-2"
              onClick={() => { setAffectOpen(true); setAffectChampIds([]); }}
            >
              <Tags className="h-4 w-4" /> {t("champs.assignButton")}
            </Button>
            <Button
              className="gap-2"
              onClick={() => {
                setSelected(null);
                setView("form");
              }}
            >
              <Plus className="h-4 w-4" /> {t("champs.create")}
            </Button>
          </div>
        </div>

        <div className="mb-3 flex flex-wrap items-end gap-4">
          <div className="w-72 space-y-1.5">
            <Label className="text-xs text-muted-foreground">{t("champs.filterByCategory")}</Label>
            <SearchableMultiSelect
              options={categoryList.map((c) => ({ value: c.id, label: c.nom }))}
              value={filterCategoryIds}
              onChange={setFilterCategoryIds}
              placeholder={t("champs.allCategories")}
            />
          </div>
          <label className="flex cursor-pointer items-center gap-2 text-sm pb-2">
            <Switch checked={showDeleted} onCheckedChange={setShowDeleted} />
            {t("consumables.showDeleted")}
          </label>
        </div>

        <DataTable
          data={champs}
          columns={columns}
          getRowId={(c) => String(c.id)}
          exportFilename="champs-minepia"
          exportTitle={`MINEPIA — ${t("config.champs")}`}
          searchKeys={["nom"]}
          rowActions={(c) =>
            c.is_delete ? (
              <RowIconButton
                icon={RotateCcw}
                label={t("action.restore")}
                onClick={() => restoreMutation.mutate(c.id)}
              />
            ) : (
              <>
                <RowIconButton
                  icon={loadingEditId === c.id ? Loader2 : Pencil}
                  label={t("action.edit")}
                  onClick={async () => {
                    setLoadingEditId(c.id);
                    try {
                      const res = await getChampById(c.id);
                      setSelected(res.data ?? c);
                    } catch {
                      // fallback sur les données de liste
                      setSelected(c);
                    } finally {
                      setLoadingEditId(null);
                    }
                    setView("form");
                  }}
                />
                <RowIconButton
                  icon={Trash2}
                  label={t("action.delete")}
                  tone="danger"
                  onClick={() => setDeleteTarget(c)}
                />
              </>
            )
          }
        />
      </div>

      <AlertDialog
        open={!!deleteTarget}
        onOpenChange={(v) => !v && setDeleteTarget(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("champs.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("champs.delete.descPrefix")} <strong>{deleteTarget?.nom}</strong>{" "}
              {t("etatBiens.delete.descSuffix")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() =>
                deleteTarget && softDeleteMutation.mutate(deleteTarget.id)
              }
            >
              {t("action.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* ── Dialog : Affecter des champs à une catégorie ── */}
      <Dialog open={affectOpen} onOpenChange={(v) => { if (!v) { setAffectOpen(false); setAffectCategoryId(""); setAffectChampIds([]); } }}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Tags className="h-5 w-5 text-primary" />
              {t("champs.assignDialog.title")}
            </DialogTitle>
          </DialogHeader>

          <div className="space-y-4 py-1">
            {/* Sélection de la catégorie cible */}
            <div className="space-y-1.5">
              <Label>
                {t("champs.assignDialog.targetCategory")} <span className="text-destructive">*</span>
              </Label>
              <Select value={affectCategoryId} onValueChange={setAffectCategoryId}>
                <SelectTrigger>
                  <SelectValue placeholder={t("assetTypes.selectCategory")} />
                </SelectTrigger>
                <SelectContent>
                  {categoryList.map((cat) => (
                    <SelectItem key={cat.id} value={String(cat.id)}>
                      {cat.nom}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Sélection des champs */}
            <div className="space-y-1.5">
              <Label>{t("champs.assignDialog.fieldsLabel")}</Label>
              <p className="text-xs text-muted-foreground">
                {t("champs.assignDialog.fieldsHint")}
              </p>
              <SearchableMultiSelect
                options={champs.map((c) => ({ value: c.id, label: c.nom }))}
                value={affectChampIds}
                onChange={setAffectChampIds}
                placeholder={t("champs.searchField")}
              />
            </div>
          </div>

          <DialogFooter className="gap-2">
            <Button
              variant="outline"
              onClick={() => { setAffectOpen(false); setAffectCategoryId(""); setAffectChampIds([]); }}
              disabled={affectMutation.isPending}
            >
              {t("action.cancel")}
            </Button>
            <Button
              disabled={affectMutation.isPending || !affectCategoryId}
              onClick={() =>
                affectMutation.mutate({
                  catId: Number(affectCategoryId),
                  ids: affectChampIds,
                })
              }
              className="gap-2"
            >
              {affectMutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
              <Save className="h-4 w-4" />
              {t("action.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

// ─── Formulaire création / édition ──────────────────────────────────────────

function ChampForm({
  initial,
  onCancel,
  onSaved,
}: {
  initial: ApiChamp | null;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [categoryIds, setCategoryIds] = useState<number[]>(
    initial?.categories?.map((c) => c.id) ?? [],
  );
  const [type, setType] = useState<ChampType>(initial?.type ?? "text");
  const [subtype, setSubtype] = useState<ChampSubtype>(initial?.subtype ?? "single");
  const [options, setOptions] = useState<string[]>(
    initial?.option ? initial.option.split(",").map((s) => s.trim()).filter(Boolean) : [],
  );
  const [optionInput, setOptionInput] = useState("");

  const addOption = () => {
    const v = optionInput.trim();
    if (!v || options.includes(v)) return;
    setOptions((prev) => [...prev, v]);
    setOptionInput("");
  };
  const removeOption = (v: string) => setOptions((prev) => prev.filter((o) => o !== v));

  const { data: catsData, isLoading: catsLoading } = useQuery({
    queryKey: ["categories", "active"],
    queryFn: () => listCategories({ page: 1, limit: 1000, include_deleted: false }),
  });

  const categoryOptions: MultiSelectOption[] = (catsData?.data?.data ?? [])
    .filter((c) => !c.is_delete)
    .map((c) => ({ value: c.id, label: c.nom }));

  const createMutation = useMutation({
    mutationFn: (payload: ChampPayload) => createChamp(payload),
    onSuccess: () => onSaved(),
    onError: () => toast.error(t("toast.error")),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: ChampPayload }) =>
      updateChamp(id, payload),
    onSuccess: () => onSaved(),
    onError: () => toast.error(t("toast.error")),
  });

  const isPending = createMutation.isPending || updateMutation.isPending;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!nom.trim()) {
      toast.error(t("etatBiens.error.nameRequired"));
      return;
    }
    if (type === "select" && subtype === "single" && options.length === 0) {
      toast.error(t("champs.error.optionRequired"));
      return;
    }
    const effectiveSubtype: ChampSubtype = type === "select" ? subtype : "single";
    const payload: ChampPayload = {
      nom: nom.trim(),
      type,
      subtype: effectiveSubtype,
      category_ids: categoryIds,
      // Confirmé en direct (2026-08-27) : POST/PATCH /champs répond 400
      // "L'option du champ est obligatoire." si `option` est absent ou vide,
      // quels que soient type/subtype — pas seulement pour select+single
      // comme documenté initialement. Un champ region/boolean/structure/
      // texte n'utilise jamais cette valeur à l'affichage (voir Biens.tsx,
      // renderChampValueInput), donc un espace réservé neutre suffit pour
      // les cas où elle n'a pas de sens fonctionnel.
      option: type === "select" && subtype === "single" ? options.join(",") : "-",
    };
    if (!initial) {
      createMutation.mutate(payload);
    } else {
      updateMutation.mutate({ id: initial.id, payload });
    }
  };

  return (
    <div className="mx-auto w-full max-w-xl space-y-3">
      <Button
        type="button"
        variant="ghost"
        size="sm"
        onClick={onCancel}
        className="-ml-2 gap-1"
      >
        <ArrowLeft className="h-4 w-4" /> {t("action.back")}
      </Button>

      <form
        onSubmit={handleSubmit}
        className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
      >
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Sliders className="h-5 w-5" />
          </div>
          <div>
            <h2 className="text-base font-semibold">
              {initial ? t("etatBiens.form.editTitle", { name: initial.nom }) : t("champs.form.createTitle")}
            </h2>
            <p className="text-xs text-muted-foreground">
              {t("champs.form.subtitle")}
            </p>
          </div>
        </div>

        <div className="space-y-5 px-5 py-5">
          <div className="space-y-1.5">
            <Label htmlFor="champ-nom">
              {t("common.name")} <span className="text-destructive">*</span>
            </Label>
            <Input
              id="champ-nom"
              value={nom}
              onChange={(e) => setNom(e.target.value)}
              placeholder="Ex : Couleur, Puissance, Capacité..."
              autoFocus
            />
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label>
                {t("champs.form.typeLabel")} <span className="text-destructive">*</span>
              </Label>
              <Select value={type} onValueChange={(v) => { setType(v as ChampType); setSubtype("single"); }}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  {TYPE_OPTIONS.map((opt) => (
                    <SelectItem key={opt} value={opt}>{t(TYPE_LABEL_KEYS[opt])}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {type === "select" && (
              <div className="space-y-1.5">
                <Label>
                  {t("champs.form.subtypeLabel")} <span className="text-destructive">*</span>
                </Label>
                <Select value={subtype} onValueChange={(v) => setSubtype(v as ChampSubtype)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {SUBTYPE_OPTIONS.map((opt) => (
                      <SelectItem key={opt} value={opt}>{t(SUBTYPE_LABEL_KEYS[opt])}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}
          </div>

          {type === "select" && subtype === "single" && (
            <div className="space-y-1.5">
              <Label>{t("champs.form.optionsLabel")}</Label>
              <p className="text-xs text-muted-foreground">
                {t("champs.form.optionsHint")}
              </p>
              <div className="flex gap-2">
                <Input
                  value={optionInput}
                  onChange={(e) => setOptionInput(e.target.value)}
                  onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addOption(); } }}
                  placeholder="Ex : Rouge"
                />
                <Button type="button" variant="outline" onClick={addOption} disabled={!optionInput.trim()}>
                  <Plus className="h-4 w-4" />
                </Button>
              </div>
              {options.length > 0 && (
                <div className="flex flex-wrap gap-1.5 pt-1">
                  {options.map((o) => (
                    <Badge key={o} variant="secondary" className="gap-1 pr-1 text-xs">
                      {o}
                      <button type="button" onClick={() => removeOption(o)} className="rounded-full p-0.5 hover:bg-muted-foreground/20">
                        <X className="h-3 w-3" />
                      </button>
                    </Badge>
                  ))}
                </div>
              )}
            </div>
          )}

          <div className="space-y-1.5">
            <Label>{t("champs.form.categoriesLabel")}</Label>
            <p className="text-xs text-muted-foreground">
              {t("champs.form.categoriesHint")}
            </p>
            <SearchableMultiSelect
              options={categoryOptions}
              value={categoryIds}
              onChange={setCategoryIds}
              placeholder={
                catsLoading
                  ? t("champs.form.loadingCategories")
                  : t("champs.form.selectCategories")
              }
              disabled={catsLoading}
            />
          </div>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-border bg-muted/20 px-5 py-3">
          <Button type="button" variant="outline" onClick={onCancel} disabled={isPending}>
            {t("action.cancel")}
          </Button>
          <Button type="submit" disabled={isPending || !nom.trim()} className="gap-2">
            {isPending && <Loader2 className="h-4 w-4 animate-spin" />}
            <Save className="h-4 w-4" />
            {t("action.save")}
          </Button>
        </div>
      </form>
    </div>
  );
}
