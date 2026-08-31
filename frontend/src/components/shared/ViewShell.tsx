import type { ReactNode } from "react";
import { ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useT } from "@/utils/i18n";

export function ViewShell({
  title,
  subtitle,
  actions,
  onBack,
  children,
}: {
  title: string;
  subtitle?: string;
  actions?: ReactNode;
  onBack?: () => void;
  children: ReactNode;
}) {
  const t = useT();
  return (
    <div className="animate-in fade-in-50 duration-200">
      <div className="mb-4 flex flex-col gap-3 border-b border-border pb-3 sm:mb-6 sm:pb-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex min-w-0 items-center gap-2 sm:gap-3">
          {onBack ? (
            <Button variant="ghost" size="sm" onClick={onBack} className="gap-1 -ml-2 shrink-0">
              <ArrowLeft className="h-4 w-4" />
              <span className="hidden sm:inline">{t("action.back")}</span>
            </Button>
          ) : null}
          <div className="min-w-0">
            <h1 className="truncate text-xl font-bold tracking-tight text-foreground sm:text-2xl">{title}</h1>
            {subtitle ? <p className="mt-0.5 truncate text-xs text-muted-foreground sm:text-sm">{subtitle}</p> : null}
          </div>
        </div>
        {actions ? <div className="flex flex-wrap items-center gap-2 [&>*]:min-w-0">{actions}</div> : null}
      </div>
      <div>{children}</div>
    </div>
  );
}