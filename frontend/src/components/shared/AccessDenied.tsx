import { ShieldAlert } from "lucide-react";
import { useT } from "@/utils/i18n";

/** Repli affiché par les gardes RBAC (CanAccess) quand l'accès est refusé. */
export function AccessDenied() {
  const t = useT();
  return (
    <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-border py-16 text-center">
      <ShieldAlert className="h-8 w-8 text-muted-foreground" />
      <div>
        <p className="text-sm font-semibold text-foreground">{t("common.accessDenied.title")}</p>
        <p className="mt-1 text-xs text-muted-foreground">{t("common.accessDenied.desc")}</p>
      </div>
    </div>
  );
}
