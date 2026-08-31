import { useState } from "react";
import { Plus, Eye, Pencil, Trash2, Save, Play } from "lucide-react";
import { toast } from "sonner";
import { AppShell } from "@/components/shared/AppShell";
import { ViewShell } from "@/components/shared/ViewShell";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { StatusBadge, statutTone } from "@/components/shared/StatusBadge";
import { ExportButton } from "@/components/shared/ExportButton";
import { useViewStack } from "@/hooks/useViewStack";
import { useT } from "@/utils/i18n";
import { mockInventaires, type Inventaire } from "@/utils/mock-data";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Progress } from "@/components/ui/progress";

type View = "liste" | "detail" | "creation" | "edition";

function InventairesShell() {
  const t = useT();
  const { view, go, back, reset } = useViewStack<View>("liste");
  const [data, setData] = useState<Inventaire[]>(mockInventaires);
  const [selected, setSelected] = useState<Inventaire | null>(null);

  const columns: Column<Inventaire>[] = [
    { key: "code", label: "Code", className: "font-mono text-xs" },
    { key: "type", label: "Type", render: (r) => <span className="font-medium">{r.type}</span> },
    { key: "exercice", label: "Exercice" },
    { key: "responsable", label: "Responsable" },
    { key: "dateDebut", label: "Début" },
    { key: "dateFin", label: "Fin" },
    {
      key: "progression",
      label: "Progression",
      render: (r) => (
        <div className="flex items-center gap-2">
          <Progress value={r.progression} className="h-2 w-24" />
          <span className="w-8 text-xs tabular-nums">{r.progression}%</span>
        </div>
      ),
      exportFormat: (r) => `${r.progression}%`,
    },
    { key: "statut", label: "Statut", render: (r) => <StatusBadge tone={statutTone(r.statut)}>{r.statut}</StatusBadge> },
  ];

  return (
    <AppShell>
      {view === "liste" && (
        <ViewShell
          title={t("inventaires.title")}
          subtitle={t("inventaires.subtitle")}
          actions={
            <Button onClick={() => { setSelected(null); go("creation"); }} className="gap-2">
              <Plus className="h-4 w-4" /> {t("inventaires.new")}
            </Button>
          }
        >
          <DataTable
            data={data}
            columns={columns}
            getRowId={(r) => r.id}
            exportFilename="inventaires-minepia"
            exportTitle="MINEPIA — Campagnes d'inventaire"
            searchKeys={["code", "type", "exercice", "responsable"]}
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
        <ViewShell title={selected.code} subtitle={selected.type + " — Exercice " + selected.exercice} onBack={() => reset("liste")}
          actions={
            <>
              <ExportButton data={[selected]} columns={[
                { key: "code", label: "Code" },
                { key: "type", label: "Type" },
                { key: "exercice", label: "Exercice" },
                { key: "responsable", label: "Responsable" },
                { key: "dateDebut", label: "Début" },
                { key: "dateFin", label: "Fin" },
                { key: "progression", label: "Progression", format: (r) => `${r.progression}%` },
                { key: "statut", label: "Statut" },
              ]} filename={`inventaire-${selected.code}`} />
              <Button variant="outline" onClick={() => go("edition")} className="gap-2"><Pencil className="h-4 w-4" /> {t("action.edit")}</Button>
              <Button className="gap-2"><Play className="h-4 w-4" /> Lancer comptage</Button>
            </>
          }>
          <div className="grid gap-6 lg:grid-cols-3">
            <div className="rounded-xl border border-border bg-card p-6 shadow-sm lg:col-span-2">
              <div className="mb-4 flex items-start justify-between">
                <h2 className="text-xl font-bold">{selected.type}</h2>
                <StatusBadge tone={statutTone(selected.statut)}>{selected.statut}</StatusBadge>
              </div>
              <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                {[
                  ["Code", selected.code],
                  ["Exercice", selected.exercice],
                  ["Responsable", selected.responsable],
                  ["Date début", selected.dateDebut],
                  ["Date fin", selected.dateFin],
                ].map(([k, v]) => (
                  <div key={k} className="border-b border-border/60 pb-2">
                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">{k}</dt>
                    <dd className="mt-0.5 text-sm font-medium">{v}</dd>
                  </div>
                ))}
              </dl>
              <div className="mt-6">
                <div className="mb-1.5 flex items-center justify-between text-sm">
                  <span className="font-medium">Progression du comptage</span>
                  <span className="tabular-nums font-semibold">{selected.progression}%</span>
                </div>
                <Progress value={selected.progression} className="h-3" />
              </div>
            </div>
            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
              <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Saisie de comptage</h3>
              <p className="text-sm text-muted-foreground">Sélectionnez un magasin ou un poste de gestion pour enregistrer le comptage physique.</p>
              <div className="mt-4 space-y-2">
                <Select defaultValue="mag-1">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="mag-1">Magasin Central Yaoundé</SelectItem>
                    <SelectItem value="mag-2">Magasin DRPIA Centre</SelectItem>
                    <SelectItem value="mag-3">Magasin DDPIA Wouri</SelectItem>
                  </SelectContent>
                </Select>
                <Button className="w-full gap-2"><Play className="h-4 w-4" /> Commencer</Button>
              </div>
            </div>
          </div>
        </ViewShell>
      )}

      {(view === "creation" || view === "edition") && (
        <InventaireForm
          initial={view === "edition" ? selected : null}
          onCancel={back}
          onSave={(inv) => {
            setData((prev) => view === "creation" ? [inv, ...prev] : prev.map((x) => (x.id === inv.id ? inv : x)));
            toast.success(t("toast.saved"));
            reset("liste");
          }}
        />
      )}
    </AppShell>
  );
}

function InventaireForm({ initial, onCancel, onSave }: { initial: Inventaire | null; onCancel: () => void; onSave: (i: Inventaire) => void }) {
  const t = useT();
  const [form, setForm] = useState<Inventaire>(
    initial ?? {
      id: `I-${Date.now().toString().slice(-4)}`,
      code: `INV-${new Date().getFullYear()}-NEW`,
      type: "Général de base",
      exercice: String(new Date().getFullYear()),
      dateDebut: new Date().toISOString().slice(0, 10),
      dateFin: new Date().toISOString().slice(0, 10),
      statut: "Planifié",
      responsable: "",
      progression: 0,
    }
  );
  return (
    <ViewShell title={initial ? "Modifier la campagne" : t("inventaires.new")} onBack={onCancel}>
      <form onSubmit={(e) => { e.preventDefault(); onSave(form); }} className="rounded-xl border border-border bg-card p-6 shadow-sm">
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1.5"><Label>Code</Label><Input value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} required /></div>
          <div className="space-y-1.5"><Label>Type</Label>
            <Select value={form.type} onValueChange={(v) => setForm({ ...form, type: v as Inventaire["type"] })}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{["Général de base", "Fin d'exercice", "Mutation"].map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}</SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5"><Label>Exercice</Label><Input value={form.exercice} onChange={(e) => setForm({ ...form, exercice: e.target.value })} /></div>
          <div className="space-y-1.5"><Label>Responsable</Label><Input value={form.responsable} onChange={(e) => setForm({ ...form, responsable: e.target.value })} required /></div>
          <div className="space-y-1.5"><Label>Date début</Label><Input type="date" value={form.dateDebut} onChange={(e) => setForm({ ...form, dateDebut: e.target.value })} /></div>
          <div className="space-y-1.5"><Label>Date fin</Label><Input type="date" value={form.dateFin} onChange={(e) => setForm({ ...form, dateFin: e.target.value })} /></div>
          <div className="space-y-1.5"><Label>Statut</Label>
            <Select value={form.statut} onValueChange={(v) => setForm({ ...form, statut: v as Inventaire["statut"] })}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{["Planifié", "En cours", "Clôturé"].map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}</SelectContent>
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

export default InventairesShell;
