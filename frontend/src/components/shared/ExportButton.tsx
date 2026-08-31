import { useState, type ReactNode } from "react";
import { Download, FileText, FileSpreadsheet, Filter, Table2 } from "lucide-react";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import * as XLSX from "xlsx";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { toast } from "sonner";
import { useT } from "@/utils/i18n";
import { useIsAdmin } from "@/hooks/useIsAdmin";
import { createMinepiaPdfHeaderRenderer, MINEPIA_PDF_HEADER_HEIGHT } from "@/services/minepiaDocumentHeader";

export type ExportColumn<T> = {
  key: keyof T | string;
  label: string;
  format?: (row: T) => string | number;
};

export type ExportButtonProps<T> = {
  data: T[];
  columns: ExportColumn<T>[];
  filename: string;
  title?: string;
  filtersSlot?: ReactNode;
  disabled?: boolean;
};

function toCSV<T>(rows: T[], columns: ExportColumn<T>[]) {
  const escape = (v: unknown) => {
    const s = v == null ? "" : String(v);
    return /[",\n;]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  const header = columns.map((c) => escape(c.label)).join(";");
  const body = rows
    .map((r) =>
      columns
        .map((c) => escape(c.format ? c.format(r) : (r as Record<string, unknown>)[c.key as string]))
        .join(";")
    )
    .join("\n");
  return "\uFEFF" + header + "\n" + body;
}

function download(filename: string, content: BlobPart, mime: string) {
  const blob = new Blob([content], { type: mime });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

export function useExportActions<T>({ data, columns, filename, title }: Pick<ExportButtonProps<T>, "data" | "columns" | "filename" | "title">) {
  const t = useT();

  const today = new Date().toISOString().slice(0, 10);
  const filenameWithDate = `${filename}_${today}`;

  const exportCSV = () => {
    try {
      download(`${filenameWithDate}.csv`, toCSV(data, columns), "text/csv;charset=utf-8");
      toast.success("CSV export");
    } catch {
      toast.error(t("toast.error"));
    }
  };

  const exportXLSX = () => {
    try {
      const rows = data.map((r) =>
        Object.fromEntries(
          columns.map((c) => [
            c.label,
            c.format ? c.format(r) : ((r as Record<string, unknown>)[c.key as string] ?? ""),
          ]),
        ),
      );
      const ws = XLSX.utils.json_to_sheet(rows);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, "Données");
      XLSX.writeFile(wb, `${filenameWithDate}.xlsx`);
      toast.success("Excel export");
    } catch {
      toast.error(t("toast.error"));
    }
  };

  const exportPDF = async () => {
    try {
      const doc = new jsPDF({ orientation: "landscape" });
      const renderHeader = await createMinepiaPdfHeaderRenderer(doc);
      renderHeader();
      const pageTitle = title ?? filename;
      doc.setFontSize(14);
      doc.text(pageTitle, 14, MINEPIA_PDF_HEADER_HEIGHT + 5);
      doc.setFontSize(9);
      doc.setTextColor(120);
      doc.text(new Date().toLocaleString(), 14, MINEPIA_PDF_HEADER_HEIGHT + 11);
      autoTable(doc, {
        startY: MINEPIA_PDF_HEADER_HEIGHT + 16,
        margin: { top: MINEPIA_PDF_HEADER_HEIGHT + 5 },
        head: [columns.map((c) => c.label)],
        body: data.map((r) =>
          columns.map((c) =>
            String(c.format ? c.format(r) : (r as Record<string, unknown>)[c.key as string] ?? "")
          )
        ),
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [46, 125, 50], textColor: 255 },
        alternateRowStyles: { fillColor: [245, 250, 245] },
        didDrawPage: () => { renderHeader(); },
      });
      doc.save(`${filenameWithDate}.pdf`);
      toast.success("PDF export");
    } catch {
      toast.error(t("toast.error"));
    }
  };

  return { exportCSV, exportXLSX, exportPDF };
}

export function ExportButton<T>({ data, columns, filename, title, filtersSlot, disabled }: ExportButtonProps<T>) {
  const t = useT();
  const isAdmin = useIsAdmin();
  const [open, setOpen] = useState(false);
  const { exportCSV, exportXLSX, exportPDF } = useExportActions({ data, columns, filename, title });

  const trigger = (
    <Button variant="outline" size="sm" disabled={disabled} className="gap-2">
      <Download className="h-4 w-4" />
      {t("action.export")}
    </Button>
  );

  if (filtersSlot) {
    return (
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>{trigger}</PopoverTrigger>
        <PopoverContent align="end" className="w-80 p-4">
          <div className="flex items-center gap-2 pb-3 text-sm font-semibold">
            <Filter className="h-4 w-4" /> {t("action.filter")}
          </div>
          <div className="space-y-3 pb-3">{filtersSlot}</div>
          <div className="flex gap-2 border-t border-border pt-3">
            <Button size="sm" className="flex-1 gap-2" onClick={exportPDF}>
              <FileText className="h-4 w-4" /> PDF
            </Button>
            <Button size="sm" variant="secondary" className="flex-1 gap-2" onClick={exportCSV}>
              <FileSpreadsheet className="h-4 w-4" /> CSV
            </Button>
            {isAdmin && (
              <Button size="sm" variant="secondary" className="flex-1 gap-2" onClick={exportXLSX}>
                <Table2 className="h-4 w-4" /> Excel
              </Button>
            )}
          </div>
        </PopoverContent>
      </Popover>
    );
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuLabel>{t("action.export")}</DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={exportPDF} className="gap-2">
          <FileText className="h-4 w-4" /> PDF
        </DropdownMenuItem>
        <DropdownMenuItem onClick={exportCSV} className="gap-2">
          <FileSpreadsheet className="h-4 w-4" /> CSV
        </DropdownMenuItem>
        {isAdmin && (
          <DropdownMenuItem onClick={exportXLSX} className="gap-2">
            <Table2 className="h-4 w-4" /> Excel
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}