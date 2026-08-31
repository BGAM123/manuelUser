import type { LucideIcon } from "lucide-react";
import { cn } from "@/utils/utils";

export function StatCard({
  label,
  value,
  hint,
  icon: Icon,
  tone = "primary",
  className,
}: {
  label: string;
  value: string;
  hint?: string;
  icon: LucideIcon;
  tone?: "primary" | "secondary" | "urgent" | "low";
  className?: string;
}) {
  const toneMap = {
    primary: "bg-primary/10 text-primary",
    secondary: "bg-secondary/10 text-secondary",
    urgent: "bg-urgent/10 text-urgent",
    low: "bg-low/20 text-low",
  } as const;
  return (
    <div className={cn("flex flex-col gap-3 rounded-lg border border-border bg-card p-4 shadow-sm transition hover:shadow-md sm:p-5", className)}>
      <div className={cn("grid h-11 w-11 shrink-0 place-items-center rounded-full sm:h-12 sm:w-12", toneMap[tone])}>
        <Icon className="h-5 w-5 sm:h-6 sm:w-6" />
      </div>
      <div className="min-w-0">
        <p className="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground sm:text-[11px]">
          {label}
        </p>
        <p className="mt-1 break-words text-xl font-extrabold leading-tight text-foreground sm:text-2xl">
          {value}
        </p>
        {hint ? <p className="mt-1 text-[11px] text-muted-foreground">{hint}</p> : null}
      </div>
    </div>
  );
}