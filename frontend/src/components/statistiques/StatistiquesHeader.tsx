import { useState } from "react";
import type { DateRange } from "react-day-picker";
import {
  Calendar as CalendarIcon,
  RotateCcw,
  Settings2,
  ChevronDown,
  Download,
  FileSpreadsheet,
  FileText,
  Sparkles,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Calendar } from "@/components/ui/calendar";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useConnectedUser } from "@/hooks/useConnectedUser";
import { useT, type Key } from "@/utils/i18n";
import { CanAccess } from "@/components/auth/CanAccess";
import type { WidgetVisibility } from "./types";
import { MINEPIA_GREEN } from "./mock-stats-data";
import { PatrimoineGlobalButton } from "./PatrimoineGlobalButton";
import { ComptabiliteButton } from "./ComptabiliteButton";

interface StatistiquesHeaderProps {
  periodLabel: string;
  onSelectPeriod: (label: string) => void;
  onResetAll: () => void;
  onExportExcel: () => void;
  onExportPdf: () => void;
  widgetVis: WidgetVisibility;
  onToggleWidget: (key: keyof WidgetVisibility) => void;
  onShowAllWidgets: () => void;
}

const WIDGET_LABEL_KEYS: Record<keyof WidgetVisibility, Key> = {
  kpiCards: "stats.widget.kpiCards",
  catPie: "stats.widget.catPie",
  geoMap: "stats.widget.geoMap",
  svcPie: "stats.widget.svcPie",
  top10: "stats.widget.top10",
  top5: "stats.widget.top5",
  top6v: "stats.widget.top6v",
  valeur: "stats.widget.valeur",
  maint: "stats.widget.maint",
  mouv: "stats.widget.mouv",
  aEtat: "stats.widget.aEtat",
  aInfo: "stats.widget.aInfo",
  evol: "stats.widget.evol",
  classmt: "stats.widget.classmt",
  reformer: "stats.widget.reformer",
};

export function StatistiquesHeader({
  periodLabel,
  onSelectPeriod,
  onResetAll,
  onExportExcel,
  onExportPdf,
  widgetVis,
  onToggleWidget,
  onShowAllWidgets,
}: StatistiquesHeaderProps) {
  const t = useT();
  const { fullName, roleName } = useConnectedUser();
  const [periodOpen, setPeriodOpen] = useState(false);
  // Plage en cours de sélection dans le calendrier — distincte de periodLabel
  // (qui ne se met à jour qu'à la validation, voir bouton "Appliquer").
  const [range, setRange] = useState<DateRange | undefined>(undefined);

  const formatDateFr = (d: Date) =>
    `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 className="text-xl font-extrabold tracking-tight text-foreground sm:text-2xl flex items-center gap-2">
          <span>{t("stats.header.title")}</span>
        </h1>
        <p className="mt-0.5 text-xs text-muted-foreground">
          {t("stats.header.welcome")}{" "}
          <span className="font-bold text-foreground">
            {fullName || "Paul NDONGO"}
          </span>{" "}
          — {roleName || t("stats.header.defaultRole")}
        </p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        {/* Sélecteur de Période — calendrier (plage de dates) */}
        <Popover
          open={periodOpen}
          onOpenChange={(v) => {
            setPeriodOpen(v);
            if (v) setRange(undefined);
          }}
        >
          <PopoverTrigger asChild>
            <button
              type="button"
              className="flex h-9 items-center gap-2 rounded-lg border border-border bg-card px-3 text-xs font-semibold text-foreground shadow-xs hover:bg-muted transition-colors cursor-pointer"
            >
              <CalendarIcon className="h-4 w-4 text-emerald-600" />
              <span>{periodLabel !== "" ? periodLabel : t("stats.header.allPeriods")}</span>
              <ChevronDown className="h-3.5 w-3.5 text-muted-foreground" />
            </button>
          </PopoverTrigger>
          <PopoverContent align="end" className="w-auto p-3">
            <div className="space-y-2">
              <p className="px-1 text-[11px] font-bold text-muted-foreground uppercase tracking-wider">
                {t("stats.header.choosePeriod")}
              </p>
              <Calendar
                mode="range"
                selected={range}
                onSelect={setRange}
                numberOfMonths={2}
                defaultMonth={range?.from}
              />
              <div className="flex items-center justify-between gap-2 border-t border-border pt-2">
                <button
                  type="button"
                  onClick={() => {
                    onSelectPeriod("");
                    setRange(undefined);
                    setPeriodOpen(false);
                  }}
                  className="text-xs font-medium text-muted-foreground hover:text-destructive"
                >
                  {t("stats.header.allPeriods")}
                </button>
                <Button
                  size="sm"
                  className="h-8 text-xs"
                  disabled={!range?.from}
                  onClick={() => {
                    if (!range?.from) return;
                    const to = range.to ?? range.from;
                    onSelectPeriod(`${formatDateFr(range.from)} – ${formatDateFr(to)}`);
                    setPeriodOpen(false);
                  }}
                >
                  {t("action.apply")}
                </Button>
              </div>
            </div>
          </PopoverContent>
        </Popover>

        {/* Bouton Réinitialiser */}
        <Button
          variant="outline"
          size="sm"
          onClick={onResetAll}
          className="h-9 gap-1.5 text-xs cursor-pointer shadow-xs"
        >
          <RotateCcw className="h-3.5 w-3.5" />
          <span>{t("action.reset")}</span>
        </Button>

        {/* Menu Export (Excel / PDF) — réservé aux profils autorisés à
            générer des états (permission edition_etat, cf. navPermissions.ts). */}
        <CanAccess permission="edition_etat">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="outline"
                size="sm"
                className="h-9 gap-1.5 text-xs cursor-pointer shadow-xs"
              >
                <Download className="h-3.5 w-3.5 text-emerald-600" />
                <span>{t("action.export")}</span>
                <ChevronDown className="h-3.5 w-3.5 text-muted-foreground" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              <DropdownMenuLabel className="text-xs">{t("stats.header.exportFormats")}</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={onExportExcel} className="text-xs cursor-pointer gap-2">
                <FileSpreadsheet className="h-4 w-4 text-emerald-600" />
                <span>{t("stats.header.exportExcelFull")}</span>
              </DropdownMenuItem>
              <DropdownMenuItem onClick={onExportPdf} className="text-xs cursor-pointer gap-2">
                <FileText className="h-4 w-4 text-red-600" />
                <span>{t("stats.header.exportPdfSummary")}</span>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </CanAccess>

        {/* Export Patrimoine Global (Excel multi-feuilles configurable) —
            même permission que le menu export ci-dessus (édition d'état). */}
        <CanAccess permission="edition_etat">
          <PatrimoineGlobalButton />
        </CanAccess>

        {/* Documents comptables officiels (Livre Journal, Fiche de détenteur) —
            réservé aux comptables et à l'administrateur (permission dédiée). */}
        <CanAccess permission="consultation_comptabilite">
          <ComptabiliteButton />
        </CanAccess>

        {/* Personnalisation de l'affichage (Pop-up widgets) */}
        <Popover>
          <PopoverTrigger asChild>
            <Button
              size="sm"
              style={{ background: MINEPIA_GREEN }}
              className="h-9 gap-1.5 text-xs text-white hover:opacity-90 font-bold shadow-xs cursor-pointer"
            >
              <Settings2 className="h-3.5 w-3.5" />
              <span>{t("stats.header.customize")}</span>
              <ChevronDown className="h-3.5 w-3.5 opacity-80" />
            </Button>
          </PopoverTrigger>
          <PopoverContent align="end" className="w-80 p-4">
            <div className="mb-3 flex items-center justify-between border-b border-border pb-2">
              <div className="flex items-center gap-1.5">
                <Sparkles className="h-4 w-4 text-emerald-600" />
                <p className="text-xs font-bold text-foreground">{t("stats.header.customizeDisplay")}</p>
              </div>
              <button
                type="button"
                onClick={onShowAllWidgets}
                className="text-[11px] font-semibold text-emerald-600 hover:underline cursor-pointer"
              >
                {t("stats.header.showAll")}
              </button>
            </div>
            <div className="max-h-72 space-y-2 overflow-y-auto pr-1">
              {(Object.keys(WIDGET_LABEL_KEYS) as (keyof WidgetVisibility)[]).map(
                (k) => (
                  <label
                    key={k}
                    className="flex cursor-pointer items-center gap-2.5 text-xs hover:bg-muted/40 p-1 rounded-md transition-colors"
                  >
                    <Checkbox
                      checked={widgetVis[k]}
                      onCheckedChange={() => onToggleWidget(k)}
                    />
                    <span className="text-foreground leading-tight">{t(WIDGET_LABEL_KEYS[k])}</span>
                  </label>
                )
              )}
            </div>
          </PopoverContent>
        </Popover>
      </div>
    </div>
  );
}
