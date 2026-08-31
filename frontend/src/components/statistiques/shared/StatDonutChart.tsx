import { ResponsiveContainer, PieChart, Pie, Cell, Tooltip } from "recharts";

interface DonutItem {
  name: string;
  value: number;
  pct?: string;
  color: string;
  icon?: any;
}

interface StatDonutChartProps {
  data: DonutItem[];
  total: number;
  label?: string;
  size?: number;
  innerRadius?: number;
  outerRadius?: number;
  showLegend?: boolean;
}

const formatNumber = (v: number) => v.toLocaleString("fr-FR");

export function StatDonutChart({
  data,
  total,
  label = "Total",
  innerRadius = 46,
  outerRadius = 66,
  showLegend = true,
}: StatDonutChartProps) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
      <div className="relative mx-auto h-36 w-36 shrink-0 sm:mx-0">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie
              data={data}
              cx="50%"
              cy="50%"
              innerRadius={innerRadius}
              outerRadius={outerRadius}
              paddingAngle={2}
              dataKey="value"
              strokeWidth={0}
            >
              {data.map((d) => (
                <Cell key={d.name} fill={d.color} />
              ))}
            </Pie>
            <Tooltip
              formatter={(v: any) => [formatNumber(Number(v))]}
              contentStyle={{
                fontSize: 12,
                borderRadius: 8,
                backgroundColor: "var(--card)",
                borderColor: "var(--border)",
              }}
            />
          </PieChart>
        </ResponsiveContainer>
        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
          <span className="text-lg font-extrabold text-foreground">{formatNumber(total)}</span>
          <span className="text-[10px] text-muted-foreground">{label}</span>
        </div>
      </div>

      {showLegend && (
        <ul className="flex-1 space-y-1.5 min-w-0">
          {data.map((d) => (
            <li key={d.name} className="flex items-center gap-2 text-[11px]">
              <span
                className="h-2.5 w-2.5 shrink-0 rounded-full"
                style={{ background: d.color }}
              />
              <span className="flex-1 truncate text-foreground font-medium">{d.name}</span>
              <span className="tabular-nums font-bold text-foreground">
                {formatNumber(d.value)}
              </span>
              {d.pct && <span className="text-muted-foreground">({d.pct})</span>}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
