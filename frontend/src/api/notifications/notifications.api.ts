/**
 * Appels API — Notifications et accusés de réception, rattachés aux
 * affectations de biens et transferts de consomptibles.
 */

import api from "@/api/axios";
import type { ApiResponse, PaginatedData } from "@/api/types";

export type NotificationSubjectType = "asset_assignment" | "consumable_transfer";

export interface ApiNotification {
  id: number;
  subjectType: NotificationSubjectType;
  subjectId: number;
  type: string;
  title: string;
  message: string;
  isRead: boolean;
  readAt: string | null;
  createdAt: string;
  // Présent uniquement sur GET /notifications/admin (destinataire).
  user?: { id: number; firstName: string; lastName: string } | null;
}

export interface ListNotificationsParams {
  page?: number;
  limit?: number;
  /** "false" | "true" | "all" — défaut "all" côté API */
  is_read?: "false" | "true" | "all";
  subject_type?: NotificationSubjectType;
}

export interface ListAdminNotificationsParams extends ListNotificationsParams {
  user_id?: number;
}

/** GET /notifications — notifications de l'utilisateur connecté. */
export async function listNotifications(
  params: ListNotificationsParams = {},
): Promise<ApiResponse<PaginatedData<ApiNotification>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiNotification>>>("/notifications", { params });
  return response.data;
}

/** GET /notifications/admin — [Admin] toutes les notifications, ou celles d'un utilisateur donné. */
export async function listAdminNotifications(
  params: ListAdminNotificationsParams = {},
): Promise<ApiResponse<PaginatedData<ApiNotification>>> {
  const response = await api.get<ApiResponse<PaginatedData<ApiNotification>>>("/notifications/admin", { params });
  return response.data;
}

/** PATCH /notifications/read-all — marque toutes les notifications de l'utilisateur connecté comme lues. */
export async function markAllNotificationsRead(): Promise<ApiResponse<{ updated: number }>> {
  const response = await api.patch<ApiResponse<{ updated: number }>>("/notifications/read-all");
  return response.data;
}

/** PATCH /notifications/{id}/read — marque une notification comme lue (idempotent). */
export async function markNotificationRead(id: number): Promise<ApiResponse<null>> {
  const response = await api.patch<ApiResponse<null>>(`/notifications/${id}/read`);
  return response.data;
}

/** POST /asset-assignments/{id}/acknowledge — accuser réception d'une affectation de bien. */
export async function acknowledgeAssetAssignment(id: number): Promise<ApiResponse<null>> {
  const response = await api.post<ApiResponse<null>>(`/asset-assignments/${id}/acknowledge`);
  return response.data;
}

/** POST /consumable-transfers/{id}/acknowledge — accuser réception d'un transfert de consomptible. */
export async function acknowledgeConsumableTransfer(id: number): Promise<ApiResponse<null>> {
  const response = await api.post<ApiResponse<null>>(`/consumable-transfers/${id}/acknowledge`);
  return response.data;
}
