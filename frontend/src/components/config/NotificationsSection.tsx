/**
 * Section Administration — Notifications (accusés de réception).
 * Vue admin : toutes les notifications, filtrables par destinataire, statut
 * de lecture et type de sujet (affectation de bien / transfert de consomptible).
 */
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Bell, Loader2, AlertCircle, Package, Boxes } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import { SearchableSelect } from "@/components/shared/SearchableSelect";
import { DataTable, type Column } from "@/components/shared/DataTable";
import {
  listAdminNotifications, type ApiNotification, type NotificationSubjectType,
} from "@/api/notifications/notifications.api";
import { listAllUsers } from "@/api/users/users.api";
import { useT } from "@/utils/i18n";

export function NotificationsSection() {
  const t = useT();
  const SUBJECT_LABELS: Record<NotificationSubjectType, string> = {
    asset_assignment: t("notifications.subject.assetAssignment"),
    consumable_transfer: t("notifications.subject.consumableTransfer"),
  };
  const [userId, setUserId] = useState<number | null>(null);
  const [isRead, setIsRead] = useState<"all" | "true" | "false">("all");
  const [subjectType, setSubjectType] = useState<"all" | NotificationSubjectType>("all");

  const { data: usersData } = useQuery({
    queryKey: ["users-all"],
    queryFn: listAllUsers,
    staleTime: 300_000,
  });
  const users = [...(usersData ?? [])].sort((a, b) =>
    `${a.firstName} ${a.lastName}`.localeCompare(`${b.firstName} ${b.lastName}`, "fr"),
  );

  const { data, isLoading, isError } = useQuery({
    queryKey: ["notifications-admin", userId, isRead, subjectType],
    queryFn: () =>
      listAdminNotifications({
        page: 1,
        limit: 500,
        user_id: userId ?? undefined,
        is_read: isRead,
        subject_type: subjectType === "all" ? undefined : subjectType,
      }),
  });

  const notifications: ApiNotification[] = data?.data?.data ?? [];

  const columns: Column<ApiNotification>[] = [
    {
      key: "subjectType",
      label: t("notifications.col.type"),
      render: (n) => (
        <span className="inline-flex items-center gap-1.5 text-xs">
          {n.subjectType === "asset_assignment" ? (
            <Package className="h-3.5 w-3.5 text-primary" />
          ) : (
            <Boxes className="h-3.5 w-3.5 text-primary" />
          )}
          {SUBJECT_LABELS[n.subjectType] ?? n.subjectType}
        </span>
      ),
      exportFormat: (n) => SUBJECT_LABELS[n.subjectType] ?? n.subjectType,
    },
    {
      key: "user",
      label: t("notifications.col.recipient"),
      render: (n) => (n.user ? `${n.user.firstName} ${n.user.lastName}` : "—"),
      exportFormat: (n) => (n.user ? `${n.user.firstName} ${n.user.lastName}` : "—"),
    },
    {
      key: "title",
      label: t("notifications.col.title"),
      render: (n) => <span className="font-medium">{n.title}</span>,
    },
    {
      key: "message",
      label: t("notifications.col.message"),
      render: (n) => <span className="text-xs text-muted-foreground">{n.message}</span>,
    },
    {
      key: "isRead",
      label: t("notifications.col.read"),
      render: (n) => (
        <Badge variant={n.isRead ? "secondary" : "default"} className="text-xs">
          {n.isRead ? t("notifications.badge.read") : t("notifications.badge.unread")}
        </Badge>
      ),
      exportFormat: (n) => (n.isRead ? t("notifications.badge.read") : t("notifications.badge.unread")),
    },
    {
      key: "createdAt",
      label: t("common.date"),
      render: (n) => <span className="text-xs tabular-nums">{n.createdAt}</span>,
      sortValue: (n) => n.createdAt,
    },
  ];

  return (
    <div>
      <div className="mb-4 flex items-center gap-2">
        <Bell className="h-5 w-5 text-primary" />
        <div>
          <h2 className="font-semibold text-foreground">{t("notifications.title")}</h2>
          <p className="text-xs text-muted-foreground">
            {t("notifications.subtitle")}
          </p>
        </div>
      </div>

      <div className="mb-4 flex flex-wrap items-end gap-3">
        <div className="w-64 space-y-1.5">
          <Label className="text-xs text-muted-foreground">{t("notifications.col.recipient")}</Label>
          <SearchableSelect
            value={userId}
            onChange={setUserId}
            options={users.map((u) => ({ value: u.id, label: `${u.firstName} ${u.lastName}` }))}
            placeholder={t("logs.filter.allUsers")}
            searchPlaceholder={t("logs.filter.searchUser")}
          />
        </div>
        <div className="w-48 space-y-1.5">
          <Label className="text-xs text-muted-foreground">{t("notifications.col.type")}</Label>
          <Select value={subjectType} onValueChange={(v) => setSubjectType(v as typeof subjectType)}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t("notifications.filter.allTypes")}</SelectItem>
              <SelectItem value="asset_assignment">{t("notifications.subject.assetAssignment")}</SelectItem>
              <SelectItem value="consumable_transfer">{t("notifications.subject.consumableTransfer")}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="w-40 space-y-1.5">
          <Label className="text-xs text-muted-foreground">{t("common.status")}</Label>
          <Select value={isRead} onValueChange={(v) => setIsRead(v as typeof isRead)}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t("notifications.filter.all")}</SelectItem>
              <SelectItem value="false">{t("notifications.filter.unread")}</SelectItem>
              <SelectItem value="true">{t("notifications.filter.read")}</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {isLoading ? (
        <div className="flex h-48 items-center justify-center gap-2 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin" /><span>{t("common.loading")}</span>
        </div>
      ) : isError ? (
        <div className="flex h-48 items-center justify-center gap-2 text-destructive">
          <AlertCircle className="h-5 w-5" /><span>{t("toast.error")}</span>
        </div>
      ) : (
        <DataTable
          data={notifications}
          columns={columns}
          getRowId={(n) => String(n.id)}
          exportFilename="notifications-minepia"
          exportTitle="MINEPIA — Notifications"
          searchKeys={["title", "message"]}
          emptyMessage={t("notifications.empty")}
        />
      )}
    </div>
  );
}
