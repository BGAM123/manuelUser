/**
 * Section de configuration — Logs d'actions (Administration > Utilisateurs > Logs).
 *
 * GET /logs (voir logs.api.ts) : confirmé en direct (2026-08-27) —
 *   - `page`/`limit` fonctionnent réellement (offset + nombre exact retourné).
 *   - AUCUN champ `meta`/pagination dans la réponse (juste `items`) — impossible
 *     de connaître le nombre total de logs, donc navigation Précédent/Suivant
 *     plutôt qu'une pagination numérotée ("Suivant" désactivé dès qu'une page
 *     renvoie moins de 15 éléments).
 *   - `resource_id` retiré du filtre à la demande explicite (2026-08-27).
 *
 * Recherche : dans le select Utilisateur (SearchableSelect), pas une barre de
 * recherche séparée sur le tableau — demande explicite (2026-08-27).
 */

import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Loader2, ScrollText, X, ChevronLeft, ChevronRight } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { SearchableSelect } from "@/components/shared/SearchableSelect";
import { cn } from "@/utils/utils";
import { useT } from "@/utils/i18n";
import { listLogs, type ApiLogEntry } from "@/api/logs/logs.api";
import { listAllUsers, type ApiUser } from "@/api/users/users.api";

const METHOD_STYLES: Record<string, string> = {
  GET: "border-blue-300 bg-blue-500/15 text-blue-700",
  POST: "border-emerald-300 bg-emerald-500/15 text-emerald-700",
  PUT: "border-amber-300 bg-amber-500/15 text-amber-700",
  PATCH: "border-amber-300 bg-amber-500/15 text-amber-700",
  DELETE: "border-red-300 bg-red-500/15 text-red-700",
};

const PAGE_SIZE = 15;

function formatTimestamp(ts: string | null | undefined): string {
  if (!ts) return "—";
  const d = new Date(ts);
  if (Number.isNaN(d.getTime())) return ts;
  return d.toLocaleString("fr-FR", { dateStyle: "short", timeStyle: "medium" });
}

export function LogsSection() {
  const t = useT();
  const [page, setPage] = useState(1);
  const [userFilterId, setUserFilterId] = useState<number | null>(null);
  const [dateStart, setDateStart] = useState("");
  const [dateEnd, setDateEnd] = useState("");
  const [detail, setDetail] = useState<ApiLogEntry | null>(null);

  useEffect(() => {
    setPage(1);
  }, [userFilterId, dateStart, dateEnd]);

  const { data: usersData } = useQuery({
    queryKey: ["users-all-for-logs"],
    queryFn: () => listAllUsers(),
    staleTime: 5 * 60_000,
  });
  const users: ApiUser[] = usersData ?? [];
  const userOptions = users.map((u) => ({ value: u.id, label: `${u.firstName} ${u.lastName}` }));

  const { data, isLoading, isError } = useQuery({
    queryKey: ["logs", page, userFilterId, dateStart, dateEnd],
    queryFn: () =>
      listLogs({
        page,
        limit: PAGE_SIZE,
        user_id: userFilterId ?? undefined,
        date_start: dateStart || undefined,
        date_end: dateEnd || undefined,
      }),
  });

  const items: ApiLogEntry[] = data?.data?.items ?? [];
  const hasNextPage = items.length >= PAGE_SIZE;

  const hasFilters = userFilterId != null || !!dateStart || !!dateEnd;
  const resetFilters = () => {
    setUserFilterId(null);
    setDateStart("");
    setDateEnd("");
  };

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="text-lg font-semibold">{t("logs.title")}</h2>
          <p className="text-sm text-muted-foreground">
            {t("logs.subtitle")}
          </p>
        </div>
      </div>

      <div className="mb-3 flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-3">
        <div className="w-64 space-y-1.5">
          <Label className="text-xs text-muted-foreground">{t("users.title")}</Label>
          <SearchableSelect
            options={userOptions}
            value={userFilterId}
            onChange={setUserFilterId}
            placeholder={t("logs.filter.allUsers")}
            searchPlaceholder={t("logs.filter.searchUser")}
          />
        </div>
        <div className="w-40 space-y-1.5">
          <Label className="text-xs text-muted-foreground">{t("logs.filter.from")}</Label>
          <Input type="date" value={dateStart} onChange={(e) => setDateStart(e.target.value)} />
        </div>
        <div className="w-40 space-y-1.5">
          <Label className="text-xs text-muted-foreground">{t("logs.filter.to")}</Label>
          <Input type="date" value={dateEnd} onChange={(e) => setDateEnd(e.target.value)} />
        </div>
        {hasFilters && (
          <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={resetFilters}>
            <X className="h-3.5 w-3.5" /> {t("action.reset")}
          </Button>
        )}
      </div>

      {isLoading ? (
        <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin" />
          <span>{t("common.loading")}</span>
        </div>
      ) : isError ? (
        <div className="rounded-xl border border-dashed border-destructive/40 p-12 text-center text-sm text-destructive">
          {t("logs.loadError")}
        </div>
      ) : items.length === 0 ? (
        <div className="rounded-xl border border-dashed border-border p-12 text-center">
          <ScrollText className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
          <p className="text-sm text-muted-foreground">{t("logs.empty")}</p>
        </div>
      ) : (
        <div className="rounded-xl border border-border bg-card shadow-sm overflow-x-auto">
          <table className="w-full min-w-[1100px] text-sm">
            <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
              <tr>
                <th className="px-3 py-3 text-left">{t("logs.col.datetime")}</th>
                <th className="px-3 py-3 text-left">{t("users.title")}</th>
                <th className="px-3 py-3 text-left">{t("logs.col.method")}</th>
                <th className="px-3 py-3 text-left">{t("logs.col.path")}</th>
                <th className="px-3 py-3 text-left">{t("logs.col.action")}</th>
                <th className="px-3 py-3 text-left">{t("logs.col.module")}</th>
                <th className="px-3 py-3 text-left">{t("logs.col.resource")}</th>
                <th className="px-3 py-3 text-left">{t("logs.col.ip")}</th>
                <th className="px-3 py-3 text-left">{t("logs.col.code")}</th>
                <th className="px-3 py-3 text-right">{t("logs.col.duration")}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {items.map((log) => (
                <tr
                  key={log.request_id}
                  className="cursor-pointer hover:bg-muted/30 transition-colors"
                  onClick={() => setDetail(log)}
                >
                  <td className="px-3 py-2.5 text-xs tabular-nums whitespace-nowrap">{formatTimestamp(log.timestamp)}</td>
                  <td className="px-3 py-2.5 text-xs">
                    {log.username ?? <span className="italic text-muted-foreground">{t("logs.anonymous")}</span>}
                  </td>
                  <td className="px-3 py-2.5">
                    <Badge variant="outline" className={cn("text-[10px] font-semibold", METHOD_STYLES[log.method] ?? "text-muted-foreground")}>
                      {log.method}
                    </Badge>
                  </td>
                  <td className="px-3 py-2.5 font-mono text-xs text-muted-foreground max-w-[220px] truncate" title={log.path}>
                    {log.path}
                  </td>
                  <td className="px-3 py-2.5 text-xs">{log.action}</td>
                  <td className="px-3 py-2.5 text-xs text-muted-foreground">{log.module}</td>
                  <td className="px-3 py-2.5 text-xs tabular-nums text-muted-foreground">{log.resource_id ?? "—"}</td>
                  <td className="px-3 py-2.5 text-xs tabular-nums text-muted-foreground">{log.ip}</td>
                  <td className="px-3 py-2.5">
                    <span className={cn("text-xs font-semibold tabular-nums", log.status_code >= 400 ? "text-destructive" : "text-emerald-600")}>
                      {log.status_code}
                    </span>
                  </td>
                  <td className="px-3 py-2.5 text-right text-xs tabular-nums text-muted-foreground">{log.duration_ms} ms</td>
                </tr>
              ))}
            </tbody>
          </table>
          <div className="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs text-muted-foreground">
              {items.length > 1
                ? t("logs.countPlural", { count: items.length })
                : t("logs.count", { count: items.length })}
            </p>
            <div className="flex items-center justify-center gap-1.5">
              <Button
                variant="outline"
                size="sm"
                className="h-8 gap-1 px-2.5 text-xs"
                disabled={page <= 1}
                onClick={() => setPage((p) => p - 1)}
              >
                <ChevronLeft className="h-3.5 w-3.5" /> {t("logs.previous")}
              </Button>
              <span className="inline-flex h-8 min-w-8 items-center justify-center rounded-md bg-primary px-3 text-xs font-semibold text-primary-foreground">
                {page}
              </span>
              <Button
                variant="outline"
                size="sm"
                className="h-8 gap-1 px-2.5 text-xs"
                disabled={!hasNextPage}
                onClick={() => setPage((p) => p + 1)}
              >
                {t("logs.next")} <ChevronRight className="h-3.5 w-3.5" />
              </Button>
            </div>
          </div>
        </div>
      )}

      {detail && (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 backdrop-blur-sm py-8 px-4" onClick={() => setDetail(null)}>
          <div className="w-full max-w-lg rounded-2xl border border-border bg-card shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between border-b border-border px-5 py-3">
              <h3 className="text-sm font-bold text-foreground">{t("logs.detail.title")}</h3>
              <button type="button" onClick={() => setDetail(null)} className="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted">
                <X className="h-4 w-4" />
              </button>
            </div>
            <div className="space-y-2 p-5 text-sm">
              {[
                [t("logs.col.datetime"), formatTimestamp(detail.timestamp)],
                [t("users.title"), detail.username ?? t("logs.anonymous")],
                [t("logs.detail.userId"), detail.user_id != null ? String(detail.user_id) : "—"],
                [t("logs.col.method"), detail.method],
                [t("logs.detail.route"), detail.route ?? "—"],
                [t("logs.col.path"), detail.path],
                [t("logs.col.action"), detail.action],
                [t("logs.col.module"), detail.module],
                [t("logs.detail.resourceId"), detail.resource_id != null ? String(detail.resource_id) : "—"],
                [t("logs.detail.ip"), detail.ip],
                [t("logs.detail.httpCode"), String(detail.status_code)],
                [t("logs.col.duration"), `${detail.duration_ms} ms`],
                [t("logs.detail.environment"), detail.environment],
                [t("logs.detail.requestId"), detail.request_id],
                [t("logs.detail.userAgent"), detail.user_agent],
              ].map(([label, value]) => (
                <div key={label} className="grid grid-cols-3 gap-2 border-b border-border/50 py-1.5 last:border-0">
                  <span className="text-xs font-medium text-muted-foreground">{label}</span>
                  <span className="col-span-2 break-words text-xs">{value}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
