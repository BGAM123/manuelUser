import { useState } from "react";
import { Plus, Eye, Pencil, Trash2, Save, Layers } from "lucide-react";
import { toast } from "sonner";
import { AppShell } from "@/components/shared/AppShell";
import { ViewShell } from "@/components/shared/ViewShell";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { StatusBadge, statutTone } from "@/components/shared/StatusBadge";
import { useViewStack } from "@/hooks/useViewStack";
import { useT } from "@/utils/i18n";
import { mockMaintenances, formatFCFA, formatAmount, type Maintenance } from "@/utils/mock-data";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

type View = "liste" | "detail" | "creation" | "edition" | "consolidation";

function MaintenanceShell() {
  const t = useT();
  const { view, go, back, reset } = useViewStack<View>("liste");
  const [data, setData] = useState<Maintenance[]>(mockMaintenances);
  const [selected, setSelected] = useState<Maintenance | null>(null);

  const columns: Column<Maintenance>[] = [
    { key: "id", label: "ID", className: "font-mono text-xs" },
    { key: "bien", label: "Bien", render: (r) => <span className="font-medium">{r.bien}</span> },
    { key: "poste", label: "Poste" },
    { key: "unite", label: "Unité", className: "text-xs" },
    { key: "dateBesoin", label: "Date" },
    { key: "priorite", label: "Priorité", render: (r) => <StatusBadge tone={r.priorite === "Haute" ? "urgent" : r.priorite === "Basse" ? "low" : "info"}>{r.priorite}</StatusBadge> },
    { key: "evaluation", label: "Évaluation (FCFA)", className: "text-right", render: (r) => <span className="tabular-nums">{formatAmount(r.evaluation)}</span>, exportFormat: (r) => r.evaluation },
    { key: "statut", label: "Statut", render: (r) => <StatusBadge tone={statutTone(r.statut)}>{r.statut}</StatusBadge> },
  ];

  const consolidation = Array.from(
    data.reduce((m, x) => m.set(x.unite, (m.get(x.unite) ?? 0) + x.evaluation), new Map<string, number>()),
    ([unite, total]) => ({ unite, total, count: data.filter((d) => d.unite === unite).length })
  ).sort((a, b) => b.total - a.total);

  return (
    <AppShell>
      {view === "liste" && (
        <ViewShell
          title={t("maintenance.title")}
          subtitle={t("maintenance.subtitle")}
          actions={
            <>
              <Button variant="outline" className="gap-2" onClick={() => go("consolidation")}>
                <Layers className="h-4 w-4" /> {t("maintenance.consolidation")}
              </Button>
              <Button onClick={() => { setSelected(null); go("creation"); }} className="gap-2">
                <Plus className="h-4 w-4" /> {t("maintenance.new")}
              </Button>
            </>
          }
        >
          <DataTable
            data={data}
            columns={columns}
            getRowId={(r) => r.id}
            exportFilename="maintenance-minepia"
            exportTitle="MINEPIA — Besoins de maintenance"
            searchKeys={["bien", "poste", "unite"]}
            rowActions={(r) => (
              <>
                <RowIconButton icon={Eye} label={t("action.view")} tone="primary" onClick={() => { setSelected(r); go("detail"); }} />
                <RowIconButton icon={Pencil} label={t("action.edit")} onClick={() => { setSelected(r); go("edition"); }} />
                <RowIconButton icon={Trash2} label={t("action.delete")} tone="danger" onClick={() => { setData((p) => p.filter((x) => x.id !== r.id)); toast.success(t("toast.deleted")); }} />
              </>
            )}
          />
        </ViewShell>
      )}

      {view === "detail" && selected && (
        <ViewShell title={selected.bien} subtitle={`Besoin ${selected.id}`} onBack={() => reset("liste")}
          actions={<Button variant="outline" onClick={() => go("edition")} className="gap-2"><Pencil className="h-4 w-4" /> {t("action.edit")}</Button>}>
          <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
            <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
              {[
                ["Bien", selected.bien],
                ["Poste de gestion", selected.poste],
                ["Unité", selected.unite],
                ["Date prévisionnelle", selected.dateBesoin],
                ["Évaluation financière", formatFCFA(selected.evaluation)],
                ["Priorité", selected.priorite],
                ["Statut", selected.statut],
              ].map(([k, v]) => (
                <div key={k} className="border-b border-border/60 pb-2">
                  <dt className="text-xs uppercase tracking-wide text-muted-foreground">{k}</dt>
                  <dd className="mt-0.5 text-sm font-medium">{v}</dd>
                </div>
              ))}
            </dl>
          </div>
        </ViewShell>
      )}

      {view === "consolidation" && (
        <ViewShell title={t("maintenance.consolidation")} subtitle="Total des besoins par unité administrative" onBack={back}>
          <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
            <table className="w-full text-sm">
              <thead className="text-xs uppercase text-muted-foreground">
                <tr><th className="py-2 text-left">Unité</th><th className="py-2 text-right">Besoins</th><th className="py-2 text-right">Montant total</th></tr>
              </thead>
              <tbody>
                {consolidation.map((c) => (
                  <tr key={c.unite} className="border-t border-border">
                    <td className="py-2.5 font-medium">{c.unite}</td>
                    <td className="py-2.5 text-right tabular-nums">{c.count}</td>
                    <td className="py-2.5 text-right tabular-nums font-semibold">{formatFCFA(c.total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </ViewShell>
      )}

      {(view === "creation" || view === "edition") && (
        <MaintForm
          initial={view === "edition" ? selected : null}
          onCancel={back}
          onSave={(m) => {
            setData((prev) => view === "creation" ? [m, ...prev] : prev.map((x) => (x.id === m.id ? m : x)));
            toast.success(t("toast.saved"));
            reset("liste");
          }}
        />
      )}
    </AppShell>
  );
}

function MaintForm({ initial, onCancel, onSave }: { initial: Maintenance | null; onCancel: () => void; onSave: (m: Maintenance) => void }) {
  const t = useT();
  const [form, setForm] = useState<Maintenance>(
    initial ?? {
      id: `M-${Date.now().toString().slice(-4)}`,
      bien: "",
      poste: "",
      dateBesoin: new Date().toISOString().slice(0, 10),
      evaluation: 0,
      priorite: "Normale",
      statut: "En attente",
      unite: "",
    }
  );
  return (
    <ViewShell title={initial ? "Modifier le besoin" : t("maintenance.new")} onBack={onCancel}>
      <form onSubmit={(e) => { e.preventDefault(); onSave(form); }} className="rounded-xl border border-border bg-card p-6 shadow-sm">
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1.5 sm:col-span-2"><Label>Bien concerné</Label><Input value={form.bien} onChange={(e) => setForm({ ...form, bien: e.target.value })} required /></div>
          <div className="space-y-1.5"><Label>Poste de gestion</Label><Input value={form.poste} onChange={(e) => setForm({ ...form, poste: e.target.value })} /></div>
          <div className="space-y-1.5"><Label>Unité</Label><Input value={form.unite} onChange={(e) => setForm({ ...form, unite: e.target.value })} /></div>
          <div className="space-y-1.5"><Label>Date prévisionnelle</Label><Input type="date" value={form.dateBesoin} onChange={(e) => setForm({ ...form, dateBesoin: e.target.value })} /></div>
          <div className="space-y-1.5"><Label>Évaluation (FCFA)</Label><Input type="number" value={form.evaluation} onChange={(e) => setForm({ ...form, evaluation: Number(e.target.value) })} /></div>
          <div className="space-y-1.5"><Label>Priorité</Label>
            <Select value={form.priorite} onValueChange={(v) => setForm({ ...form, priorite: v as Maintenance["priorite"] })}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{["Haute", "Normale", "Basse"].map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}</SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5"><Label>Statut</Label>
            <Select value={form.statut} onValueChange={(v) => setForm({ ...form, statut: v as Maintenance["statut"] })}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{["En attente", "Approuvé", "Réalisé", "Rejeté"].map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}</SelectContent>
            </Select>
          </div>
        </div>
        <div className="mt-6 flex justify-end gap-2 border-t border-border pt-4">
          <Button type="button" variant="outline" onClick={onCancel}>{t("action.cancel")}</Button>
          <Button type="submit" className="gap-2"><Save className="h-4 w-4" /> {t("action.save")}</Button>
        </div>
      </form>
    </ViewShell>
  );
}

export default MaintenanceShell;
