import { useEffect, useMemo, useState, type ReactNode } from "react";
import { Search, ArrowUpDown, ArrowUp, ArrowDown } from "lucide-react";
import { Input } from "@/components/ui/input";
import { cn } from "@/utils/utils";
import { useT } from "@/utils/i18n";
import { Pagination } from "./Pagination";
import { ExportButton, type ExportColumn } from "./ExportButton";

export type Column<T> = {
  key: string;
  label: string;
  render?: (row: T) => ReactNode;
  sortValue?: (row: T) => string | number;
  className?: string;
  exportFormat?: (row: T) => string | number;
  hideOnExport?: boolean;
};

export type DataTableProps<T> = {
  data: T[];
  columns: Column<T>[];
  getRowId: (row: T) => string;
  rowActions?: (row: T) => ReactNode;
  toolbar?: ReactNode;
  searchable?: boolean;
  searchKeys?: (keyof T | string)[];
  exportFilename: string;
  exportTitle?: string;
  emptyMessage?: string;
  selectable?: boolean;
  selectedIds?: string[];
  onSelectionChange?: (ids: string[]) => void;
  onRowClick?: (row: T) => void;
  bulkBar?: ReactNode;
};

export function DataTable<T>({
  data,
  columns,
  getRowId,
  rowActions,
  toolbar,
  searchable = true,
  searchKeys,
  exportFilename,
  exportTitle,
  emptyMessage,
  selectable = false,
  selectedIds,
  onSelectionChange,
  onRowClick,
  bulkBar,
}: DataTableProps<T>) {
  const t = useT();
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [sort, setSort] = useState<{ key: string; dir: "asc" | "desc" } | null>(null);

  const filtered = useMemo(() => {
    if (!query.trim()) return data;
    const q = query.toLowerCase();
    return data.filter((r) => {
      const keys = searchKeys ?? (Object.keys(r as object) as string[]);
      return keys.some((k) => {
        const v = (r as Record<string, unknown>)[k as string];
        return v != null && String(v).toLowerCase().includes(q);
      });
    });
  }, [data, query, searchKeys]);

  const sorted = useMemo(() => {
    if (!sort) return filtered;
    const col = columns.find((c) => c.key === sort.key);
    if (!col) return filtered;
    const getVal = (r: T) =>
      col.sortValue ? col.sortValue(r) : ((r as Record<string, unknown>)[col.key] as string | number);
    return [...filtered].sort((a, b) => {
      const va = getVal(a);
      const vb = getVal(b);
      if (va == null && vb == null) return 0;
      if (va == null) return 1;
      if (vb == null) return -1;
      if (va < vb) return sort.dir === "asc" ? -1 : 1;
      if (va > vb) return sort.dir === "asc" ? 1 : -1;
      return 0;
    });
  }, [filtered, sort, columns]);

  const total = sorted.length;
  const start = (page - 1) * pageSize;
  const paged = sorted.slice(start, start + pageSize);

  // Si le jeu de données change (changement de filtre, corbeille affichée
  // puis masquée, suppression, recherche…) et que la page courante n'existe
  // plus, on revient à la première page. Sans ça, le tableau restait vide
  // jusqu'à un rechargement manuel de la page.
  useEffect(() => {
    if (page > 1 && start >= total) setPage(1);
  }, [page, start, total]);


  const toggleSort = (key: string) => {
    setSort((s) =>
      s?.key === key ? (s.dir === "asc" ? { key, dir: "desc" } : null) : { key, dir: "asc" }
    );
  };

  const exportCols: ExportColumn<T>[] = columns
    .filter((c) => !c.hideOnExport)
    .map((c) => ({
      key: c.key,
      label: c.label,
      format:
        c.exportFormat ??
        ((r: T) => {
          const v = (r as Record<string, unknown>)[c.key];
          return v == null ? "" : (v as string | number);
        }),
    }));

  const selected = new Set(selectedIds ?? []);
  const allPagedSelected =
    paged.length > 0 && paged.every((r) => selected.has(getRowId(r)));
  const somePagedSelected =
    paged.some((r) => selected.has(getRowId(r))) && !allPagedSelected;

  const togglePageAll = () => {
    if (!onSelectionChange) return;
    const pagedIds = paged.map(getRowId);
    if (allPagedSelected) {
      onSelectionChange((selectedIds ?? []).filter((id) => !pagedIds.includes(id)));
    } else {
      const next = new Set([...(selectedIds ?? []), ...pagedIds]);
      onSelectionChange(Array.from(next));
    }
  };
  const toggleRow = (id: string) => {
    if (!onSelectionChange) return;
    const current = selectedIds ?? [];
    onSelectionChange(
      current.includes(id) ? current.filter((x) => x !== id) : [...current, id]
    );
  };

  return (
    <div className="rounded-xl border border-border bg-card shadow-sm">
      {bulkBar ? <div className="border-b border-border p-3 sm:p-4">{bulkBar}</div> : null}
      <div className="flex flex-col gap-3 border-b border-border p-3 sm:p-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex w-full flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
          {searchable ? (
            <div className="relative w-full sm:min-w-[220px] sm:max-w-sm sm:flex-1">
              <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={query}
                onChange={(e) => {
                  setQuery(e.target.value);
                  setPage(1);
                }}
                placeholder={t("action.search") + "…"}
                className="pl-8"
              />
            </div>
          ) : null}
          <div className="flex flex-wrap gap-2 [&>*]:flex-1 sm:[&>*]:flex-none">{toolbar}</div>
        </div>
        <div className="flex justify-end sm:justify-normal">
          <ExportButton data={sorted} columns={exportCols} filename={exportFilename} title={exportTitle} />
        </div>
      </div>

      <div className="-mx-px overflow-x-auto">
        <table className="w-full min-w-[640px] text-sm">
          <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
            <tr>
              {selectable ? (
                <th className="w-10 px-3 py-2.5 sm:px-4 sm:py-3">
                  <input
                    type="checkbox"
                    aria-label="Tout sélectionner"
                    checked={allPagedSelected}
                    ref={(el) => {
                      if (el) el.indeterminate = somePagedSelected;
                    }}
                    onChange={togglePageAll}
                    className="h-4 w-4 cursor-pointer accent-primary"
                  />
                </th>
              ) : null}
              {columns.map((c) => (
                <th key={c.key} className={cn("select-none whitespace-nowrap px-3 py-2.5 text-left font-semibold sm:px-4 sm:py-3", c.className)}>
                  <button type="button" onClick={() => toggleSort(c.key)} className="inline-flex items-center gap-1 hover:text-foreground">
                    {c.label}
                    {sort?.key === c.key ? (
                      sort.dir === "asc" ? (
                        <ArrowUp className="h-3 w-3" />
                      ) : (
                        <ArrowDown className="h-3 w-3" />
                      )
                    ) : (
                      <ArrowUpDown className="h-3 w-3 opacity-40" />
                    )}
                  </button>
                </th>
              ))}
              {rowActions ? <th className="whitespace-nowrap px-3 py-2.5 text-right font-semibold sm:px-4 sm:py-3">{t("common.actions")}</th> : null}
            </tr>
          </thead>
          <tbody>
            {paged.length === 0 ? (
              <tr>
                <td colSpan={columns.length + (rowActions ? 1 : 0) + (selectable ? 1 : 0)} className="px-4 py-12 text-center text-sm text-muted-foreground">
                  {emptyMessage ?? t("common.empty")}
                </td>
              </tr>
            ) : (
              paged.map((row, idx) => {
                const id = getRowId(row);
                const isSel = selected.has(id);
                return (
                <tr
                  key={id}
                  onClick={onRowClick ? () => onRowClick(row) : undefined}
                  className={cn(
                    "border-t border-border transition-colors hover:bg-accent/40",
                    idx % 2 === 1 && "bg-muted/20",
                    isSel && "bg-primary/5",
                    onRowClick && "cursor-pointer",
                  )}
                >
                  {selectable ? (
                    <td className="px-3 py-2.5 sm:px-4 sm:py-3" onClick={(e) => e.stopPropagation()}>
                      <input
                        type="checkbox"
                        aria-label="Sélectionner la ligne"
                        checked={isSel}
                        onChange={() => toggleRow(id)}
                        className="h-4 w-4 cursor-pointer accent-primary"
                      />
                    </td>
                  ) : null}
                  {columns.map((c) => (
                    <td key={c.key} className={cn("px-3 py-2.5 align-middle sm:px-4 sm:py-3", c.className)}>
                      {c.render ? c.render(row) : (((row as Record<string, unknown>)[c.key] as ReactNode) ?? "—")}
                    </td>
                  ))}
                  {rowActions ? (
                    <td className="px-3 py-2 text-right sm:px-4" onClick={(e) => e.stopPropagation()}>
                      <div className="inline-flex items-center gap-1">{rowActions(row)}</div>
                    </td>
                  ) : null}
                </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      <Pagination page={page} pageSize={pageSize} total={total} onPageChange={setPage} onPageSizeChange={(s) => { setPageSize(s); setPage(1); }} />
    </div>
  );
}

export function RowIconButton({
  onClick,
  label,
  icon: Icon,
  tone = "default",
}: {
  onClick: () => void;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  tone?: "default" | "danger" | "primary";
}) {
  const toneCls =
    tone === "danger"
      ? "text-destructive hover:bg-destructive/10"
      : tone === "primary"
        ? "text-primary hover:bg-primary/10"
        : "text-muted-foreground hover:bg-muted hover:text-foreground";
  return (
    <button
      type="button"
      onClick={onClick}
      title={label}
      aria-label={label}
      className={cn("inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors", toneCls)}
    >
      <Icon className="h-4 w-4" />
    </button>
  );
}