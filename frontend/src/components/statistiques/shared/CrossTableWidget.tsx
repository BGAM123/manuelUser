import { useState } from "react";
import { Search, ArrowUpDown } from "lucide-react";
import { Input } from "@/components/ui/input";

export interface CrossTableColumn {
  key: string;
  label: string;
  align?: "left" | "center" | "right";
  isTotal?: boolean;
}

interface CrossTableWidgetProps {
  title?: string;
  subTitle?: string;
  columns: CrossTableColumn[];
  data: Record<string, any>[];
  rowKey: string;
  searchPlaceholder?: string;
}

const formatNumber = (v: any) => {
  if (typeof v === "number") return v.toLocaleString("fr-FR");
  return v ?? "—";
};

export function CrossTableWidget({
  title,
  subTitle,
  columns,
  data,
  rowKey,
  searchPlaceholder = "Rechercher un département...",
}: CrossTableWidgetProps) {
  const [search, setSearch] = useState("");
  const [sortKey, setSortKey] = useState<string | null>(null);
  const [sortOrder, setSortOrder] = useState<"asc" | "desc">("desc");

  const handleSort = (key: string) => {
    if (sortKey === key) {
      setSortOrder(sortOrder === "asc" ? "desc" : "asc");
    } else {
      setSortKey(key);
      setSortOrder("desc");
    }
  };

  const filtered = data.filter((row) => {
    if (!search) return true;
    return Object.values(row).some((val) =>
      String(val).toLowerCase().includes(search.toLowerCase())
    );
  });

  const sorted = [...filtered].sort((a, b) => {
    if (!sortKey) return 0;
    const valA = a[sortKey];
    const valB = b[sortKey];
    if (typeof valA === "number" && typeof valB === "number") {
      return sortOrder === "asc" ? valA - valB : valB - valA;
    }
    return sortOrder === "asc"
      ? String(valA).localeCompare(String(valB))
      : String(valB).localeCompare(String(valA));
  });

  return (
    <div className="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
      {(title || subTitle) && (
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-border px-4 py-3 bg-muted/20">
          <div>
            {title && (
              <p className="text-xs font-bold uppercase tracking-wider text-foreground">
                {title}
              </p>
            )}
            {subTitle && (
              <p className="text-[11px] text-muted-foreground">{subTitle}</p>
            )}
          </div>
          <div className="relative w-full sm:w-64">
            <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
            <Input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={searchPlaceholder}
              className="h-8 pl-8 text-xs bg-background"
            />
          </div>
        </div>
      )}

      <div className="overflow-x-auto">
        <table className="w-full text-left text-xs border-collapse">
          <thead>
            <tr className="border-b border-border bg-muted/40 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
              {columns.map((col) => (
                <th
                  key={col.key}
                  onClick={() => handleSort(col.key)}
                  className={`px-3 py-2.5 cursor-pointer hover:bg-muted/70 transition-colors ${
                    col.align === "right"
                      ? "text-right"
                      : col.align === "center"
                      ? "text-center"
                      : "text-left"
                  } ${col.isTotal ? "text-primary font-extrabold bg-primary/5" : ""}`}
                >
                  <div className={`flex items-center gap-1 ${
                    col.align === "right" ? "justify-end" : col.align === "center" ? "justify-center" : "justify-start"
                  }`}>
                    <span>{col.label}</span>
                    <ArrowUpDown className="h-2.5 w-2.5 opacity-50" />
                  </div>
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {sorted.length === 0 ? (
              <tr>
                <td
                  colSpan={columns.length}
                  className="px-4 py-6 text-center text-xs text-muted-foreground"
                >
                  Aucune donnée trouvée.
                </td>
              </tr>
            ) : (
              sorted.map((row, idx) => (
                <tr
                  key={row[rowKey] || idx}
                  className="hover:bg-muted/30 transition-colors"
                >
                  {columns.map((col) => {
                    const val = row[col.key];
                    return (
                      <td
                        key={col.key}
                        className={`px-3 py-2 text-foreground ${
                          col.align === "right"
                            ? "text-right tabular-nums"
                            : col.align === "center"
                            ? "text-center"
                            : "text-left font-medium"
                        } ${col.isTotal ? "font-bold text-primary bg-primary/5" : ""}`}
                      >
                        {formatNumber(val)}
                      </td>
                    );
                  })}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
