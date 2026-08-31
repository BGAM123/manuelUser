/**
 * Contrat de réponse standard de l'API MINEPIA.
 * Toutes les réponses partagent cette enveloppe.
 */
export interface ApiResponse<T> {
  success: boolean;
  status: number;
  message: string;
  data: T;
}

/**
 * Enveloppe de collection paginée retournée par les endpoints de liste.
 */
export interface PaginatedMeta {
  current_page: number;
  limit: number;
  total_items: number;
  total_pages: number;
}

export interface PaginatedData<T> {
  meta: PaginatedMeta;
  data: T[];
}

/** Paramètres de pagination communs à tous les endpoints de liste. */
export interface PaginationParams {
  page?: number;
  limit?: number;
}
