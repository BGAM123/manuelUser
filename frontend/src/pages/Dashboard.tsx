import { useMemo, useState } from "react";
import {
  Package,
  Wallet,
  Wrench,
  CheckCircle2,
  PackageMinus,
  Building,
  RotateCcw,
  Settings2,
  ChevronDown,
} from "lucide-react";
import {
  ResponsiveContainer,
  Tooltip,
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
} from "recharts";
import { AppShell } from "@/components/shared/AppShell";
import { StatCard } from "@/components/shared/StatCard";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useT } from "@/utils/i18n";
import {
  mockBiens,
  mockMaintenances,
  mockProjets,
  formatFCFA,
} from "@/utils/mock-data";

type SectionKey =
  | "topStructures"
  | "topProjets"
  | "topValeur"
  | "valeurTotale"
  | "maintenanceMois"
  | "mouvements";

const SECTION_LABELS: Record<SectionKey, string> = {
  topStructures: "Répartition par structure (Top 10)",
  topProjets: "Répartition par projet (Top 5)",
  topValeur: "Répartition par structure (Top 6) par valeur",
  valeurTotale: "Valeur totale du patrimoine (FCFA)",
  maintenanceMois: "Biens en maintenance par mois",
  mouvements: "Mouvements de biens - Nombre de biens affectés par mois",
};

// Palette pour les 6 tuiles "Top 6 par valeur"
const TILE_PALETTE = ["#16a34a", "#2563eb", "#0d9488", "#8b5cf6", "#ea580c", "#0891b2"];

const MOIS = ["Jan", "Fév", "Mar", "Avr", "Mai", "Juin", "Juil", "Août", "Sep", "Oct", "Nov", "Déc"];

// Formate en Milliards de FCFA — "28,40 Mds FCFA"
function formatMdsFCFA(v: number): string {
  return `${(v / 1_000_000_000).toLocaleString("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Mds FCFA`;
}
function formatMdsShort(v: number): string {
  return `${(v / 1_000_000_000).toLocaleString("fr-FR", { maximumFractionDigits: 0 })} Mds`;
}

function DashboardPage() {
  const t = useT();

  // Filtres
  const [organigramme, setOrganigramme] = useState("all");
  const [typeBien, setTypeBien] = useState("all");
  const [categorie, setCategorie] = useState("all");
  const [projet, setProjet] = useState("all");
  const resetFilters = () => {
    setOrganigramme("all");
    setTypeBien("all");
    setCategorie("all");
    setProjet("all");
  };

  // Personnalisation affichage
  const [visible, setVisible] = useState<Record<SectionKey, boolean>>({
    topStructures: true,
    topProjets: true,
    topValeur: true,
    valeurTotale: true,
    maintenanceMois: true,
    mouvements: true,
  });
  const toggle = (k: SectionKey) => setVisible((v) => ({ ...v, [k]: !v[k] }));

  const unites = useMemo(() => Array.from(new Set(mockBiens.map((b) => b.unite))), []);
  const categories = useMemo(() => Array.from(new Set(mockBiens.map((b) => b.categorie))), []);

  const filteredBiens = useMemo(() => {
    return mockBiens.filter(
      (b) =>
        (organigramme === "all" || b.unite === organigramme) &&
        (typeBien === "all" || b.categorie === typeBien) &&
        (categorie === "all" || b.categorie === categorie),
    );
  }, [organigramme, typeBien, categorie]);

  // KPI (basés sur biens filtrés)
  const total = filteredBiens.length || 1;
  const valorise = filteredBiens.reduce((s, b) => s + b.valeurAcquisition, 0);
  const biensActifs = filteredBiens.filter((b) => b.statut === "Actif").length;
  const biensEnMaint = filteredBiens.filter((b) => b.statut === "En maintenance").length;
  const biensSortis = filteredBiens.filter((b) => b.statut === "À réformer" || b.statut === "Réformé").length;
  const structuresConcernees = new Set(filteredBiens.map((b) => b.unite)).size;

  // Top 10 structures — par nombre
  const parStructure = useMemo(() => {
    return unites.map((u) => {
      const items = filteredBiens.filter((b) => b.unite === u);
      return {
        name: u,
        count: items.length,
        value: items.reduce((s, b) => s + b.valeurAcquisition, 0),
      };
    });
  }, [filteredBiens, unites]);
  const topStructures = useMemo(
    () => [...parStructure].sort((a, b) => b.count - a.count).slice(0, 10),
    [parStructure],
  );
  const maxNb = Math.max(...topStructures.map((x) => x.count), 1);

  // Top 6 structures par valeur (tuiles colorées)
  const top6Valeur = useMemo(
    () => [...parStructure].sort((a, b) => b.value - a.value).slice(0, 6),
    [parStructure],
  );

  // Top 5 projets (par budget en nombre — count est représenté par le budget dans le mock)
  const topProjets = useMemo(() => {
    return [...mockProjets]
      .map((p) => ({ name: p.intitule, count: Math.round(p.budget / 500_000) }))
      .sort((a, b) => b.count - a.count)
      .slice(0, 5);
  }, []);
  const maxProjet = Math.max(...topProjets.map((x) => x.count), 1);

  // Séries mensuelles
  const mouvementsParMois = useMemo(() => {
    const arr = MOIS.map((m) => ({ mois: m, nombre: 0 }));
    filteredBiens.forEach((b) => {
      const m = Number(b.acquisitionDate.slice(5, 7)) - 1;
      if (m >= 0 && m < 12) arr[m].nombre += 1;
    });
    return arr;
  }, [filteredBiens]);

  const valeurParMois = useMemo(() => {
    const arr = MOIS.map((m) => ({ mois: m, valeur: 0 }));
    filteredBiens.forEach((b) => {
      const m = Number(b.acquisitionDate.slice(5, 7)) - 1;
      if (m >= 0 && m < 12) arr[m].valeur += b.valeurAcquisition;
    });
    // cumul progressif pour illustrer l'évolution
    let acc = 0;
    return arr.map((x) => ({ mois: x.mois, valeur: (acc += x.valeur) }));
  }, [filteredBiens]);

  const maintenanceParMois = useMemo(() => {
    const arr = MOIS.map((m) => ({ mois: m, nombre: 0 }));
    mockMaintenances.forEach((m) => {
      const idx = Number(m.dateBesoin.slice(5, 7)) - 1;
      if (idx >= 0 && idx < 12) arr[idx].nombre += 1;
    });
    return arr;
  }, []);

  return (
    <AppShell>
      <div className="space-y-6 sm:space-y-8">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
            {t("dashboard.title")}
          </h1>
          <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
            {t("dashboard.welcome")},{" "}
            <span className="font-medium text-foreground">Paul NDONGO</span> — Administrateur
          </p>
        </div>

        {/* Filtres + Personnaliser */}
        <div className="rounded-lg border border-border bg-card p-4 shadow-sm sm:p-5">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <div className="space-y-1">
              <Label className="text-xs">Organigramme</Label>
              <Select value={organigramme} onValueChange={setOrganigramme}>
                <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Toutes les structures</SelectItem>
                  {unites.map((u) => <SelectItem key={u} value={u}>{u}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Catégorie (Parent)</Label>
              <Select value={categorie} onValueChange={setCategorie}>
                <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Toutes les catégories</SelectItem>
                  {categories.map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Type de bien (Enfant)</Label>
              <Select value={typeBien} onValueChange={setTypeBien}>
                <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Tous les types</SelectItem>
                  {categories.map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Projet</Label>
              <Select value={projet} onValueChange={setProjet}>
                <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Tous</SelectItem>
                  {mockProjets.map((p) => (
                    <SelectItem key={p.id} value={p.id}>{p.code} — {p.intitule}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-end">
              <Button variant="outline" onClick={resetFilters} className="h-9 w-full gap-2">
                <RotateCcw className="h-4 w-4" />
                Réinitialiser
              </Button>
            </div>
            <div className="flex items-end">
              <Popover>
                <PopoverTrigger asChild>
                  <Button variant="outline" className="h-9 w-full gap-2 border-primary/40 text-primary hover:bg-primary/5">
                    <Settings2 className="h-4 w-4" />
                    <span className="truncate">Personnaliser l'affichage</span>
                    <ChevronDown className="h-4 w-4 opacity-70" />
                  </Button>
                </PopoverTrigger>
                <PopoverContent align="end" className="w-80">
                  <p className="mb-3 text-sm font-semibold text-foreground">Personnaliser l'affichage</p>
                  <div className="space-y-2.5">
                    {(Object.keys(SECTION_LABELS) as SectionKey[]).map((k) => (
                      <label key={k} className="flex cursor-pointer items-start gap-2 text-sm">
                        <Checkbox checked={visible[k]} onCheckedChange={() => toggle(k)} className="mt-0.5" />
                        <span className="leading-snug">{SECTION_LABELS[k]}</span>
                      </label>
                    ))}
                  </div>
                </PopoverContent>
              </Popover>
            </div>
          </div>
        </div>

        {/* 6 KPI cards */}
        <div className="grid grid-cols-2 gap-4 sm:gap-5 md:grid-cols-3 xl:grid-cols-6">
          <StatCard label="Nombre total de biens" value={String(filteredBiens.length)} hint="Tous statuts confondus" icon={Package} tone="primary" />
          <StatCard label="Valeur totale du patrimoine" value={formatFCFA(valorise)} hint="Valeur d'acquisition" icon={Wallet} tone="secondary" />
          <StatCard label="Biens actifs" value={String(biensActifs)} hint={`${Math.round((biensActifs / total) * 100)}% du total`} icon={CheckCircle2} tone="primary" />
          <StatCard label="Biens en maintenance" value={String(biensEnMaint)} hint={`${Math.round((biensEnMaint / total) * 100)}% du total`} icon={Wrench} tone="low" />
          <StatCard label="Biens sortis" value={String(biensSortis)} hint={`${Math.round((biensSortis / total) * 100)}% du total`} icon={PackageMinus} tone="urgent" />
          <StatCard label="Structures concernées" value={String(structuresConcernees)} hint="Structures activées" icon={Building} tone="secondary" />
        </div>

        {/* Row 1 : Top structures / Top projets / Top 6 par valeur */}
        {(visible.topStructures || visible.topProjets || visible.topValeur) && (
          <div className="grid grid-cols-1 gap-5 sm:gap-6 lg:grid-cols-3">
            {visible.topStructures && (
              <RankListCard
                title="Répartition par structure (Top 10)"
                columnLabel="Nombre de biens"
                items={topStructures.map((s) => ({ name: s.name, value: s.count }))}
                max={maxNb}
                color="#16a34a"
              />
            )}
            {visible.topProjets && (
              <RankListCard
                title="Répartition par projet (Top 5)"
                columnLabel="Nombre de biens"
                items={topProjets.map((p) => ({ name: p.name, value: p.count }))}
                max={maxProjet}
                color="#2563eb"
              />
            )}
            {visible.topValeur && (
              <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
                <h2 className="mb-4 text-sm font-bold text-foreground">Répartition par structure (Top 6) par valeur</h2>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                  {top6Valeur.map((c, i) => (
                    <div
                      key={c.name}
                      className="rounded-lg p-4 text-white shadow-sm"
                      style={{ background: TILE_PALETTE[i % TILE_PALETTE.length] }}
                    >
                      <p className="line-clamp-2 text-xs font-semibold leading-tight opacity-95">{c.name}</p>
                      <p className="mt-2 text-xl font-extrabold tabular-nums">
                        {formatMdsFCFA(c.value)}
                      </p>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}

        {/* Row 2 : 3 courbes */}
        {(visible.valeurTotale || visible.maintenanceMois || visible.mouvements) && (
          <div className="grid grid-cols-1 gap-5 sm:gap-6 lg:grid-cols-3">
            {visible.valeurTotale && (
              <LineCard
                title="Valeur totale du patrimoine (FCFA)"
                data={valeurParMois}
                dataKey="valeur"
                color="#2563eb"
                yFormatter={(v) => formatMdsShort(v)}
                tooltipFormatter={(v) => formatMdsFCFA(v)}
              />
            )}
            {visible.maintenanceMois && (
              <LineCard
                title="Biens en maintenance par mois"
                data={maintenanceParMois}
                dataKey="nombre"
                color="#ea580c"
              />
            )}
            {visible.mouvements && (
              <LineCard
                title="Mouvements de biens - Nombre de biens affectés par mois"
                data={mouvementsParMois}
                dataKey="nombre"
                color="#16a34a"
              />
            )}
          </div>
        )}
      </div>
    </AppShell>
  );
}

function RankListCard({
  title,
  columnLabel,
  items,
  max,
  color,
}: {
  title: string;
  columnLabel: string;
  items: { name: string; value: number }[];
  max: number;
  color: string;
}) {
  return (
    <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-sm font-bold text-foreground">{title}</h2>
        <span className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
          {columnLabel}
        </span>
      </div>
      <ol className="space-y-2.5">
        {items.map((s, i) => (
          <li key={s.name} className="grid grid-cols-[1.25rem_1fr_auto] items-center gap-2 text-xs">
            <span className="text-[11px] font-semibold text-muted-foreground tabular-nums">{i + 1}</span>
            <div className="min-w-0">
              <p className="truncate font-medium">{s.name}</p>
              <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted">
                <div
                  className="h-full rounded-full"
                  style={{ width: `${(s.value / max) * 100}%`, background: color }}
                />
              </div>
            </div>
            <span className="tabular-nums font-semibold">
              {s.value.toLocaleString("fr-FR")}
            </span>
          </li>
        ))}
      </ol>
    </div>
  );
}

function LineCard({
  title,
  data,
  dataKey,
  color,
  yFormatter,
  tooltipFormatter,
}: {
  title: string;
  data: Array<Record<string, number | string>>;
  dataKey: string;
  color: string;
  yFormatter?: (v: number) => string;
  tooltipFormatter?: (v: number) => string;
}) {
  return (
    <div className="rounded-lg border border-border bg-card p-5 shadow-sm">
      <h2 className="mb-3 text-sm font-bold text-foreground">{title}</h2>
      <div className="h-56">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={data} margin={{ top: 8, right: 12, left: 0, bottom: 0 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
            <XAxis dataKey="mois" tick={{ fontSize: 10 }} />
            <YAxis
              tick={{ fontSize: 10 }}
              width={44}
              tickFormatter={yFormatter}
              allowDecimals={false}
            />
            <Tooltip
              formatter={(v: number) => (tooltipFormatter ? tooltipFormatter(v) : v)}
            />
            <Line
              type="monotone"
              dataKey={dataKey}
              stroke={color}
              strokeWidth={2.5}
              dot={{ r: 3.5, fill: color }}
              activeDot={{ r: 5 }}
            />
          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}

export default DashboardPage;
