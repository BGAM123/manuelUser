/**
 * Section Catégories de biens — Arbre hiérarchique + CRUD intégré.
 * 3 niveaux : Catégories → Types de biens → Sous-types de biens
 * Inspiré du pattern OrganigrammeSection.
 */

import { useState, useEffect, useMemo } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Plus,
  Pencil,
  Trash2,
  X,
  Loader2,
  FolderOpen,
  Search,
  ChevronRight,
  RotateCcw,
} from "lucide-react";
import { toast } from "sonner";
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
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { useT } from "@/utils/i18n";
import { cn } from "@/utils/utils";
import { Switch } from "@/components/ui/switch";
import {
  getCategorieHierarchie,
  getCategoryById,
  listCategories,
  createCategory,
  updateCategory,
  softDeleteCategory,
  restoreCategory,
  isDeletedHierarchyNode,
  type HierarchyCategory,
  type HierarchyType,
  type HierarchySubtype,
} from "@/api/categories/categories.api";
import {
  createAssetType,
  updateAssetType,
  softDeleteAssetType,
  restoreAssetType,
} from "@/api/asset-types/asset-types.api";
import {
  createAssetSubtype,
  updateAssetSubtype,
  softDeleteAssetSubtype,
  restoreAssetSubtype,
} from "@/api/asset-subtypes/asset-subtypes.api";

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getApiError(err: unknown): string {
  const d = (err as { response?: { data?: { message?: string; detail?: string } } })?.response
    ?.data;
  return d?.message ?? d?.detail ?? "Une erreur est survenue";
}

/** Filtre récursivement l'arbre hiérarchique selon une recherche textuelle. */
function filterHierarchy(items: HierarchyCategory[], q: string): HierarchyCategory[] {
  if (!q.trim()) return items;
  const lq = q.toLowerCase();
  return items.reduce<HierarchyCategory[]>((acc, cat) => {
    const catMatch = cat.nom.toLowerCase().includes(lq);
    const filteredTypes = cat.types.reduce<HierarchyType[]>((tacc, tp) => {
      const tMatch = tp.nom.toLowerCase().includes(lq);
      const filteredSubs = tp.sousTypes.filter((s) => s.nom.toLowerCase().includes(lq));
      if (tMatch || filteredSubs.length > 0) {
        tacc.push({ ...tp, sousTypes: tMatch ? tp.sousTypes : filteredSubs });
      }
      return tacc;
    }, []);
    if (catMatch || filteredTypes.length > 0) {
      acc.push({ ...cat, types: catMatch ? cat.types : filteredTypes });
    }
    return acc;
  }, []);
}

// ─── Palette de couleurs par niveau ──────────────────────────────────────────

const LEVEL_COLORS = [
  {
    bg: "bg-primary/8 border-primary/30",
    text: "text-primary",
    dot: "bg-primary",
    badge: "bg-primary/10 border-primary/20",
    info: "bg-primary/5 border-primary/20 text-primary",
  },
  {
    bg: "bg-blue-50 border-blue-200 dark:bg-blue-900/15 dark:border-blue-700/40",
    text: "text-blue-700 dark:text-blue-300",
    dot: "bg-blue-500",
    badge: "bg-blue-100 border-blue-200 dark:bg-blue-900/30",
    info: "bg-blue-50 border-blue-200 dark:bg-blue-900/15 dark:border-blue-700/40 text-blue-700 dark:text-blue-300",
  },
  {
    bg: "bg-emerald-50 border-emerald-200 dark:bg-emerald-900/15 dark:border-emerald-700/40",
    text: "text-emerald-700 dark:text-emerald-300",
    dot: "bg-emerald-500",
    badge: "bg-emerald-100 border-emerald-200 dark:bg-emerald-900/30",
    info: "bg-emerald-50 border-emerald-200 dark:bg-emerald-900/15 dark:border-emerald-700/40 text-emerald-700 dark:text-emerald-300",
  },
] as const;

// ─── Types du dialog inline ───────────────────────────────────────────────────

type CatDialog =
  | null
  | { mode: "createCategory" }
  | { mode: "editCategory"; cat: HierarchyCategory }
  | { mode: "createType"; catId: number; catNom: string }
  | { mode: "editType"; tp: HierarchyType; catId: number; catNom: string }
  | { mode: "createSubtype"; tpId: number; tpNom: string }
  | { mode: "editSubtype"; sub: HierarchySubtype; tpId: number; tpNom: string };

type DeleteTarget =
  | null
  | { kind: "category"; id: number; nom: string }
  | { kind: "type"; id: number; nom: string }
  | { kind: "subtype"; id: number; nom: string };

// ─── Nœud : Sous-type (niveau 2) ─────────────────────────────────────────────

function SubtypeNode({
  sub,
  onEdit,
  onDelete,
  onRestore,
}: {
  sub: HierarchySubtype;
  onEdit: () => void;
  onDelete: () => void;
  onRestore: () => void;
}) {
  const t = useT();
  const c = LEVEL_COLORS[2];
  const isDeleted = isDeletedHierarchyNode(sub);
  return (
    <div className="relative ml-5 pl-4 before:absolute before:left-0 before:top-0 before:h-full before:border-l-2 before:border-dashed before:border-border/60">
      <div className={cn("group mb-1.5 rounded-xl border px-4 py-2.5 transition-all", c.bg, isDeleted && "opacity-60")}>
        <div className="flex items-center gap-3">
          <div className={cn("h-1.5 w-1.5 shrink-0 rounded-full", c.dot)} />
          <div className="min-w-0 flex-1">
            <span className={cn("text-sm", c.text)}>{sub.nom}</span>
            {isDeleted && (
              <span className="ml-2 rounded-full border border-muted-foreground/30 px-1.5 py-0.5 text-[10px] text-muted-foreground">{t("assetTypes.archived")}</span>
            )}
            {sub.description && (
              <p className="mt-0.5 truncate text-xs text-muted-foreground">{sub.description}</p>
            )}
          </div>
          <div
            className="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100"
            onClick={(e) => e.stopPropagation()}
          >
            {isDeleted ? (
              <button
                type="button"
                title={t("categoriesTree.restoreSubtypeTitle")}
                onClick={onRestore}
                className="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
              >
                <RotateCcw className="h-3 w-3" />
              </button>
            ) : (
              <>
                <button
                  type="button"
                  title={t("categoriesTree.editSubtypeTitle")}
                  onClick={onEdit}
                  className="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                  <Pencil className="h-3 w-3" />
                </button>
                <button
                  type="button"
                  title={t("categoriesTree.deleteSubtypeTitle")}
                  onClick={onDelete}
                  className="inline-flex h-6 w-6 items-center justify-center rounded text-destructive hover:bg-destructive/10"
                >
                  <Trash2 className="h-3 w-3" />
                </button>
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ─── Nœud : Type de bien (niveau 1) ──────────────────────────────────────────

function TypeNode({
  tp,
  catId,
  forceOpen,
  onAddSubtype,
  onEdit,
  onDelete,
  onRestore,
  onEditSubtype,
  onDeleteSubtype,
  onRestoreSubtype,
}: {
  tp: HierarchyType;
  catId: number;
  forceOpen: boolean;
  onAddSubtype: (tpId: number, tpNom: string) => void;
  onEdit: (tp: HierarchyType, catId: number) => void;
  onDelete: (tp: HierarchyType) => void;
  onRestore: (tp: HierarchyType) => void;
  onEditSubtype: (sub: HierarchySubtype, tpId: number, tpNom: string) => void;
  onDeleteSubtype: (sub: HierarchySubtype) => void;
  onRestoreSubtype: (sub: HierarchySubtype) => void;
}) {
  const t = useT();
  const [open, setOpen] = useState(false);
  const hasSubs = tp.sousTypes.length > 0;
  const effective = forceOpen || open;
  const c = LEVEL_COLORS[1];
  const isDeleted = isDeletedHierarchyNode(tp);

  return (
    <div className="relative ml-5 pl-4 before:absolute before:left-0 before:top-0 before:h-full before:border-l-2 before:border-dashed before:border-border/60">
      <div
        className={cn(
          "group mb-1.5 rounded-xl border px-4 py-3 transition-all",
          c.bg,
          hasSubs && "cursor-pointer hover:shadow-sm",
        )}
        onClick={() => hasSubs && setOpen(!open)}
      >
        <div className="flex items-center gap-3">
          <div className="flex h-5 w-5 shrink-0 items-center justify-center">
            {hasSubs ? (
              <ChevronRight
                className={cn(
                  "h-4 w-4 transition-transform duration-200",
                  c.text,
                  effective && "rotate-90",
                )}
              />
            ) : (
              <div className={cn("h-2 w-2 rounded-full", c.dot)} />
            )}
          </div>
          <div className="min-w-0 flex-1">
            <span className={cn("text-sm font-semibold", c.text)}>{tp.nom}</span>
            {isDeleted && (
              <span className="ml-2 rounded-full border border-muted-foreground/30 px-1.5 py-0.5 text-[10px] font-normal text-muted-foreground">{t("assetTypes.archived")}</span>
            )}
            {tp.description && (
              <p className="mt-0.5 truncate text-xs text-muted-foreground">{tp.description}</p>
            )}
            {(tp.dureeVie != null || tp.taux != null) && (
              <div className="mt-0.5 flex flex-wrap gap-3">
                {tp.dureeVie != null && (
                  <span className="text-xs text-muted-foreground">
                    {tp.dureeVie > 1
                      ? t("categoriesTree.lifespanYears", { count: tp.dureeVie })
                      : t("categoriesTree.lifespanYear", { count: tp.dureeVie })}
                  </span>
                )}
                {tp.taux != null && (
                  <span className="text-xs text-muted-foreground">{t("categoriesTree.rate", { value: tp.taux })}</span>
                )}
              </div>
            )}
          </div>
          {hasSubs && (
            <span className="shrink-0 text-xs text-muted-foreground">
              {tp.sousTypes.length > 1
                ? t("categoriesTree.subtypeCountPlural", { count: tp.sousTypes.length })
                : t("categoriesTree.subtypeCount", { count: tp.sousTypes.length })}
            </span>
          )}
          <div
            className="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100"
            onClick={(e) => e.stopPropagation()}
          >
            {isDeleted ? (
              <button
                type="button"
                title={t("categoriesTree.restoreTypeTitle")}
                onClick={() => onRestore(tp)}
                className="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
              >
                <RotateCcw className="h-3.5 w-3.5" />
              </button>
            ) : (
              <>
                <button
                  type="button"
                  title={t("categoriesTree.addSubtypeTitle")}
                  onClick={() => onAddSubtype(tp.id, tp.nom)}
                  className="inline-flex h-6 w-6 items-center justify-center rounded text-primary hover:bg-primary/10"
                >
                  <Plus className="h-3.5 w-3.5" />
                </button>
                <button
                  type="button"
                  title={t("categoriesTree.editTypeTitle")}
                  onClick={() => onEdit(tp, catId)}
                  className="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                  <Pencil className="h-3.5 w-3.5" />
                </button>
                <button
                  type="button"
                  title={t("categoriesTree.deleteTypeTitle")}
                  onClick={() => onDelete(tp)}
                  className="inline-flex h-6 w-6 items-center justify-center rounded text-destructive hover:bg-destructive/10"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </>
            )}
          </div>
        </div>
      </div>

      {/* Sous-types */}
      {effective && hasSubs && (
        <div>
          {tp.sousTypes.map((sub) => (
            <SubtypeNode
              key={sub.id}
              sub={sub}
              onEdit={() => onEditSubtype(sub, tp.id, tp.nom)}
              onDelete={() => onDeleteSubtype(sub)}
              onRestore={() => onRestoreSubtype(sub)}
            />
          ))}
        </div>
      )}
    </div>
  );
}

// ─── Nœud : Catégorie (niveau 0) ─────────────────────────────────────────────

function CategoryNode({
  cat,
  seuil,
  consommable,
  forceOpen,
  onAddType,
  onEdit,
  onDelete,
  onRestore,
  onAddSubtype,
  onEditType,
  onDeleteType,
  onRestoreType,
  onEditSubtype,
  onDeleteSubtype,
  onRestoreSubtype,
}: {
  cat: HierarchyCategory;
  seuil: number | null;
  consommable: boolean;
  forceOpen: boolean;
  onAddType: (catId: number, catNom: string) => void;
  onEdit: (cat: HierarchyCategory) => void;
  onDelete: (cat: HierarchyCategory) => void;
  onRestore: (cat: HierarchyCategory) => void;
  onAddSubtype: (tpId: number, tpNom: string) => void;
  onEditType: (tp: HierarchyType, catId: number, catNom: string) => void;
  onDeleteType: (tp: HierarchyType) => void;
  onRestoreType: (tp: HierarchyType) => void;
  onEditSubtype: (sub: HierarchySubtype, tpId: number, tpNom: string) => void;
  onDeleteSubtype: (sub: HierarchySubtype) => void;
  onRestoreSubtype: (sub: HierarchySubtype) => void;
}) {
  const t = useT();
  const [open, setOpen] = useState(false);
  const hasTypes = cat.types.length > 0;
  const effective = forceOpen || open;
  const c = LEVEL_COLORS[0];
  const isDeleted = isDeletedHierarchyNode(cat);

  return (
    <div>
      <div
        className={cn(
          "group mb-1.5 rounded-xl border px-4 py-3 transition-all",
          c.bg,
          hasTypes && "cursor-pointer hover:shadow-sm",
        )}
        onClick={() => hasTypes && setOpen(!open)}
      >
        <div className="flex items-center gap-3">
          <div className="flex h-6 w-6 shrink-0 items-center justify-center">
            {hasTypes ? (
              <ChevronRight
                className={cn(
                  "h-4 w-4 transition-transform duration-200",
                  c.text,
                  effective && "rotate-90",
                )}
              />
            ) : (
              <div className={cn("h-2 w-2 rounded-full", c.dot)} />
            )}
          </div>
          <div className="min-w-0 flex-1">
            <span className={cn("text-sm font-bold uppercase tracking-wide", c.text)}>
              {cat.nom}
            </span>
            {isDeleted && (
              <span className="ml-2 rounded-full border border-muted-foreground/30 px-1.5 py-0.5 text-[10px] font-normal normal-case text-muted-foreground">{t("categories.archived")}</span>
            )}
            {seuil != null && (
              <span className="ml-2 rounded-full border border-amber-300 bg-amber-50 px-1.5 py-0.5 text-[10px] font-normal normal-case text-amber-700 dark:border-amber-700/40 dark:bg-amber-900/20 dark:text-amber-400">
                {t("categoriesTree.threshold", { value: seuil })}
              </span>
            )}
            {consommable && (
              <span className="ml-2 rounded-full border border-blue-300 bg-blue-50 px-1.5 py-0.5 text-[10px] font-normal normal-case text-blue-700 dark:border-blue-700/40 dark:bg-blue-900/20 dark:text-blue-400">
                {t("categoriesTree.consumableBadge")}
              </span>
            )}
            {cat.description && (
              <p className="mt-0.5 truncate text-xs text-muted-foreground">{cat.description}</p>
            )}
          </div>
          {hasTypes && (
            <span className="shrink-0 text-xs text-muted-foreground">
              {cat.types.length > 1
                ? t("categoriesTree.typeCountPlural", { count: cat.types.length })
                : t("categoriesTree.typeCount", { count: cat.types.length })}
            </span>
          )}
          <div
            className="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100"
            onClick={(e) => e.stopPropagation()}
          >
            {isDeleted ? (
              <button
                type="button"
                title={t("categoriesTree.restoreCategoryTitle")}
                onClick={() => onRestore(cat)}
                className="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
              >
                <RotateCcw className="h-3.5 w-3.5" />
              </button>
            ) : (
              <>
                <button
                  type="button"
                  title={t("categoriesTree.addTypeTitle")}
                  onClick={() => onAddType(cat.id, cat.nom)}
                  className="inline-flex h-6 w-6 items-center justify-center rounded text-primary hover:bg-primary/10"
                >
                  <Plus className="h-3.5 w-3.5" />
                </button>
                <button
                  type="button"
                  title={t("categoriesTree.editCategoryTitle")}
                  onClick={() => onEdit(cat)}
                  className="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                  <Pencil className="h-3.5 w-3.5" />
                </button>
                <button
                  type="button"
                  title={t("categoriesTree.deleteCategoryTitle")}
                  onClick={() => onDelete(cat)}
                  className="inline-flex h-6 w-6 items-center justify-center rounded text-destructive hover:bg-destructive/10"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </>
            )}
          </div>
        </div>
      </div>

      {/* Types */}
      {effective && hasTypes && (
        <div>
          {cat.types.map((tp) => (
            <TypeNode
              key={tp.id}
              tp={tp}
              catId={cat.id}
              forceOpen={forceOpen}
              onAddSubtype={onAddSubtype}
              onEdit={(t, cid) => onEditType(t, cid, cat.nom)}
              onDelete={onDeleteType}
              onRestore={onRestoreType}
              onEditSubtype={onEditSubtype}
              onDeleteSubtype={onDeleteSubtype}
              onRestoreSubtype={onRestoreSubtype}
            />
          ))}
        </div>
      )}
    </div>
  );
}

// ─── Formulaire : Catégorie ───────────────────────────────────────────────────

function CategoryFormContent({
  initial,
  onCancel,
  onSaved,
}: {
  initial: { id?: number; nom: string; description: string | null; seuil?: number | null; consommable?: boolean } | null;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const queryClient = useQueryClient();
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [description, setDescription] = useState(initial?.description ?? "");
  const [seuil, setSeuil] = useState(initial?.seuil != null ? String(initial.seuil) : "");
  const [consommable, setConsommable] = useState(initial?.consommable ?? false);
  const [saving, setSaving] = useState(false);

  // Aucun champ n'est garanti présent/à jour dans l'arbre hiérarchique
  // (source de `initial` ici) — on va chercher la catégorie complète via
  // GET /categories/{id} dès l'ouverture en édition, pour un préremplissage
  // fiable de tous les champs (nom, description, seuil, consommable).
  useEffect(() => {
    if (!initial?.id) return;
    let cancelled = false;
    getCategoryById(initial.id).then((res) => {
      if (cancelled || !res.data) return;
      setNom(res.data.nom ?? "");
      setDescription(res.data.description ?? "");
      setSeuil(res.data.seuil != null ? String(res.data.seuil) : "");
      setConsommable(res.data.consommable ?? false);
    }).catch(() => {});
    return () => { cancelled = true; };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initial?.id]);

  const handleSave = async () => {
    if (!nom.trim()) return;
    setSaving(true);
    try {
      if (initial?.id) {
        await updateCategory(initial.id, {
          nom: nom.trim(),
          description: description.trim() || undefined,
          seuil: seuil.trim() ? Number(seuil) : undefined,
          consommable,
        });
      } else {
        await createCategory({
          nom: nom.trim(),
          description: description.trim() || undefined,
          seuil: seuil.trim() ? Number(seuil) : undefined,
          consommable,
        });
      }
      queryClient.invalidateQueries({ queryKey: ["categories-hierarchie"] });
      queryClient.invalidateQueries({ queryKey: ["categories"] });
      toast.success(t("toast.saved"));
      onSaved();
    } catch (err) {
      toast.error(getApiError(err));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label>
          {t("categories.field.nom")} <span className="text-destructive">*</span>
        </Label>
        <Input
          value={nom}
          onChange={(e) => setNom(e.target.value)}
          placeholder="Ex : MATÉRIEL INFORMATIQUE"
          autoFocus
        />
      </div>
      <div className="space-y-1.5">
        <Label>{t("common.description")}</Label>
        <Textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          placeholder={t("common.optionalDescription")}
          rows={3}
        />
      </div>
      <div className="space-y-1.5">
        <Label>{t("categoriesTree.form.seuilLabel")}</Label>
        <p className="text-xs text-muted-foreground">
          {t("categoriesTree.form.seuilHint")}
        </p>
        <Input
          type="number"
          min={0}
          value={seuil}
          onChange={(e) => setSeuil(e.target.value)}
          placeholder="Ex : 5"
        />
      </div>
      <div className="flex items-center gap-3 rounded-lg border border-border bg-muted/20 px-4 py-3">
        <Switch id="category-consommable" checked={consommable} onCheckedChange={setConsommable} />
        <div>
          <Label htmlFor="category-consommable" className="cursor-pointer">
            {t("categoriesTree.form.consommableLabel")}
          </Label>
          <p className="text-xs text-muted-foreground">
            {t("categoriesTree.form.consommableHint")}
          </p>
        </div>
      </div>
      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variant="ghost" onClick={onCancel}>
          {t("action.cancel")}
        </Button>
        <Button
          type="button"
          onClick={handleSave}
          disabled={!nom.trim() || saving}
          className="gap-2"
        >
          {saving && <Loader2 className="h-4 w-4 animate-spin" />}
          {t("action.save")}
        </Button>
      </div>
    </div>
  );
}

// ─── Formulaire : Type de bien ────────────────────────────────────────────────

function TypeFormContent({
  initial,
  catId,
  catNom,
  onCancel,
  onSaved,
}: {
  initial: {
    id?: number;
    nom: string;
    description: string | null | undefined;
    dureeVie: number | null | undefined;
    taux: number | null | undefined;
  } | null;
  catId: number;
  catNom: string;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const queryClient = useQueryClient();
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [description, setDescription] = useState(initial?.description ?? "");
  const [dureeVie, setDureeVie] = useState(
    initial?.dureeVie != null ? String(initial.dureeVie) : "",
  );
  const [taux, setTaux] = useState(initial?.taux != null ? String(initial.taux) : "");
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    if (!nom.trim()) return;
    setSaving(true);
    try {
      const payload = {
        nom: nom.trim(),
        description: description.trim() || undefined,
        category_id: catId,
        dureeVie: dureeVie ? Number(dureeVie) : undefined,
        taux: taux ? Number(taux) : undefined,
      };
      if (initial?.id) {
        await updateAssetType(initial.id, payload);
      } else {
        await createAssetType(payload);
      }
      queryClient.invalidateQueries({ queryKey: ["categories-hierarchie"] });
      queryClient.invalidateQueries({ queryKey: ["assetTypes"] });
      toast.success(t("toast.saved"));
      onSaved();
    } catch (err) {
      toast.error(getApiError(err));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-4">
      {/* Contexte catégorie parente */}
      <div
        className={cn(
          "rounded-lg border px-3 py-2 text-xs",
          LEVEL_COLORS[1].info,
        )}
      >
        {t("categoriesTree.form.parentCategoryLabel")} <strong>{catNom}</strong>
      </div>

      <div className="space-y-1.5">
        <Label>
          {t("assetTypes.field.nom")} <span className="text-destructive">*</span>
        </Label>
        <Input
          value={nom}
          onChange={(e) => setNom(e.target.value)}
          placeholder="Ex : Matériel de bureau"
          autoFocus
        />
      </div>
      <div className="space-y-1.5">
        <Label>{t("common.description")}</Label>
        <Textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          placeholder={t("common.optionalDescription")}
          rows={2}
        />
      </div>
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label>{t("categoriesTree.form.dureeVieLabel")}</Label>
          <Input
            type="number"
            min={0}
            value={dureeVie}
            onChange={(e) => setDureeVie(e.target.value)}
            placeholder="Ex : 5"
          />
        </div>
        <div className="space-y-1.5">
          <Label>{t("categoriesTree.form.tauxLabel")}</Label>
          <Input
            type="number"
            min={0}
            max={100}
            step={0.01}
            value={taux}
            onChange={(e) => setTaux(e.target.value)}
            placeholder="Ex : 20"
          />
        </div>
      </div>
      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variant="ghost" onClick={onCancel}>
          {t("action.cancel")}
        </Button>
        <Button
          type="button"
          onClick={handleSave}
          disabled={!nom.trim() || saving}
          className="gap-2"
        >
          {saving && <Loader2 className="h-4 w-4 animate-spin" />}
          {t("action.save")}
        </Button>
      </div>
    </div>
  );
}

// ─── Formulaire : Sous-type ───────────────────────────────────────────────────

function SubtypeFormContent({
  initial,
  tpId,
  tpNom,
  onCancel,
  onSaved,
}: {
  initial: { id?: number; nom: string; description: string | null | undefined } | null;
  tpId: number;
  tpNom: string;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const queryClient = useQueryClient();
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [description, setDescription] = useState(initial?.description ?? "");
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    if (!nom.trim()) return;
    setSaving(true);
    try {
      const payload = {
        nom: nom.trim(),
        description: description.trim() || undefined,
        asset_type_id: tpId,
      };
      if (initial?.id) {
        await updateAssetSubtype(initial.id, payload);
      } else {
        await createAssetSubtype(payload);
      }
      queryClient.invalidateQueries({ queryKey: ["categories-hierarchie"] });
      queryClient.invalidateQueries({ queryKey: ["assetSubtypes"] });
      toast.success(t("toast.saved"));
      onSaved();
    } catch (err) {
      toast.error(getApiError(err));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-4">
      {/* Contexte type parent */}
      <div
        className={cn(
          "rounded-lg border px-3 py-2 text-xs",
          LEVEL_COLORS[2].info,
        )}
      >
        {t("categoriesTree.form.parentTypeLabel")} <strong>{tpNom}</strong>
      </div>

      <div className="space-y-1.5">
        <Label>
          {t("assetSubtypes.field.nom")} <span className="text-destructive">*</span>
        </Label>
        <Input
          value={nom}
          onChange={(e) => setNom(e.target.value)}
          placeholder="Ex : Armes à feu"
          autoFocus
        />
      </div>
      <div className="space-y-1.5">
        <Label>{t("common.description")}</Label>
        <Textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          placeholder={t("common.optionalDescription")}
          rows={2}
        />
      </div>
      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variant="ghost" onClick={onCancel}>
          {t("action.cancel")}
        </Button>
        <Button
          type="button"
          onClick={handleSave}
          disabled={!nom.trim() || saving}
          className="gap-2"
        >
          {saving && <Loader2 className="h-4 w-4 animate-spin" />}
          {t("action.save")}
        </Button>
      </div>
    </div>
  );
}

// ─── Helper : clé unique de dialog pour forcer le remontage ──────────────────

function getDialogKey(dialog: CatDialog): string {
  if (!dialog) return "closed";
  switch (dialog.mode) {
    case "createCategory":
      return "createCategory";
    case "editCategory":
      return `editCategory-${dialog.cat.id}`;
    case "createType":
      return `createType-cat${dialog.catId}`;
    case "editType":
      return `editType-${dialog.tp.id}`;
    case "createSubtype":
      return `createSubtype-tp${dialog.tpId}`;
    case "editSubtype":
      return `editSubtype-${dialog.sub.id}`;
  }
}

// ─── Section principale exportée ─────────────────────────────────────────────

export function CategoriesBienSection() {
  const t = useT();
  const queryClient = useQueryClient();

  const [searchQuery, setSearchQuery] = useState("");
  const [dialog, setDialog] = useState<CatDialog>(null);
  const [deleteTarget, setDeleteTarget] = useState<DeleteTarget>(null);
  const [deleting, setDeleting] = useState(false);
  const [showDeleted, setShowDeleted] = useState(false);
  const [restoring, setRestoring] = useState(false);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["categories-hierarchie", showDeleted],
    queryFn: () => getCategorieHierarchie({ limit: 500, include_inactive: showDeleted }),
  });

  const allCategories: HierarchyCategory[] = data?.data?.items ?? [];
  const displayed = filterHierarchy(allCategories, searchQuery);
  const isSearching = searchQuery.trim().length > 0;

  // Le seuil de maintenance n'est pas garanti dans l'arbre hiérarchique —
  // on le récupère via GET /categories (source fiable, voir CategoryFormContent
  // pour le même souci en édition) pour l'afficher sur chaque nœud catégorie.
  const { data: catsFlat } = useQuery({
    queryKey: ["categories", "seuils"],
    queryFn: () => listCategories({ limit: 1000 }),
    staleTime: 60_000,
  });
  const seuilByCategoryId = useMemo(() => {
    const map = new Map<number, number | null>();
    (catsFlat?.data?.data ?? []).forEach((c) => map.set(c.id, c.seuil ?? null));
    return map;
  }, [catsFlat]);
  const consommableByCategoryId = useMemo(() => {
    const map = new Map<number, boolean>();
    (catsFlat?.data?.data ?? []).forEach((c) => map.set(c.id, c.consommable ?? false));
    return map;
  }, [catsFlat]);

  const invalidateAll = () => {
    queryClient.invalidateQueries({ queryKey: ["categories-hierarchie"] });
    queryClient.invalidateQueries({ queryKey: ["categories"] });
    queryClient.invalidateQueries({ queryKey: ["assetTypes"] });
    queryClient.invalidateQueries({ queryKey: ["assetSubtypes"] });
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      if (deleteTarget.kind === "category") await softDeleteCategory(deleteTarget.id);
      else if (deleteTarget.kind === "type") await softDeleteAssetType(deleteTarget.id);
      else await softDeleteAssetSubtype(deleteTarget.id);
      invalidateAll();
      toast.success(t("toast.deleted"));
    } catch (err) {
      toast.error(getApiError(err));
    } finally {
      setDeleting(false);
      setDeleteTarget(null);
    }
  };

  const handleRestore = async (target: DeleteTarget) => {
    if (!target) return;
    setRestoring(true);
    try {
      if (target.kind === "category") await restoreCategory(target.id);
      else if (target.kind === "type") await restoreAssetType(target.id);
      else await restoreAssetSubtype(target.id);
      invalidateAll();
      toast.success(t("toast.saved"));
    } catch (err) {
      toast.error(getApiError(err));
    } finally {
      setRestoring(false);
    }
  };

  const closeDialog = () => setDialog(null);

  // Titre du dialog
  const dialogTitle = (() => {
    if (!dialog) return "";
    switch (dialog.mode) {
      case "createCategory":   return t("categoriesTree.dialog.createCategory");
      case "editCategory":     return t("categoriesTree.dialog.editCategory");
      case "createType":       return t("categoriesTree.dialog.createType");
      case "editType":         return t("categoriesTree.dialog.editType");
      case "createSubtype":    return t("categoriesTree.dialog.createSubtype");
      case "editSubtype":      return t("categoriesTree.dialog.editSubtype");
    }
  })();

  // Message de confirmation de suppression
  const deleteMsg = (() => {
    if (!deleteTarget) return { title: "", desc: "" };
    if (deleteTarget.kind === "category")
      return {
        title: t("categoriesTree.deleteDialog.categoryTitle"),
        desc: t("categoriesTree.deleteDialog.categoryDesc", { name: deleteTarget.nom }),
      };
    if (deleteTarget.kind === "type")
      return {
        title: t("categoriesTree.deleteDialog.typeTitle"),
        desc: t("categoriesTree.deleteDialog.typeDesc", { name: deleteTarget.nom }),
      };
    return {
      title: t("categoriesTree.deleteDialog.subtypeTitle"),
      desc: t("categoriesTree.deleteDialog.subtypeDesc", { name: deleteTarget.nom }),
    };
  })();

  return (
    <div>
      {/* En-tête */}
      <div className="mb-4 flex items-center justify-between border-b border-border pb-4">
        <div className="flex items-center gap-2">
          <FolderOpen className="h-5 w-5 text-primary" />
          <div>
            <h2 className="font-semibold text-foreground">{t("categoriesTree.title")}</h2>
            <p className="text-xs text-muted-foreground">
              {t("categoriesTree.hint")}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-3">
          <label className="flex cursor-pointer items-center gap-2 text-sm">
            <Switch checked={showDeleted} onCheckedChange={setShowDeleted} />
            {t("consumables.showDeleted")}
          </label>
          <Button
            size="sm"
            className="gap-2"
            onClick={() => setDialog({ mode: "createCategory" })}
          >
            <Plus className="h-3.5 w-3.5" /> {t("categories.create")}
          </Button>
        </div>
      </div>

      {/* Légende des niveaux */}
      <div className="mb-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
        <span className={cn("flex items-center gap-1.5 rounded-full border px-2.5 py-1", LEVEL_COLORS[0].bg, LEVEL_COLORS[0].text)}>
          <div className={cn("h-2 w-2 rounded-full", LEVEL_COLORS[0].dot)} />
          {t("categoriesTree.legendCategory")}
        </span>
        <span className={cn("flex items-center gap-1.5 rounded-full border px-2.5 py-1", LEVEL_COLORS[1].bg, LEVEL_COLORS[1].text)}>
          <div className={cn("h-2 w-2 rounded-full", LEVEL_COLORS[1].dot)} />
          {t("assetSubtypes.field.type")}
        </span>
        <span className={cn("flex items-center gap-1.5 rounded-full border px-2.5 py-1", LEVEL_COLORS[2].bg, LEVEL_COLORS[2].text)}>
          <div className={cn("h-2 w-2 rounded-full", LEVEL_COLORS[2].dot)} />
          {t("categoriesTree.legendSubtype")}
        </span>
      </div>

      {/* Barre de recherche */}
      <div className="mb-3 flex items-center gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2">
        <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
        <input
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          placeholder={t("categoriesTree.searchPlaceholder")}
          className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
        />
        {isSearching && (
          <button type="button" onClick={() => setSearchQuery("")}>
            <X className="h-3.5 w-3.5 text-muted-foreground hover:text-foreground" />
          </button>
        )}
      </div>

      {/* Arbre */}
      {isLoading ? (
        <div className="flex h-60 items-center justify-center gap-2 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin" />
          <span>{t("common.loading")}</span>
        </div>
      ) : isError ? (
        <div className="flex h-60 items-center justify-center text-destructive">
          {t("toast.error")}
        </div>
      ) : allCategories.length === 0 ? (
        <div className="flex h-48 flex-col items-center justify-center gap-3 text-muted-foreground">
          <FolderOpen className="h-10 w-10 opacity-30" />
          <p className="text-sm">{t("categoriesTree.empty")}</p>
          <Button
            size="sm"
            variant="outline"
            onClick={() => setDialog({ mode: "createCategory" })}
            className="gap-1"
          >
            <Plus className="h-4 w-4" /> {t("categoriesTree.createCategoryButton")}
          </Button>
        </div>
      ) : displayed.length === 0 ? (
        <div className="flex h-32 flex-col items-center justify-center gap-2 text-muted-foreground">
          <p className="text-sm">{t("services.noSearchResults", { query: searchQuery })}</p>
          <button
            type="button"
            onClick={() => setSearchQuery("")}
            className="text-xs text-primary underline"
          >
            {t("services.clearSearch")}
          </button>
        </div>
      ) : (
        <div className="space-y-2 py-2">
          {displayed.map((cat) => (
            <CategoryNode
              key={cat.id}
              cat={cat}
              seuil={seuilByCategoryId.get(cat.id) ?? null}
              consommable={consommableByCategoryId.get(cat.id) ?? false}
              forceOpen={isSearching}
              onAddType={(catId, catNom) => setDialog({ mode: "createType", catId, catNom })}
              onEdit={(c) => setDialog({ mode: "editCategory", cat: c })}
              onDelete={(c) => setDeleteTarget({ kind: "category", id: c.id, nom: c.nom })}
              onRestore={(c) => handleRestore({ kind: "category", id: c.id, nom: c.nom })}
              onAddSubtype={(tpId, tpNom) => setDialog({ mode: "createSubtype", tpId, tpNom })}
              onEditType={(tp, catId, catNom) =>
                setDialog({ mode: "editType", tp, catId, catNom })
              }
              onDeleteType={(tp) => setDeleteTarget({ kind: "type", id: tp.id, nom: tp.nom })}
              onRestoreType={(tp) => handleRestore({ kind: "type", id: tp.id, nom: tp.nom })}
              onEditSubtype={(sub, tpId, tpNom) =>
                setDialog({ mode: "editSubtype", sub, tpId, tpNom })
              }
              onDeleteSubtype={(sub) =>
                setDeleteTarget({ kind: "subtype", id: sub.id, nom: sub.nom })
              }
              onRestoreSubtype={(sub) => handleRestore({ kind: "subtype", id: sub.id, nom: sub.nom })}
            />
          ))}
        </div>
      )}

      {/* Dialog CRUD */}
      <Dialog open={!!dialog} onOpenChange={(o) => !o && closeDialog()}>
        <DialogContent key={getDialogKey(dialog)} className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{dialogTitle}</DialogTitle>
          </DialogHeader>

          {dialog?.mode === "createCategory" && (
            <CategoryFormContent initial={null} onCancel={closeDialog} onSaved={closeDialog} />
          )}
          {dialog?.mode === "editCategory" && (
            <CategoryFormContent
              initial={{
                id: dialog.cat.id,
                nom: dialog.cat.nom,
                description: dialog.cat.description,
                seuil: dialog.cat.seuil,
                consommable: dialog.cat.consommable,
              }}
              onCancel={closeDialog}
              onSaved={closeDialog}
            />
          )}
          {dialog?.mode === "createType" && (
            <TypeFormContent
              initial={null}
              catId={dialog.catId}
              catNom={dialog.catNom}
              onCancel={closeDialog}
              onSaved={closeDialog}
            />
          )}
          {dialog?.mode === "editType" && (
            <TypeFormContent
              initial={{
                id: dialog.tp.id,
                nom: dialog.tp.nom,
                description: dialog.tp.description,
                dureeVie: dialog.tp.dureeVie,
                taux: dialog.tp.taux,
              }}
              catId={dialog.catId}
              catNom={dialog.catNom}
              onCancel={closeDialog}
              onSaved={closeDialog}
            />
          )}
          {dialog?.mode === "createSubtype" && (
            <SubtypeFormContent
              initial={null}
              tpId={dialog.tpId}
              tpNom={dialog.tpNom}
              onCancel={closeDialog}
              onSaved={closeDialog}
            />
          )}
          {dialog?.mode === "editSubtype" && (
            <SubtypeFormContent
              initial={{
                id: dialog.sub.id,
                nom: dialog.sub.nom,
                description: dialog.sub.description,
              }}
              tpId={dialog.tpId}
              tpNom={dialog.tpNom}
              onCancel={closeDialog}
              onSaved={closeDialog}
            />
          )}
        </DialogContent>
      </Dialog>

      {/* Confirmation de suppression */}
      <AlertDialog open={!!deleteTarget} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{deleteMsg.title}</AlertDialogTitle>
            <AlertDialogDescription>{deleteMsg.desc}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              disabled={deleting}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {deleting ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                t("action.delete")
              )}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
