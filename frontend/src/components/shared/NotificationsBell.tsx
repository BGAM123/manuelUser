/**
 * Cloche de notifications — visible partout, pour l'utilisateur connecté.
 * Affiche ses notifications (affectations de biens, transferts de
 * consomptibles), permet de marquer comme lu / tout marquer comme lu, et
 * d'accuser réception directement depuis une notification "created" de
 * type asset_assignment ou consumable_transfer.
 */
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Bell, Check, CheckCheck, Loader2, Package, Boxes } from "lucide-react";
import { toast } from "sonner";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/utils/utils";
import {
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  acknowledgeAssetAssignment,
  acknowledgeConsumableTransfer,
  type ApiNotification,
} from "@/api/notifications/notifications.api";

function timeAgo(iso: string): string {
  const d = new Date(iso.replace(" ", "T"));
  if (Number.isNaN(d.getTime())) return iso;
  const diffMin = Math.round((Date.now() - d.getTime()) / 60000);
  if (diffMin < 1) return "à l'instant";
  if (diffMin < 60) return `il y a ${diffMin} min`;
  const diffH = Math.round(diffMin / 60);
  if (diffH < 24) return `il y a ${diffH} h`;
  const diffJ = Math.round(diffH / 24);
  return `il y a ${diffJ} j`;
}

function NotificationRow({ n }: { n: ApiNotification }) {
  const queryClient = useQueryClient();
  const [acknowledged, setAcknowledged] = useState(false);

  const readMutation = useMutation({
    mutationFn: () => markNotificationRead(n.id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["notifications"] }),
  });

  const ackMutation = useMutation({
    mutationFn: () =>
      n.subjectType === "asset_assignment"
        ? acknowledgeAssetAssignment(n.subjectId)
        : acknowledgeConsumableTransfer(n.subjectId),
    onSuccess: () => {
      setAcknowledged(true);
      toast.success("Accusé de réception enregistré");
      queryClient.invalidateQueries({ queryKey: ["notifications"] });
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      if (status === 400) {
        // Déjà accusé réception — on considère l'action faite, pas une erreur pour l'utilisateur.
        setAcknowledged(true);
        return;
      }
      toast.error(msg ?? "Erreur lors de l'accusé de réception");
    },
  });

  const canAcknowledge =
    n.type === "created" &&
    (n.subjectType === "asset_assignment" || n.subjectType === "consumable_transfer");

  const Icon = n.subjectType === "asset_assignment" ? Package : Boxes;

  return (
    <div
      className={cn(
        "flex gap-2.5 border-b border-border px-3 py-2.5 last:border-b-0",
        !n.isRead && "bg-primary/5",
      )}
    >
      <Icon className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
      <div className="min-w-0 flex-1">
        <div className="flex items-start justify-between gap-2">
          <p className="text-xs font-semibold text-foreground">{n.title}</p>
          {!n.isRead && (
            <button
              type="button"
              title="Marquer comme lu"
              onClick={() => readMutation.mutate()}
              disabled={readMutation.isPending}
              className="shrink-0 rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
            >
              <Check className="h-3.5 w-3.5" />
            </button>
          )}
        </div>
        <p className="mt-0.5 text-xs text-muted-foreground">{n.message}</p>
        <div className="mt-1 flex items-center justify-between gap-2">
          <span className="text-[10px] text-muted-foreground">{timeAgo(n.createdAt)}</span>
          {canAcknowledge && (
            <button
              type="button"
              onClick={() => ackMutation.mutate()}
              disabled={ackMutation.isPending || acknowledged}
              className={cn(
                "inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium transition-colors",
                acknowledged
                  ? "border-emerald-300 bg-emerald-50 text-emerald-700"
                  : "border-primary/40 text-primary hover:bg-primary/10",
              )}
            >
              {ackMutation.isPending ? (
                <Loader2 className="h-3 w-3 animate-spin" />
              ) : acknowledged ? (
                <Check className="h-3 w-3" />
              ) : null}
              {acknowledged ? "Réception accusée" : "Accuser réception"}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

export function NotificationsBell() {
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();

  const { data, isLoading } = useQuery({
    queryKey: ["notifications", "mine"],
    queryFn: () => listNotifications({ page: 1, limit: 20, is_read: "all" }),
    refetchInterval: 60_000,
  });

  const notifications = data?.data?.data ?? [];
  const unreadCount = notifications.filter((n) => !n.isRead).length;

  const markAllMutation = useMutation({
    mutationFn: () => markAllNotificationsRead(),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["notifications"] }),
  });

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button
          type="button"
          aria-label="Notifications"
          className="relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-foreground/70 hover:bg-muted"
        >
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold text-destructive-foreground">
              {unreadCount > 9 ? "9+" : unreadCount}
            </span>
          )}
        </button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-80 p-0 shadow-lg">
        <div className="flex items-center justify-between border-b border-border px-3 py-2">
          <p className="text-sm font-semibold text-foreground">Notifications</p>
          {unreadCount > 0 && (
            <button
              type="button"
              onClick={() => markAllMutation.mutate()}
              disabled={markAllMutation.isPending}
              className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
            >
              <CheckCheck className="h-3.5 w-3.5" /> Tout marquer comme lu
            </button>
          )}
        </div>
        <div className="max-h-96 overflow-y-auto">
          {isLoading ? (
            <div className="flex items-center justify-center gap-2 py-8 text-xs text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" /> Chargement…
            </div>
          ) : notifications.length === 0 ? (
            <p className="px-4 py-8 text-center text-xs text-muted-foreground">Aucune notification</p>
          ) : (
            notifications.map((n) => <NotificationRow key={n.id} n={n} />)
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}
