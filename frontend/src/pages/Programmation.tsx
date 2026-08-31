import { useState } from "react";
import { Plus, Eye, Pencil, Trash2, Save } from "lucide-react";
import { toast } from "sonner";
import { AppShell } from "@/components/shared/AppShell";
import { ViewShell } from "@/components/shared/ViewShell";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { StatusBadge, statutTone } from "@/components/shared/StatusBadge";
import { useViewStack } from "@/hooks/useViewStack";
import { useT } from "@/utils/i18n";
import { mockProgrammations, formatFCFA, type Programmation } from "@/utils/mock-data";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

type View = "liste" | "detail" | "creation" | "edition";

function ProgrammationShell() {
  const t = useT();
  const { view, go, back, reset } = useViewStack<View>("liste");
  const [data, setData] = useState<Programmation[]>(mockProgrammations);
  const [selected, setSelected] = useState<Programmation | null>(null);

  const columns: Column<Programmation>[] = [
    { key: "id", label: "ID", className: "font-mono text-xs" },
    { key: "designation", label: "Désignation", render: (r) => <span className="font-medium">{r.designation}</span> },
    { key: "categorie", label: "Catégorie" },
    { key: "exercice", label: "Exercice" },
    { key: "unite", label: "Unité", className: "text-xs" },
    { key: "maturation", label: "Maturation", render: (r) => <StatusBadge tone={r.maturation === "Engagé" ? "normal" : r.maturation === "Prêt à engager" ? "info" : "low"}>{r.maturation}</StatusBadge> },
    { key: "montantEnvisage", label: "Montant", className: "text-right", render: (r) => <span className="tabular-nums">{formatFCFA(r.montantEnvisage)}</span>, exportFormat: (r) => r.montantEnvisage },
    { key: "statut", label: "Statut", render: (r) => <StatusBadge tone={statutTone(r.statut)}>{r.statut}</StatusBadge> },
  ];

  return (
    <AppShell>
      {view === "liste" && (
        <ViewShell
          title={t("programmation.title")}
          subtitle={t("programmation.subtitle")}
          actions={
            <Button onClick={() => { setSelected(null); go("creation"); }} className="gap-2">
              <Plus className="h-4 w-4" /> {t("programmation.new")}
            </Button>
          }
        >
          <DataTable
            data={data}
            columns={columns}
            getRowId={(r) => r.id}
            exportFilename="programmation-minepia"
            exportTitle="MINEPIA — Programmation budgétaire"
            searchKeys={["designation", "categorie", "exercice", "unite"]}
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
        <ViewShell title={selected.designation} subtitle={`Programmation ${selected.id}`} onBack={() => reset("liste")}
          actions={<Button variant="outline" onClick={() => go("edition")} className="gap-2"><Pencil className="h-4 w-4" /> {t("action.edit")}</Button>}>
          <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
            <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
              {[
                ["Désignation", selected.designation],
                ["Catégorie", selected.categorie],
                ["Exercice", selected.exercice],
                ["Unité", selected.unite],
                ["Maturation", selected.maturation],
                ["Date prévue", selected.datePrevue],
                ["Montant envisagé", formatFCFA(selected.montantEnvisage)],
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

      {(view === "creation" || view === "edition") && (
        <ProgForm
          initial={view === "edition" ? selected : null}
          onCancel={back}
          onSave={(p) => {
            setData((prev) => view === "creation" ? [p, ...prev] : prev.map((x) => (x.id === p.id ? p : x)));
            toast.success(t("toast.saved"));
            reset("liste");
          }}
        />
      )}
    </AppShell>
  );
}

function ProgForm({ initial, onCancel, onSave }: { initial: Programmation | null; onCancel: () => void; onSave: (p: Programmation) => void }) {
  const t = useT();
  const [form, setForm] = useState<Programmation>(
    initial ?? {
      id: `P-${Date.now().toString().slice(-4)}`,
      designation: "",
      categorie: "Mobilier",
      exercice: String(new Date().getFullYear()),
      maturation: "Idée",
      datePrevue: new Date().toISOString().slice(0, 10),
      montantEnvisage: 0,
      unite: "",
      statut: "Planifié",
    }
  );
  return (
    <ViewShell title={initial ? "Modifier la programmation" : t("programmation.new")} onBack={onCancel}>
      <form onSubmit={(e) => { e.preventDefault(); onSave(form); }} className="rounded-xl border border-border bg-card p-6 shadow-sm">
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1.5 sm:col-span-2"><Label>Désignation</Label><Input value={form.designation} onChange={(e) => setForm({ ...form, designation: e.target.value })} required /></div>
          <div className="space-y-1.5"><Label>Catégorie</Label>
            <Select value={form.categorie} onValueChange={(v) => setForm({ ...form, categorie: v as Programmation["categorie"] })}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{["Immobilier", "Mobilier", "Informatique", "Roulant", "Cheptel"].map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}</SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5"><Label>Exercice</Label><Input value={form.exercice} onChange={(e) => setForm({ ...form, exercice: e.target.value })} /></div>
          <div className="space-y-1.5"><Label>Maturation</Label>
            <Select value={form.maturation} onValueChange={(v) => setForm({ ...form, maturation: v as Programmation["maturation"] })}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{["Idée", "Étude", "Prêt à engager", "Engagé"].map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}</SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5"><Label>Unité</Label><Input value={form.unite} onChange={(e) => setForm({ ...form, unite: e.target.value })} /></div>
          <div className="space-y-1.5"><Label>Date prévue</Label><Input type="date" value={form.datePrevue} onChange={(e) => setForm({ ...form, datePrevue: e.target.value })} /></div>
          <div className="space-y-1.5"><Label>Montant envisagé (FCFA)</Label><Input type="number" value={form.montantEnvisage} onChange={(e) => setForm({ ...form, montantEnvisage: Number(e.target.value) })} /></div>
          <div className="space-y-1.5"><Label>Statut</Label>
            <Select value={form.statut} onValueChange={(v) => setForm({ ...form, statut: v as Programmation["statut"] })}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{["Planifié", "Réalisé", "Reporté"].map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}</SelectContent>
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

export default ProgrammationShell;
