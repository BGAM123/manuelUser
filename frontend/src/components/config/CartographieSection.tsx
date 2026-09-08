/**
 * Section Cartographie - Vue arbre hierarchique Region / Departement / Arrondissement.
 * Utilise GET /cartographie pour l'affichage et les endpoints CRUD classiques.
 */

import { useState, useMemo } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Plus, Pencil, Trash2, Search, ChevronRight, MapPin, Loader2, X,
} from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from "@/components/ui/dialog";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { useT } from "@/utils/i18n";
import { cn } from "@/utils/utils";
import { CanAccess } from "@/components/auth/CanAccess";
import {
  getCartographie, createRegion, updateRegion, softDeleteRegion,
  type CartographieRegion,
} from "@/api/regions/regions.api";
import { MAX_REGIONS } from "@/components/config/RegionsSection";
import {
  createDepartement, updateDepartement, softDeleteDepartement,
} from "@/api/departements/departements.api";
import {
  createArrondissement, updateArrondissement, softDeleteArrondissement,
} from "@/api/arrondissements/arrondissements.api";

// ─── Types internes ──────────────────────────────────────────────────────────

type DialogMode =
  | { type: "create-region" }
  | { type: "edit-region"; id: number; nom: string; code: string }
  | { type: "delete-region"; id: number; nom: string }
  | { type: "create-dept"; regionId: number; regionNom: string }
  | { type: "edit-dept"; id: number; nom: string; code: string }
  | { type: "delete-dept"; id: number; nom: string }
  | { type: "create-arond"; deptId: number; deptNom: string }
  | { type: "edit-arond"; id: number; nom: string; code: string }
  | { type: "delete-arond"; id: number; nom: string }
  | null;

// ─── Boutons d'action inline ─────────────────────────────────────────────────

function ActionBtn({
  icon: Icon,
  label,
  onClick,
  tone = "default",
}: {
  icon: typeof Plus;
  label: string;
  onClick: (e: React.MouseEvent) => void;
  tone?: "default" | "danger" | "primary";
}) {
  return (
    <button
      type="button"
      title={label}
      onClick={(e) => { e.stopPropagation(); onClick(e); }}
      className={cn(
        "inline-flex h-6 w-6 items-center justify-center rounded transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100",
        tone === "danger"
          ? "text-destructive hover:bg-destructive/10"
          : tone === "primary"
          ? "text-primary hover:bg-primary/10"
          : "text-muted-foreground hover:bg-muted",
      )}
      aria-label={label}
    >
      <Icon className="h-3.5 w-3.5" />
    </button>
  );
}

// ─── Noeud arrondissement ────────────────────────────────────────────────────

function ArondNode({
  arond,
  onDialog,
}: {
  arond: { id: number; nom: string; code?: string };
  onDialog: (d: DialogMode) => void;
}) {
  const t = useT();
  return (
    <div className="group flex items-center gap-2 rounded-lg px-3 py-1.5 pl-10 transition-colors hover:bg-muted/60">
      <div className="h-1.5 w-1.5 shrink-0 rounded-full bg-orange-400" />
      <span className="flex-1 truncate text-sm text-foreground">{arond.nom}</span>
      <div className="flex shrink-0 items-center gap-0.5">
        <CanAccess permission="creation_cartographie">
          <ActionBtn
            icon={Pencil}
            label={t("cartographie.editArond")}
            tone="primary"
            onClick={() =>
              onDialog({ type: "edit-arond", id: arond.id, nom: arond.nom, code: arond.code ?? "" })
            }
          />
          <ActionBtn
            icon={Trash2}
            label={t("cartographie.deleteArond")}
            tone="danger"
            onClick={() => onDialog({ type: "delete-arond", id: arond.id, nom: arond.nom })}
          />
        </CanAccess>
      </div>
    </div>
  );
}

// ─── Noeud departement ────────────────────────────────────────────────────────

function DeptNode({
  dept,
  open,
  onToggle,
  onDialog,
}: {
  dept: CartographieRegion["departements"][number];
  open: boolean;
  onToggle: () => void;
  onDialog: (d: DialogMode) => void;
}) {
  const t = useT();
  return (
    <div>
      <div
        className="group flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 pl-6 transition-colors hover:bg-muted/60"
        onClick={onToggle}
      >
        <ChevronRight
          className={cn(
            "h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform duration-200",
            open && "rotate-90",
          )}
        />
        <div className="h-2 w-2 shrink-0 rounded-full bg-emerald-500" />
        <span className="flex-1 truncate text-sm font-medium text-foreground">{dept.nom}</span>
        <span className="shrink-0 text-[11px] text-muted-foreground">
          {t("cartographie.arondCount", { count: dept.arrondissements.length })}
        </span>
        <div className="flex shrink-0 items-center gap-0.5">
          <CanAccess permission="creation_cartographie">
            <ActionBtn
              icon={Plus}
              label={t("cartographie.addArond")}
              tone="primary"
              onClick={() =>
                onDialog({ type: "create-arond", deptId: dept.id, deptNom: dept.nom })
              }
            />
            <ActionBtn
              icon={Pencil}
              label={t("cartographie.editDept")}
              tone="primary"
              onClick={() =>
                onDialog({ type: "edit-dept", id: dept.id, nom: dept.nom, code: dept.code ?? "" })
              }
            />
            <ActionBtn
              icon={Trash2}
              label={t("cartographie.deleteDept")}
              tone="danger"
              onClick={() => onDialog({ type: "delete-dept", id: dept.id, nom: dept.nom })}
            />
          </CanAccess>
        </div>
      </div>

      {open && dept.arrondissements.length > 0 && (
        <div className="ml-4 border-l border-dashed border-border/60 py-0.5">
          {dept.arrondissements.map((a) => (
            <ArondNode key={a.id} arond={a} onDialog={onDialog} />
          ))}
        </div>
      )}

      {open && dept.arrondissements.length === 0 && (
        <p className="py-1 pl-14 text-xs text-muted-foreground">{t("cartographie.noArond")}</p>
      )}
    </div>
  );
}

// ─── Noeud region ─────────────────────────────────────────────────────────────

function RegionNode({
  region,
  searchQuery,
  onDialog,
}: {
  region: CartographieRegion;
  searchQuery: string;
  onDialog: (d: DialogMode) => void;
}) {
  const t = useT();
  const [open, setOpen] = useState(false);
  const [openDepts, setOpenDepts] = useState<Set<number>>(new Set());

  const toggleDept = (id: number) =>
    setOpenDepts((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });

  const forceOpen = searchQuery.length > 0;

  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
      <div
        className="group flex cursor-pointer items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/40"
        onClick={() => setOpen((v) => !v)}
      >
        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <MapPin className="h-4 w-4" />
        </div>
        <ChevronRight
          className={cn(
            "h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200",
            (open || forceOpen) && "rotate-90",
          )}
        />
        <span className="flex-1 text-sm font-bold text-foreground">{region.nom}</span>
        <span className="shrink-0 text-xs text-muted-foreground">
          {t("cartographie.deptCount", { count: region.departements.length })}
        </span>
        <div className="flex shrink-0 items-center gap-0.5">
          <CanAccess permission="creation_cartographie">
            <ActionBtn
              icon={Plus}
              label={t("cartographie.addDept")}
              tone="primary"
              onClick={() =>
                onDialog({ type: "create-dept", regionId: region.id, regionNom: region.nom })
              }
            />
            <ActionBtn
              icon={Pencil}
              label={t("cartographie.editRegion")}
              tone="primary"
              onClick={() =>
                onDialog({ type: "edit-region", id: region.id, nom: region.nom, code: region.code ?? "" })
              }
            />
            <ActionBtn
              icon={Trash2}
              label={t("cartographie.deleteRegion")}
              tone="danger"
              onClick={() => onDialog({ type: "delete-region", id: region.id, nom: region.nom })}
            />
          </CanAccess>
        </div>
      </div>

      {(open || forceOpen) && (
        <div className="border-t border-border/60 px-2 py-1.5">
          {region.departements.length === 0 ? (
            <p className="py-2 pl-4 text-xs text-muted-foreground">{t("cartographie.noDept")}</p>
          ) : (
            region.departements.map((dept) => (
              <DeptNode
                key={dept.id}
                dept={dept}
                open={forceOpen || openDepts.has(dept.id)}
                onToggle={() => toggleDept(dept.id)}
                onDialog={onDialog}
              />
            ))
          )}
        </div>
      )}
    </div>
  );
}

// ─── Formulaire generique (nom + code) ─────────────────────────────────────

function GeoDialogForm({
  open,
  title,
  initialNom,
  initialCode,
  showCode,
  loading,
  onClose,
  onSave,
}: {
  open: boolean;
  title: string;
  initialNom: string;
  initialCode: string;
  showCode: boolean;
  loading: boolean;
  onClose: () => void;
  onSave: (nom: string, code: string) => void;
}) {
  const t = useT();
  const [nom, setNom] = useState(initialNom);
  const [code, setCode] = useState(initialCode);

  return (
    <Dialog open={open} onOpenChange={(v) => { if (!v) onClose(); }}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4 py-2">
          <div className="space-y-1.5">
            <Label>{t("common.name")} <span className="text-destructive">*</span></Label>
            <Input
              value={nom}
              onChange={(e) => setNom(e.target.value)}
              placeholder={t("cartographie.enterName")}
              autoFocus
            />
          </div>
          {showCode && (
            <div className="space-y-1.5">
              <Label>{t("common.code")}</Label>
              <Input
                value={code}
                onChange={(e) => setCode(e.target.value.toUpperCase())}
                placeholder={t("cartographie.codePlaceholder")}
                maxLength={10}
              />
            </div>
          )}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={loading}>
            {t("action.cancel")}
          </Button>
          <Button
            onClick={() => onSave(nom.trim(), code.trim())}
            disabled={loading || !nom.trim()}
            className="gap-2"
          >
            {loading && <Loader2 className="h-4 w-4 animate-spin" />}
            {t("action.save")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ─── Section principale ──────────────────────────────────────────────────────

export function CartographieSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [dialog, setDialog] = useState<DialogMode>(null);
  const [search, setSearch] = useState("");

  const { data, isLoading, isError } = useQuery({
    queryKey: ["cartographie"],
    queryFn: () => getCartographie(),
  });

  const regions: CartographieRegion[] = data?.data ?? [];
  const atRegionLimit = regions.length >= MAX_REGIONS;

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return regions;
    return regions
      .map((r) => {
        const rMatch = r.nom.toLowerCase().includes(q);
        const depts = r.departements
          .map((d) => {
            const dMatch = d.nom.toLowerCase().includes(q);
            const aronds = d.arrondissements.filter((a) =>
              a.nom.toLowerCase().includes(q),
            );
            if (dMatch || aronds.length > 0) {
              return { ...d, arrondissements: dMatch ? d.arrondissements : aronds };
            }
            return null;
          })
          .filter((d): d is CartographieRegion["departements"][number] => d !== null);
        if (rMatch || depts.length > 0) {
          return { ...r, departements: rMatch ? r.departements : depts };
        }
        return null;
      })
      .filter((r): r is CartographieRegion => r !== null);
  }, [regions, search]);

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["cartographie"] });

  const createRegionMut = useMutation({
    mutationFn: (p: { nom: string; code: string }) => createRegion(p),
    onSuccess: () => { invalidate(); toast.success(t("toast.saved")); setDialog(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const updateRegionMut = useMutation({
    mutationFn: (p: { id: number; nom: string; code: string }) =>
      updateRegion(p.id, { nom: p.nom, code: p.code }),
    onSuccess: () => { invalidate(); toast.success(t("toast.saved")); setDialog(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const deleteRegionMut = useMutation({
    mutationFn: (id: number) => softDeleteRegion(id),
    onSuccess: () => { invalidate(); toast.success(t("toast.deleted")); setDialog(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const createDeptMut = useMutation({
    mutationFn: (p: { nom: string; code: string; region_id: number }) =>
      createDepartement(p as Parameters<typeof createDepartement>[0]),
    onSuccess: () => { invalidate(); toast.success(t("toast.saved")); setDialog(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const updateDeptMut = useMutation({
    mutationFn: (p: { id: number; nom: string; code: string }) =>
      updateDepartement(p.id, { nom: p.nom, code: p.code }),
    onSuccess: () => { invalidate(); toast.success(t("toast.saved")); setDialog(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const deleteDeptMut = useMutation({
    mutationFn: (id: number) => softDeleteDepartement(id),
    onSuccess: () => { invalidate(); toast.success(t("toast.deleted")); setDialog(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const createArondMut = useMutation({
    mutationFn: (p: { nom: string; code: string; departement_id: number }) =>
      createArrondissement(p as Parameters<typeof createArrondissement>[0]),
    onSuccess: () => { invalidate(); toast.success(t("toast.saved")); setDialog(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const updateArondMut = useMutation({
    mutationFn: (p: { id: number; nom: string; code: string }) =>
      updateArrondissement(p.id, { nom: p.nom, code: p.code }),
    onSuccess: () => { invalidate(); toast.success(t("toast.saved")); setDialog(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const deleteArondMut = useMutation({
    mutationFn: (id: number) => softDeleteArrondissement(id),
    onSuccess: () => { invalidate(); toast.success(t("toast.deleted")); setDialog(null); },
    onError: () => toast.error(t("toast.error")),
  });

  const isMutating =
    createRegionMut.isPending || updateRegionMut.isPending || deleteRegionMut.isPending ||
    createDeptMut.isPending || updateDeptMut.isPending || deleteDeptMut.isPending ||
    createArondMut.isPending || updateArondMut.isPending || deleteArondMut.isPending;

  return (
    <div className="space-y-4">
      {/* Barre d'outils */}
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="relative flex-1 min-w-[200px] max-w-sm">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t("cartographie.searchPlaceholder")}
            className="h-9 pl-9"
          />
          {search && (
            <button
              type="button"
              onClick={() => setSearch("")}
              className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
            >
              <X className="h-4 w-4" />
            </button>
          )}
        </div>
        <div className="flex items-center gap-2">
          <span className="text-xs text-muted-foreground">({regions.length}/{MAX_REGIONS})</span>
          <CanAccess permission="creation_cartographie">
            <Button
              className="gap-2"
              disabled={atRegionLimit}
              onClick={() => setDialog({ type: "create-region" })}
            >
              <Plus className="h-4 w-4" /> {t("cartographie.newRegion")}
            </Button>
          </CanAccess>
        </div>
      </div>

      {isLoading && (
        <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin" />
          <span>{t("common.loading")}</span>
        </div>
      )}

      {isError && (
        <div className="flex h-40 items-center justify-center text-destructive">
          {t("toast.error")}
        </div>
      )}

      {!isLoading && !isError && (
        <>
          {filtered.length === 0 ? (
            <div className="flex h-32 items-center justify-center text-sm text-muted-foreground">
              {search ? t("cartographie.noSearchResults") : t("cartographie.noRegionFound")}
            </div>
          ) : (
            <div className="space-y-3">
              {filtered.map((region) => (
                <RegionNode
                  key={region.id}
                  region={region}
                  searchQuery={search}
                  onDialog={setDialog}
                />
              ))}
            </div>
          )}
        </>
      )}

      {/* Dialogs creation/edition */}

      {dialog?.type === "create-region" && (
        <GeoDialogForm
          open
          title={t("cartographie.newRegion")}
          initialNom=""
          initialCode=""
          showCode
          loading={createRegionMut.isPending}
          onClose={() => setDialog(null)}
          onSave={(nom, code) => createRegionMut.mutate({ nom, code })}
        />
      )}

      {dialog?.type === "edit-region" && (
        <GeoDialogForm
          open
          title={t("services.editWithName", { nom: dialog.nom })}
          initialNom={dialog.nom}
          initialCode={dialog.code}
          showCode
          loading={updateRegionMut.isPending}
          onClose={() => setDialog(null)}
          onSave={(nom, code) => updateRegionMut.mutate({ id: dialog.id, nom, code })}
        />
      )}

      {dialog?.type === "create-dept" && (
        <GeoDialogForm
          open
          title={t("cartographie.newDeptOf", { nom: dialog.regionNom })}
          initialNom=""
          initialCode=""
          showCode
          loading={createDeptMut.isPending}
          onClose={() => setDialog(null)}
          onSave={(nom, code) => createDeptMut.mutate({ nom, code, region_id: dialog.regionId })}
        />
      )}

      {dialog?.type === "edit-dept" && (
        <GeoDialogForm
          open
          title={t("services.editWithName", { nom: dialog.nom })}
          initialNom={dialog.nom}
          initialCode={dialog.code}
          showCode
          loading={updateDeptMut.isPending}
          onClose={() => setDialog(null)}
          onSave={(nom, code) => updateDeptMut.mutate({ id: dialog.id, nom, code })}
        />
      )}

      {dialog?.type === "create-arond" && (
        <GeoDialogForm
          open
          title={t("cartographie.newArondOf", { nom: dialog.deptNom })}
          initialNom=""
          initialCode=""
          showCode
          loading={createArondMut.isPending}
          onClose={() => setDialog(null)}
          onSave={(nom, code) => createArondMut.mutate({ nom, code, departement_id: dialog.deptId })}
        />
      )}

      {dialog?.type === "edit-arond" && (
        <GeoDialogForm
          open
          title={t("services.editWithName", { nom: dialog.nom })}
          initialNom={dialog.nom}
          initialCode={dialog.code}
          showCode
          loading={updateArondMut.isPending}
          onClose={() => setDialog(null)}
          onSave={(nom, code) => updateArondMut.mutate({ id: dialog.id, nom, code })}
        />
      )}

      {(dialog?.type === "delete-region" ||
        dialog?.type === "delete-dept" ||
        dialog?.type === "delete-arond") && (
        <AlertDialog open onOpenChange={(v) => { if (!v) setDialog(null); }}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t("cartographie.confirmDelete")}</AlertDialogTitle>
              <AlertDialogDescription>
                {t("cartographie.confirmDeleteBefore")}{" "}
                <span className="font-semibold text-foreground">{dialog.nom}</span>{" "}
                {t("cartographie.confirmDeleteAfter")}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
              <AlertDialogAction
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                onClick={() => {
                  const id = dialog.id;
                  if (dialog.type === "delete-region") deleteRegionMut.mutate(id);
                  else if (dialog.type === "delete-dept") deleteDeptMut.mutate(id);
                  else deleteArondMut.mutate(id);
                }}
                disabled={isMutating}
              >
                {isMutating && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {t("action.delete")}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      )}
    </div>
  );
}
