import { useState, type ReactNode } from "react";
import { Download, FileText, FileSpreadsheet, Filter } from "lucide-react";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
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

export function ExportButton<T>({ data, columns, filename, title, filtersSlot, disabled }: ExportButtonProps<T>) {
  const t = useT();
  const [open, setOpen] = useState(false);

  const exportCSV = () => {
    try {
      download(`${filename}.csv`, toCSV(data, columns), "text/csv;charset=utf-8");
      toast.success("CSV export");
    } catch {
      toast.error(t("toast.error"));
    }
  };

  const exportPDF = () => {
    try {
      const doc = new jsPDF({ orientation: "landscape" });
      const pageTitle = title ?? filename;
      doc.setFontSize(14);
      doc.text(pageTitle, 14, 15);
      doc.setFontSize(9);
      doc.setTextColor(120);
      doc.text(new Date().toLocaleString(), 14, 21);
      autoTable(doc, {
        startY: 26,
        head: [columns.map((c) => c.label)],
        body: data.map((r) =>
          columns.map((c) =>
            String(c.format ? c.format(r) : (r as Record<string, unknown>)[c.key as string] ?? "")
          )
        ),
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [46, 125, 50], textColor: 255 },
        alternateRowStyles: { fillColor: [245, 250, 245] },
      });
      doc.save(`${filename}.pdf`);
      toast.success("PDF export");
    } catch {
      toast.error(t("toast.error"));
    }
  };

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
      </DropdownMenuContent>
    </DropdownMenu>
  );
}