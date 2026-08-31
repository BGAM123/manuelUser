import type { ReactNode } from "react";
import { cn } from "@/utils/utils";

type Tone = "normal" | "urgent" | "low" | "muted" | "info";

const tones: Record<Tone, string> = {
  normal: "bg-normal/15 text-normal border-normal/30",
  urgent: "bg-urgent/15 text-urgent border-urgent/30",
  low: "bg-low/20 text-low border-low/40",
  muted: "bg-muted text-muted-foreground border-border",
  info: "bg-secondary/15 text-secondary border-secondary/30",
};

export function StatusBadge({ tone = "muted", children, className }: { tone?: Tone; children: ReactNode; className?: string }) {
  return (
    <span className={cn("inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium", tones[tone], className)}>
      {children}
    </span>
  );
}

export function statutTone(s: string): Tone {
  const v = s.toLowerCase();
  if (v.includes("réform") || v.includes("retire")) return "urgent";
  if (v.includes("litig")) return "urgent";
  if (v.includes("maintenance")) return "low";
  if (v.includes("actif") || v.includes("active") || v.includes("clôtur") || v.includes("réalisé")) return "normal";
  if (v.includes("cours") || v.includes("progress") || v.includes("attente")) return "low";
  if (v.includes("plan")) return "info";
  return "muted";
}