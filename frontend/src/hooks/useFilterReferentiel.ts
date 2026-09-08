/**
 * Hooks React Query pour charger les référentiels des filtres statistiques.
 * Ces données alimentent les selects de StatistiquesFilterBar et AdvancedFilterSheet.
 * staleTime: Infinity car ces données changent très rarement.
 */

import { useQuery } from "@tanstack/react-query";
import { getCartographie } from "@/api/regions/regions.api";
import { listCategories } from "@/api/categories/categories.api";
import { listProjects } from "@/api/projects/projects.api";
import { listEtatBiens } from "@/api/etat-biens/etat-biens.api";
import { listAssetTypes } from "@/api/asset-types/asset-types.api";
import { listServices } from "@/api/services/services.api";

// ── Options formatées pour les selects ────────────────────────────────────

export interface SelectOption {
  value: string;
  label: string;
  id?: number;
}

// ── Cartographie (Régions → Départements → Arrondissements) ──────────────

export function useCartographie() {
  return useQuery({
    queryKey: ["cartographie"],
    queryFn: () => getCartographie(),
    staleTime: Infinity,
    select: (res) => res.data ?? [],
  });
}

/**
 * Options de régions pour le select : [{ value: "1", label: "Centre", id: 1 }, ...]
 * value = id.toString() pour pouvoir stocker l'ID dans FilterState.regionId
 */
export function useRegionOptions() {
  return useQuery({
    queryKey: ["cartographie", "region-options"],
    queryFn: () => getCartographie(),
    staleTime: Infinity,
    select: (res): SelectOption[] => [
      { value: "all", label: "Toutes les régions" },
      ...(res.data ?? []).map((r) => ({
        value: String(r.id),
        label: r.nom,
        id: r.id,
      })),
    ],
  });
}

/**
 * Options de départements filtrées par région.
 * @param regionId ID numérique de la région sélectionnée (null = toutes)
 */
export function useDepartementOptions(regionId?: number | null) {
  return useQuery({
    queryKey: ["cartographie", "dept-options", regionId],
    queryFn: () => getCartographie(),
    staleTime: Infinity,
    select: (res): SelectOption[] => {
      const opts: SelectOption[] = [{ value: "all", label: "Tous les départements" }];
      const carto = res.data ?? [];
      const regions = regionId ? carto.filter((r) => r.id === regionId) : carto;
      regions.forEach((r) => {
        r.departements.forEach((d) => {
          opts.push({ value: String(d.id), label: d.nom, id: d.id });
        });
      });
      return opts;
    },
  });
}

// ── Catégories ────────────────────────────────────────────────────────────

export function useCategorieOptions() {
  return useQuery({
    queryKey: ["categories", "filter-options"],
    queryFn: () => listCategories({ page: 1, limit: 100 }),
    staleTime: Infinity,
    select: (res): SelectOption[] => [
      { value: "all", label: "Toutes les catégories" },
      ...(res.data?.data ?? []).map((c) => ({
        value: String(c.id),
        label: c.nom,
        id: c.id,
      })),
    ],
  });
}

// ── Types de biens (filtrés par catégorie) ────────────────────────────────

export function useAssetTypeOptions(categorieId?: number | null) {
  return useQuery({
    queryKey: ["asset-types", "filter-options", categorieId],
    queryFn: () =>
      listAssetTypes({
        page: 1,
        limit: 200,
        ...(categorieId ? { category_id: categorieId } : {}),
      }),
    staleTime: Infinity,
    select: (res): SelectOption[] => [
      { value: "all", label: "Tous les types" },
      ...(res.data?.data ?? []).map((t) => ({
        value: String(t.id),
        label: t.nom,
        id: t.id,
      })),
    ],
  });
}

// ── Projets / Donateurs ────────────────────────────────────────────────────

export function useProjetOptions() {
  return useQuery({
    queryKey: ["projects", "filter-options"],
    queryFn: () => listProjects({ page: 1, limit: 100 }),
    staleTime: Infinity,
    select: (res): SelectOption[] => [
      { value: "all", label: "Tous les projets / donateurs" },
      ...(res?.data ?? []).map((p) => ({
        value: String(p.id),
        label: p.exercice ? `${p.nom} - ${p.exercice}` : p.nom,
        id: p.id,
      })),
    ],
  });
}

// ── États des biens ────────────────────────────────────────────────────────

export function useEtatBienOptions() {
  return useQuery({
    queryKey: ["etat-biens", "filter-options"],
    queryFn: () => listEtatBiens({ page: 1, limit: 100 }),
    staleTime: Infinity,
    select: (res): SelectOption[] =>
      (res.data?.data ?? []).map((e) => ({
        value: String(e.id),
        label: e.nom,
        id: e.id,
      })),
  });
}

// ── Organigramme (Services) ───────────────────────────────────────────────

export function useOrganigrammeOptions() {
  return useQuery({
    queryKey: ["services", "filter-options"],
    queryFn: () => listServices({ page: 1, limit: 300 }),
    staleTime: Infinity,
    select: (res): SelectOption[] => [
      { value: "all", label: "Toutes les structures" },
      ...(res.data?.data ?? []).map((s) => ({
        value: String(s.id),
        label: s.sigle ? `${s.sigle} — ${s.nom}` : s.nom,
        id: s.id,
      })),
    ],
  });
}

// ── Hiérarchie Catégories → Types (pour le treeview) ─────────────────────

import { getCategorieHierarchie } from "@/api/categories/categories.api";
import type { ApiAssetType } from "@/api/asset-types/asset-types.api";

export interface CatTypeNode {
  categorie: { id: number; nom: string };
  types: Array<{ id: number; nom: string }>;
}

/**
 * Charge toutes les catégories avec leurs types de biens associés.
 * Construit l'arbre Catégorie → [Types] utilisé par FilterCategorieTree.
 */
export function useCategorieHierarchie() {
  return useQuery({
    queryKey: ["categorie-hierarchie", "tree"],
    queryFn: async (): Promise<CatTypeNode[]> => {
      // 1. Charger toutes les catégories
      const { data: catRes } = await import("@/api/categories/categories.api").then(m =>
        m.listCategories({ page: 1, limit: 100 })
      );
      const cats = catRes?.data ?? [];

      // 2. Charger tous les types (une seule requête, sans filtre)
      const { data: typeRes } = await listAssetTypes({ page: 1, limit: 500 });
      const types: ApiAssetType[] = typeRes?.data ?? [];

      // 3. Regrouper les types sous leur catégorie
      return cats.map((c) => ({
        categorie: { id: c.id, nom: c.nom },
        types: types
          .filter((t) => t.category_id === c.id)
          .map((t) => ({ id: t.id, nom: t.nom }))
          .sort((a, b) => a.nom.localeCompare(b.nom, "fr")),
      })).sort((a, b) => a.categorie.nom.localeCompare(b.categorie.nom, "fr"));
    },
    staleTime: Infinity,
  });
}

// ── Arbre Organigramme services (hiérarchie Symfony) ─────────────────────

export interface OrgNode {
  id: number;
  nom: string;
  sigle?: string;
  children: OrgNode[];
}

function buildOrgTree(nodes: any[]): OrgNode[] {
  const map = new Map<number, OrgNode>();
  const roots: OrgNode[] = [];

  nodes.forEach((n) => {
    map.set(n.id, { id: n.id, nom: n.nom, sigle: n.sigle || undefined, children: [] });
  });

  nodes.forEach((n) => {
    const node = map.get(n.id)!;
    const parentId = n.parent_id?.id ?? null;
    if (parentId && map.has(parentId)) {
      map.get(parentId)!.children.push(node);
    } else {
      roots.push(node);
    }
  });

  return roots;
}

/**
 * Charge l'organigramme des services sous forme d'arbre hiérarchique.
 * Utilisé par FilterOrganigramme.
 */
export function useOrganigrammeTree() {
  return useQuery({
    queryKey: ["organigramme", "tree"],
    queryFn: async (): Promise<OrgNode[]> => {
      const { listServices } = await import("@/api/services/services.api");
      const res = await listServices({ page: 1, limit: 500 });
      const services = res.data?.data ?? [];
      return buildOrgTree(services);
    },
    staleTime: Infinity,
  });
}
