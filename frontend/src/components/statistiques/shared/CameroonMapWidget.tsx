import { useState } from "react";
import { MINEPIA_GREEN } from "../mock-stats-data";

interface RegionItem {
  name: string;
  value: number;
}

interface CameroonMapWidgetProps {
  data: RegionItem[];
  onSelectRegion?: (region: string) => void;
  selectedRegion?: string;
}

const formatNumber = (v: number) => v.toLocaleString("fr-FR");

const REGION_COORDS: Record<string, { cx: number; cy: number; lbl: string; fullName: string }> = {
  Centre: { cx: 100, cy: 118, lbl: "Centre", fullName: "Centre" },
  Littoral: { cx: 72, cy: 142, lbl: "Littoral", fullName: "Littoral" },
  Ouest: { cx: 70, cy: 102, lbl: "Ouest", fullName: "Ouest" },
  Nord: { cx: 112, cy: 52, lbl: "Nord", fullName: "Nord" },
  "Sud-Ouest": { cx: 50, cy: 155, lbl: "S-O", fullName: "Sud-Ouest" },
  Sud: { cx: 98, cy: 178, lbl: "Sud", fullName: "Sud" },
  Est: { cx: 148, cy: 128, lbl: "Est", fullName: "Est" },
  Adamaoua: { cx: 118, cy: 88, lbl: "Adam.", fullName: "Adamaoua" },
  "Nord-Ouest": { cx: 70, cy: 86, lbl: "N-O", fullName: "Nord-Ouest" },
  "Extrême-Nord": { cx: 110, cy: 26, lbl: "Ex-N", fullName: "Extrême-Nord" },
  "+5 autres régions": { cx: 148, cy: 160, lbl: "+5", fullName: "+5 autres régions" },
};

export function CameroonMapWidget({
  data,
  onSelectRegion,
  selectedRegion,
}: CameroonMapWidgetProps) {
  const [hoveredRegion, setHoveredRegion] = useState<string | null>(null);
  const max = Math.max(...data.map((d) => d.value), 1);
  const op = (v: number) => Math.min(1, Math.max(0.2, (v / max) * 0.95));

  return (
    <div className="flex flex-col items-center gap-3 sm:flex-row sm:items-start">
      {/* Carte SVG du Cameroun */}
      <div className="relative shrink-0 flex items-center justify-center p-1 bg-emerald-50/50 dark:bg-emerald-950/20 rounded-xl border border-emerald-100 dark:border-emerald-900/30">
        <svg
          width="160"
          height="192"
          viewBox="0 0 200 220"
          style={{ overflow: "visible" }}
          className="transition-all"
        >
          {/* Contour géographique simplifié du Cameroun */}
          <path
            d="M70,20 L130,18 L160,35 L165,65 L155,90 L165,115 L155,145 L140,170 L120,195 L95,200 L70,185 L50,165 L40,140 L35,110 L45,85 L40,60 L55,38 Z"
            fill="var(--card)"
            stroke={MINEPIA_GREEN}
            strokeWidth="1.75"
            className="filter drop-shadow-sm"
          />

          {/* Points et bulles de données régionales */}
          {data.map((d) => {
            const c = REGION_COORDS[d.name];
            if (!c) return null;
            const isHovered = hoveredRegion === d.name;
            const isSelected = selectedRegion === d.name;
            const radius = 12 + (d.value / max) * 8;

            return (
              <g
                key={d.name}
                className="cursor-pointer transition-transform duration-200"
                onMouseEnter={() => setHoveredRegion(d.name)}
                onMouseLeave={() => setHoveredRegion(null)}
                onClick={() => onSelectRegion?.(d.name)}
              >
                <circle
                  cx={c.cx}
                  cy={c.cy}
                  r={radius}
                  fill={isSelected ? "#16a34a" : MINEPIA_GREEN}
                  opacity={isHovered ? 1 : op(d.value)}
                  stroke={isHovered || isSelected ? "#ffffff" : "transparent"}
                  strokeWidth="1.5"
                  className="transition-all duration-200"
                />
                <text
                  x={c.cx}
                  y={c.cy + 1}
                  textAnchor="middle"
                  dominantBaseline="middle"
                  fontSize="6.5"
                  fontWeight="700"
                  fill="#ffffff"
                  className="pointer-events-none select-none"
                >
                  {c.lbl}
                </text>
              </g>
            );
          })}
        </svg>

        {hoveredRegion && (
          <div className="absolute bottom-2 left-2 right-2 bg-slate-900/90 text-white text-[10px] py-1 px-2 rounded-md shadow-md text-center pointer-events-none backdrop-blur-xs">
            <span className="font-bold">{hoveredRegion}</span> :{" "}
            {formatNumber(data.find((d) => d.name === hoveredRegion)?.value || 0)} biens
          </div>
        )}
      </div>

      {/* Liste des régions & valeurs */}
      <div className="flex-1 space-y-1.5 w-full min-w-0">
        {data.map((d) => {
          const isSelected = selectedRegion === d.name;
          return (
            <button
              type="button"
              key={d.name}
              onClick={() => onSelectRegion?.(d.name)}
              className={`w-full flex items-center justify-between text-[11px] p-1 rounded-md transition-colors ${
                isSelected
                  ? "bg-primary/15 font-bold text-primary"
                  : "hover:bg-muted text-foreground"
              }`}
            >
              <div className="flex items-center gap-1.5 min-w-0">
                <span
                  className="h-2 w-2 rounded-full shrink-0"
                  style={{ background: MINEPIA_GREEN, opacity: op(d.value) }}
                />
                <span className="truncate font-medium">{d.name}</span>
              </div>
              <span className="tabular-nums font-bold shrink-0 ml-2">
                {formatNumber(d.value)}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
}
