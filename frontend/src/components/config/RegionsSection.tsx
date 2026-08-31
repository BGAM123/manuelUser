/**
 * Section Régions — CRUD avec soft-delete, restauration et limite de 10.
 */

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Plus, Pencil, Trash2, Save, X, ArrowLeft, Loader2, MapPin,
} from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { SearchableSelect } from "@/components/shared/SearchableSelect";
import { useT } from "@/utils/i18n";
import {
  listRegions, createRegion, updateRegion, softDeleteRegion,
  type ApiRegion, type RegionPayload,
} from "@/api/regions/regions.api";

export const MAX_REGIONS = 10;

// Les 10 régions officielles du Cameroun (correspond exactement à MAX_REGIONS).
const CAMEROON_REGIONS = [
  "Adamaoua", "Centre", "Est", "Extrême-Nord", "Littoral",
  "Nord", "Nord-Ouest", "Ouest", "Sud", "Sud-Ouest",
];

export function RegionsSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<"liste" | "form">("liste");
  const [selected, setSelected] = useState<ApiRegion | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiRegion | null>(null);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["regions"],
    queryFn: () => listRegions({ page: 1, limit: 1000, is_delete: false }),
  });

  const regions: ApiRegion[] = [...(data?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom, "fr"),
  );
  const activeTotal = data?.data?.meta?.total_items ?? 0;
  const atLimit = activeTotal >= MAX_REGIONS;

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["regions"] });

  const softDeleteMutation = useMutation({
    mutationFn: (id: number) => softDeleteRegion(id),
    onSuccess: () => { invalidate(); toast.success(t("toast.deleted")); setDeleteTarget(null); },
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string }; status?: number } })?.response?.data?.message;
      const status = (err as { response?: { status?: number } })?.response?.status;
      toast.error(status === 409 ? (apiMsg ?? t("toast.error")) : t("toast.error"));
    },
  });

  const columns: Column<ApiRegion>[] = [
    {
      key: "nom", label: t("regions.field.nom"),
      render: (r) => <span className="font-semibold">{r.nom}</span>,
      sortValue: (r) => r.nom,
    },
    {
      key: "code", label: t("regions.field.code"),
      render: (r) => <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">{r.code}</code>,
      sortValue: (r) => r.code,
    },
  ];

  if (isLoading) return <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground"><Loader2 className="h-5 w-5 animate-spin" /><span>{t("common.loading")}</span></div>;
  if (isError) return <div className="flex h-40 items-center justify-center text-destructive">{t("toast.error")}</div>;

  if (view === "form") {
    return (
      <GeoForm
        title={t("regions.create")}
        subtitle={t("regions.subtitle")}
        icon={<MapPin className="h-5 w-5" />}
        initial={selected ? { nom: selected.nom, code: selected.code } : null}
        fields={[
          { key: "nom", label: t("regions.field.nom"), placeholder: "Centre", options: CAMEROON_REGIONS },
          { key: "code", label: t("regions.field.code"), placeholder: "CE", uppercase: true },
        ]}
        isEdit={!!selected}
        onCancel={() => setView("liste")}
        onSaved={(payload) => {
          if (selected) {
            return updateRegion(selected.id, payload).then(() => {
              invalidate(); toast.success(t("toast.saved")); setView("liste");
            }).catch(() => { toast.error(t("toast.error")); });
          } else {
            return createRegion(payload as unknown as RegionPayload).then((res) => {
              invalidate();
              const { created, restored } = res.data;
              if (restored > 0 && created === 0) toast.success(t("regions.toast.restored"));
              else if (restored > 0) toast.success(`${created} créée(s), ${restored} restaurée(s).`);
              else toast.success(t("toast.saved"));
              setView("liste");
            }).catch((err: unknown) => {
              const status = (err as { response?: { status?: number } })?.response?.status;
              const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
              toast.error(status === 409 ? (msg ?? t("regions.limit")) : t("toast.error"));
            });
          }
        }}
      />
    );
  }

  return (
    <>
      <div>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <h2 className="text-lg font-semibold">{t("cartographie.regions")}</h2>
            {<span className="text-xs text-muted-foreground">({activeTotal}/{MAX_REGIONS})</span>}
          </div>
          <div className="flex items-center gap-3">
            <Button className="gap-2" disabled={atLimit} onClick={() => { setSelected(null); setView("form"); }}>
              <Plus className="h-4 w-4" /> {t("regions.create")}
            </Button>
          </div>
        </div>
        <DataTable
          data={regions}
          columns={columns}
          getRowId={(r) => String(r.id)}
          exportFilename="regions-cameroun"
          exportTitle="Cameroun — Régions"
          searchKeys={["nom", "code"]}
          rowActions={(r) => (
            <>
              <RowIconButton icon={Pencil} label={t("action.edit")} onClick={() => { setSelected(r); setView("form"); }} />
              <RowIconButton icon={Trash2} label={t("regions.delete.title")} tone="danger" onClick={() => setDeleteTarget(r)} />
            </>
          )}
        />
      </div>
      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => { if (!open) setDeleteTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("regions.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>{t("regions.delete.desc")} <span className="font-semibold text-foreground">{deleteTarget?.nom}</span></AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={() => { if (deleteTarget) softDeleteMutation.mutate(deleteTarget.id); }}>
              {t("action.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

// ─── Formulaire générique pour Région / Département / Arrondissement ────────

type GeoField = {
  key: string;
  label: string;
  placeholder: string;
  uppercase?: boolean;
  /** Si fourni, le champ devient un select recherchable parmi ces valeurs fixes. */
  options?: string[];
};

export function GeoForm({
  title, subtitle, icon, initial, fields, isEdit, onCancel, onSaved,
  children,
}: {
  title: string;
  subtitle: string;
  icon: React.ReactNode;
  initial: Record<string, string> | null;
  fields: GeoField[];
  isEdit: boolean;
  onCancel: () => void;
  onSaved: (values: Record<string, string>) => Promise<void> | void;
  children?: React.ReactNode;
}) {
  const t = useT();
  const [values, setValues] = useState<Record<string, string>>(
    initial ?? Object.fromEntries(fields.map((f) => [f.key, ""])),
  );
  const [isPending, setIsPending] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsPending(true);
    try {
      await onSaved(values);
    } finally {
      setIsPending(false);
    }
  };

  return (
    <div className="mx-auto w-full max-w-2xl space-y-3">
      <Button type="button" variant="ghost" size="sm" onClick={onCancel} className="-ml-2 gap-1">
        <ArrowLeft className="h-4 w-4" /> {t("action.back")}
      </Button>
      <form onSubmit={handleSubmit} className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">{icon}</div>
          <div>
            <h2 className="text-base font-semibold">{isEdit ? `${t("action.edit")} — ${initial?.nom ?? ""}` : title}</h2>
            <p className="text-xs text-muted-foreground">{subtitle}</p>
          </div>
        </div>
        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <div className="grid gap-4 sm:grid-cols-2">
            {fields.map((f) => (
              <div key={f.key} className="space-y-1.5">
                <Label>{f.label} <span className="text-destructive">*</span></Label>
                {f.options ? (
                  <SearchableSelect
                    value={values[f.key] || null}
                    onChange={(v) => setValues((prev) => ({ ...prev, [f.key]: v ?? "" }))}
                    options={f.options.map((o) => ({ value: o, label: o }))}
                    placeholder={f.placeholder}
                    searchPlaceholder="Rechercher..."
                  />
                ) : (
                  <Input
                    value={values[f.key] ?? ""}
                    onChange={(e) => setValues((prev) => ({ ...prev, [f.key]: f.uppercase ? e.target.value.toUpperCase() : e.target.value }))}
                    placeholder={f.placeholder}
                    required
                  />
                )}
              </div>
            ))}
          </div>
          {children}
        </div>
        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button type="button" variant="outline" onClick={onCancel} disabled={isPending}><X className="h-4 w-4" /> {t("action.cancel")}</Button>
          <Button type="submit" className="gap-2" disabled={isPending}>
            {isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            {t("action.save")}
          </Button>
        </div>
      </form>
    </div>
  );
}
