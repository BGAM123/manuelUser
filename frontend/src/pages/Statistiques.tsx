import { useMemo, useState } from "react";
import { BarChart3, Building2, MapPin, TrendingDown, ScrollText } from "lucide-react";
import { AppShell } from "@/components/shared/AppShell";
import { ViewShell } from "@/components/shared/ViewShell";
import { DataTable, type Column } from "@/components/shared/DataTable";
import { ExportButton } from "@/components/shared/ExportButton";
import { StatusBadge, statutTone } from "@/components/shared/StatusBadge";
import { useT } from "@/utils/i18n";
import { mockBiens, formatFCFA, type Bien } from "@/utils/mock-data";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

type Report = "unite" | "detenteur" | "etat" | "litige" | "amortissement";

const reports: { key: Report; label: string; icon: typeof BarChart3; desc: string }[] = [
  { key: "unite", label: "Biens par unité", icon: Building2, desc: "Répartition par unité administrative" },
  { key: "detenteur", label: "Biens par détenteur", icon: MapPin, desc: "Inventaire par agent ou service" },
  { key: "etat", label: "État des biens", icon: BarChart3, desc: "Répartition Bon / Passable / Mauvais" },
  { key: "litige", label: "Terrains en litige", icon: ScrollText, desc: "Occupation et statut foncier" },
  { key: "amortissement", label: "Amortissements", icon: TrendingDown, desc: "Tableau des amortissements en cours" },
];

function StatistiquesShell() {
  const t = useT();
  const [active, setActive] = useState<Report>("unite");
  const [exercice, setExercice] = useState("all");
  const [unite, setUnite] = useState("all");
  const [cat, setCat] = useState("all");

  const filtered = useMemo(() => {
    return mockBiens.filter(
      (b) =>
        (exercice === "all" || b.acquisitionDate.startsWith(exercice)) &&
        (unite === "all" || b.unite === unite) &&
        (cat === "all" || b.categorie === cat) &&
        (active !== "litige" || b.statut === "En litige")
    );
  }, [exercice, unite, cat, active]);

  const filterPanel = (
    <>
      <div className="space-y-1.5">
        <Label className="text-xs">Exercice</Label>
        <Select value={exercice} onValueChange={setExercice}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Tous</SelectItem>
            {["2023", "2024", "2025", "2026"].map((y) => <SelectItem key={y} value={y}>{y}</SelectItem>)}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-1.5">
        <Label className="text-xs">Unité</Label>
        <Select value={unite} onValueChange={setUnite}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Toutes</SelectItem>
            {Array.from(new Set(mockBiens.map((b) => b.unite))).map((u) => (
              <SelectItem key={u} value={u}>{u}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-1.5">
        <Label className="text-xs">Catégorie</Label>
        <Select value={cat} onValueChange={setCat}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Toutes</SelectItem>
            {["Immobilier", "Mobilier", "Informatique", "Roulant", "Cheptel"].map((c) => (
              <SelectItem key={c} value={c}>{c}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
    </>
  );

  const columns: Column<Bien>[] = [
    { key: "numero", label: "N° inventaire", className: "font-mono text-xs" },
    { key: "designation", label: "Désignation", render: (b) => <span className="font-medium">{b.designation}</span> },
    { key: "categorie", label: "Catégorie" },
    { key: "unite", label: "Unité" },
    { key: "detenteur", label: "Détenteur" },
    { key: "etat", label: "État" },
    { key: "valeurAcquisition", label: "Valeur", className: "text-right", render: (b) => <span className="tabular-nums">{formatFCFA(b.valeurAcquisition)}</span>, exportFormat: (b) => b.valeurAcquisition },
    { key: "statut", label: "Statut", render: (b) => <StatusBadge tone={statutTone(b.statut)}>{b.statut}</StatusBadge> },
  ];

  return (
    <AppShell>
      <ViewShell
        title={t("statistiques.title")}
        subtitle={t("statistiques.subtitle")}
        actions={
          <ExportButton
            data={filtered}
            columns={columns.map((c) => ({
              key: c.key,
              label: c.label,
              format: c.exportFormat ?? ((r) => (r as Record<string, unknown>)[c.key] as string | number),
            }))}
            filename={`stats-${active}`}
            title={`MINEPIA — ${reports.find((r) => r.key === active)?.label}`}
            filtersSlot={filterPanel}
          />
        }
      >
        <div className="mb-5 grid gap-2.5 grid-cols-2 sm:gap-3 lg:grid-cols-5">
          {reports.map((r) => {
            const Icon = r.icon;
            const isActive = active === r.key;
            return (
              <button
                key={r.key}
                type="button"
                onClick={() => setActive(r.key)}
                className={
                  "flex flex-col items-start gap-2 rounded-xl border p-4 text-left transition " +
                  (isActive
                    ? "border-primary bg-primary/5 shadow-sm"
                    : "border-border bg-card hover:border-primary/40 hover:bg-accent/40")
                }
              >
                <span className={"rounded-lg p-2 " + (isActive ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground")}>
                  <Icon className="h-4 w-4" />
                </span>
                <span className="text-sm font-semibold">{r.label}</span>
                <span className="text-xs text-muted-foreground">{r.desc}</span>
              </button>
            );
          })}
        </div>

        <div className="mb-4 grid grid-cols-2 gap-3 rounded-xl border border-border bg-card p-3 shadow-sm sm:flex sm:flex-wrap sm:p-4">
          <div className="space-y-1 sm:w-40"><Label className="text-xs">Exercice</Label>
            <Select value={exercice} onValueChange={setExercice}>
              <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Tous</SelectItem>
                {["2023", "2024", "2025", "2026"].map((y) => <SelectItem key={y} value={y}>{y}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1 sm:w-52"><Label className="text-xs">Unité</Label>
            <Select value={unite} onValueChange={setUnite}>
              <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Toutes</SelectItem>
                {Array.from(new Set(mockBiens.map((b) => b.unite))).map((u) => <SelectItem key={u} value={u}>{u}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1 sm:w-40"><Label className="text-xs">Catégorie</Label>
            <Select value={cat} onValueChange={setCat}>
              <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Toutes</SelectItem>
                {["Immobilier", "Mobilier", "Informatique", "Roulant", "Cheptel"].map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="hidden flex-1 sm:block" />
          <div className="col-span-2 flex items-end sm:col-span-1">
            <Button variant="outline" className="w-full sm:w-auto" onClick={() => { setExercice("all"); setUnite("all"); setCat("all"); }}>Réinitialiser</Button>
          </div>
        </div>

        <DataTable
          data={filtered}
          columns={columns}
          getRowId={(b) => b.id}
          exportFilename={`stats-${active}`}
          exportTitle={`MINEPIA — ${reports.find((r) => r.key === active)?.label}`}
        />
      </ViewShell>
    </AppShell>
  );
}

export default StatistiquesShell;
