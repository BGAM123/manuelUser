/**
 * Section de configuration — Gestion des groupes d'utilisateurs.
 *
 * - Liste + création/édition d'un groupe (nom, description, actif, privilèges)
 * - Gestion des membres (recherche d'un utilisateur, ajout/retrait)
 * - Retrait/restauration d'un privilège individuel pour un membre précis,
 *   sans affecter le groupe ni les autres membres
 *
 * Toutes les modifications de permissions (du groupe ou d'un membre) passent
 * par des endpoints incrémentaux (ajouter UN élément / retirer UN élément),
 * jamais par un remplacement en masse basé sur des données qui pourraient
 * être incomplètes — voir la note en tête de src/api/groupes/groupes.api.ts.
 */

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Plus,
  Pencil,
  Trash2,
  Save,
  X,
  ArrowLeft,
  Loader2,
  Users2,
  Minus,
  Check,
  ChevronDown,
  UserPlus,
} from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { DataTable, RowIconButton, type Column } from "@/components/shared/DataTable";
import { useT } from "@/utils/i18n";
import { cn } from "@/utils/utils";
import { CanAccess } from "@/components/auth/CanAccess";
import {
  listGroupes,
  createGroupe,
  updateGroupe,
  softDeleteGroupe,
  getGroupeById,
  assignPermissionsToGroupe,
  removePermissionFromGroupe,
  type ApiGroupe,
} from "@/api/groupes/groupes.api";
import {
  listPermissions,
  type ApiPermission,
} from "@/api/permissions/permissions.api";
import {
  listUsers,
  assignGroupeToUser,
  removeGroupeFromUser,
  getUserPermissions,
  updateUser,
  type ApiUser,
} from "@/api/users/users.api";

type GroupesView = "liste" | "form" | "membres";

// ─── Helpers partagés ───────────────────────────────────────────────────────
// Le Swagger ne documente pas la forme exacte des relations imbriquées : les
// permissions/groupes peuvent arriver en objets complets, en IDs bruts, ou en
// objets partiels. On résout toujours contre le catalogue complet, et on ne
// laisse jamais un ID invalide (0/undefined/NaN) partir vers le backend.

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function permissionId(p: any): number | undefined {
  const id = typeof p === "number" ? p : p?.id;
  return typeof id === "number" && Number.isFinite(id) ? id : undefined;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function resolvePermission(p: any, catalog: ApiPermission[]): ApiPermission {
  if (p && typeof p === "object" && typeof p.nom === "string") return p as ApiPermission;
  const id = permissionId(p);
  return (
    catalog.find((c) => c.id === id) ??
    { id: id ?? -1, nom: id != null ? `#${id}` : "?", description: "", is_active: true }
  );
}

/** Ne garde que des IDs entiers strictement positifs — jamais 0/NaN/undefined. */
function toValidIds(ids: Iterable<number | undefined>): number[] {
  return [...ids].filter((id): id is number => typeof id === "number" && Number.isInteger(id) && id > 0);
}

function isMemberOfGroupe(u: ApiUser, groupeId: number): boolean {
  return (u.assignedGroupes ?? []).some((g) => permissionId(g) === groupeId);
}

function apiErrorMessage(err: unknown): string | undefined {
  return (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
}

// ─── Section principale ─────────────────────────────────────────────────────

export function GroupesSection() {
  const t = useT();
  const queryClient = useQueryClient();
  const [view, setView] = useState<GroupesView>("liste");
  const [selected, setSelected] = useState<ApiGroupe | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ApiGroupe | null>(null);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["groupes"],
    queryFn: () => listGroupes({ page: 1, limit: 1000 }),
  });

  const groupes = [...(data?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom),
  );

  const reportError = (err: unknown) => toast.error(apiErrorMessage(err) ?? t("toast.error"));

  const toggleMutation = useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) =>
      updateGroupe(id, { is_active }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["groupes"] }),
    onError: reportError,
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => softDeleteGroupe(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["groupes"] });
      toast.success(t("toast.deleted"));
      setDeleteTarget(null);
    },
    onError: reportError,
  });

  // Pas de colonne "Privilèges" : GET /groupes (liste) ne garantit pas de les
  // renvoyer. Seule la fiche détail (GET /groupes/{id}) les garantit — elle
  // est chargée au moment d'ouvrir "Modifier".
  const columns: Column<ApiGroupe>[] = [
    {
      key: "nom",
      label: t("groupes.field.nom"),
      render: (g) => <span className="font-semibold">{g.nom}</span>,
      sortValue: (g) => g.nom,
    },
    {
      key: "description",
      label: t("common.description"),
      render: (g) => (
        <span className="text-xs text-muted-foreground">{g.description || "—"}</span>
      ),
    },
    {
      key: "is_active",
      label: t("common.status"),
      render: (g) => (
        <label className="flex cursor-pointer items-center gap-2">
          <Switch
            checked={g.is_active}
            onCheckedChange={(checked) =>
              toggleMutation.mutate({ id: g.id, is_active: checked })
            }
          />
          <span className="text-xs font-medium">
            {g.is_active ? t("status.active") : t("common.inactive")}
          </span>
        </label>
      ),
      exportFormat: (g) => (g.is_active ? t("status.active") : t("common.inactive")),
    },
  ];

  if (isLoading) {
    return (
      <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin" />
        <span>{t("common.loading")}</span>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="flex h-40 items-center justify-center text-destructive">
        {t("toast.error")}
      </div>
    );
  }

  if (view === "form") {
    return (
      <GroupeForm
        initial={selected}
        onCancel={() => setView("liste")}
        onSaved={() => {
          queryClient.invalidateQueries({ queryKey: ["groupes"] });
          toast.success(t("toast.saved"));
          setView("liste");
        }}
      />
    );
  }

  if (view === "membres" && selected) {
    return (
      <GroupMembersManager
        groupe={selected}
        onBack={() => setView("liste")}
      />
    );
  }

  return (
    <>
      <div>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold">{t("config.groupes")}</h2>
          <CanAccess permission="creation_groupe">
            <Button
              className="gap-2"
              onClick={() => {
                setSelected(null);
                setView("form");
              }}
            >
              <Plus className="h-4 w-4" /> {t("groupes.create")}
            </Button>
          </CanAccess>
        </div>
        <DataTable
          data={groupes}
          columns={columns}
          getRowId={(g) => String(g.id)}
          exportFilename="groupes-minepia"
          exportTitle="MINEPIA — Groupes d'utilisateurs"
          searchKeys={["nom", "description"]}
          rowActions={(g) => (
            <CanAccess permission="creation_groupe">
              <RowIconButton
                icon={Pencil}
                label={t("action.edit")}
                onClick={() => {
                  setSelected(g);
                  setView("form");
                }}
              />
              <RowIconButton
                icon={Users2}
                label={t("groupes.members.manage")}
                onClick={() => {
                  setSelected(g);
                  setView("membres");
                }}
              />
              <RowIconButton
                icon={Trash2}
                label={t("action.delete")}
                tone="danger"
                onClick={() => setDeleteTarget(g)}
              />
            </CanAccess>
          )}
        />
      </div>

      <AlertDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => {
          if (!open) setDeleteTarget(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("groupes.delete.title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("groupes.delete.desc")}{" "}
              <span className="font-semibold text-foreground">{deleteTarget?.nom}</span>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("action.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => {
                if (deleteTarget) deleteMutation.mutate(deleteTarget.id);
              }}
            >
              {t("action.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

// ─── Formulaire création / édition ─────────────────────────────────────────
// Wrapper : attend le chargement complet de la fiche détail (qui garantit les
// permissions) avant de monter le vrai formulaire, une seule fois — pas de
// useEffect de resynchronisation qui pourrait écraser une modification en
// cours si une requête se rafraîchit en arrière-plan.

function GroupeForm({
  initial,
  onCancel,
  onSaved,
}: {
  initial: ApiGroupe | null;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const { data: groupeDetailData, isLoading } = useQuery({
    queryKey: ["groupes", initial?.id],
    queryFn: () => getGroupeById(initial!.id),
    enabled: !!initial,
  });

  if (initial && isLoading) {
    return (
      <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin" />
        <span>{t("common.loading")}</span>
      </div>
    );
  }

  return (
    <GroupeFormFields
      initial={initial ? (groupeDetailData?.data ?? initial) : null}
      onCancel={onCancel}
      onSaved={onSaved}
    />
  );
}

function GroupeFormFields({
  initial,
  onCancel,
  onSaved,
}: {
  initial: ApiGroupe | null;
  onCancel: () => void;
  onSaved: () => void;
}) {
  const t = useT();
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [description, setDescription] = useState(initial?.description ?? "");
  const [isActive, setIsActive] = useState(initial?.is_active ?? true);
  const [selectedPermissionIds, setSelectedPermissionIds] = useState<Set<number>>(
    new Set(toValidIds((initial?.permissions ?? []).map(permissionId))),
  );
  const [permSearch, setPermSearch] = useState("");
  const [permPopoverOpen, setPermPopoverOpen] = useState(false);

  const { data: allPermsData } = useQuery({
    queryKey: ["permissions"],
    queryFn: () => listPermissions({ page: 1, limit: 1000 }),
  });
  const allPermissions: ApiPermission[] = [...(allPermsData?.data?.data ?? [])].sort((a, b) =>
    a.nom.localeCompare(b.nom),
  );
  const filteredPerms = allPermissions.filter(
    (p) =>
      !selectedPermissionIds.has(p.id) &&
      (p.nom.toLowerCase().includes(permSearch.toLowerCase()) ||
        p.description?.toLowerCase().includes(permSearch.toLowerCase())),
  );
  const selectedPermissions = allPermissions.filter((p) => selectedPermissionIds.has(p.id));
  const togglePerm = (id: number) => {
    setSelectedPermissionIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const reportError = (err: unknown) => toast.error(apiErrorMessage(err) ?? t("toast.error"));

  // Création : infos de base d'abord, puis affectation en masse des
  // permissions choisies (POST /groupes/{id}/permissions) — jamais envoyées
  // dans le payload de création lui-même.
  const createMutation = useMutation({
    mutationFn: async () => {
      const result = await createGroupe({ nom: nom.trim(), description, is_active: isActive });
      const ids = toValidIds(selectedPermissionIds);
      if (ids.length > 0 && result.data?.id) {
        await assignPermissionsToGroupe(result.data.id, ids);
      }
      return result;
    },
    onSuccess: () => onSaved(),
    onError: reportError,
  });

  // Édition : infos de base via PATCH (sans le champ permissions), puis diff
  // entre la sélection initiale (fiche détail) et la sélection actuelle —
  // ajouts en masse, retraits un par un — jamais de remplacement intégral.
  const updateMutation = useMutation({
    mutationFn: async () => {
      const result = await updateGroupe(initial!.id, {
        nom: nom.trim(),
        description,
        is_active: isActive,
      });
      const initialIds = new Set(toValidIds((initial?.permissions ?? []).map(permissionId)));
      const toAdd = toValidIds([...selectedPermissionIds].filter((id) => !initialIds.has(id)));
      const toRemove = toValidIds([...initialIds].filter((id) => !selectedPermissionIds.has(id)));
      await Promise.all([
        toAdd.length > 0 ? assignPermissionsToGroupe(initial!.id, toAdd) : Promise.resolve(),
        ...toRemove.map((id) => removePermissionFromGroupe(initial!.id, id)),
      ]);
      return result;
    },
    onSuccess: () => onSaved(),
    onError: reportError,
  });

  const isPending = createMutation.isPending || updateMutation.isPending;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!nom.trim()) return;
    if (!initial) {
      createMutation.mutate();
    } else {
      updateMutation.mutate();
    }
  };

  return (
    <div className="mx-auto w-full max-w-2xl space-y-3">
      <Button
        type="button"
        variant="ghost"
        size="sm"
        onClick={onCancel}
        className="-ml-2 gap-1"
      >
        <ArrowLeft className="h-4 w-4" />
        {t("action.back")}
      </Button>
      <form
        onSubmit={handleSubmit}
        className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
      >
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Users2 className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {initial ? `${t("action.edit")} — ${initial.nom}` : t("groupes.create")}
            </h2>
            <p className="text-xs text-muted-foreground">{t("groupes.subtitle")}</p>
          </div>
        </div>

        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <div className="grid gap-4">
            <div className="space-y-1.5">
              <Label>
                {t("groupes.field.nom")}{" "}
                <span className="text-destructive">*</span>
              </Label>
              <Input
                value={nom}
                onChange={(e) => setNom(e.target.value)}
                placeholder="Gestionnaires régionaux"
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("groupes.field.description")}</Label>
              <Input
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder={t("groupes.field.description.placeholder")}
              />
            </div>
            <div className="flex items-center gap-3">
              <Switch
                id="groupe-active"
                checked={isActive}
                onCheckedChange={setIsActive}
              />
              <Label htmlFor="groupe-active">
                {isActive ? t("status.active") : t("common.inactive")}
              </Label>
            </div>

            {/* ── Sélecteur de privilèges ── */}
            <div className="space-y-1.5">
              <Label>{t("groupes.field.permissions")}</Label>
              {selectedPermissions.length > 0 && (
                <div className="mb-1.5 flex flex-wrap gap-1.5">
                  {selectedPermissions.map((p) => (
                    <Badge key={p.id} variant="secondary" className="gap-1 pr-1 font-mono text-xs">
                      {p.nom}
                      <button
                        type="button"
                        onClick={() => togglePerm(p.id)}
                        className="ml-0.5 flex h-4 w-4 items-center justify-center rounded hover:bg-muted-foreground/20"
                      >
                        <X className="h-3 w-3" />
                      </button>
                    </Badge>
                  ))}
                </div>
              )}
              <Popover open={permPopoverOpen} onOpenChange={setPermPopoverOpen}>
                <PopoverTrigger asChild>
                  <Button type="button" variant="outline" className="w-full justify-between gap-2">
                    <span className="text-muted-foreground">
                      {selectedPermissionIds.size === 0
                        ? t("groupes.selectPermissions")
                        : t("roles.permissionsSelectedCount", { count: selectedPermissionIds.size })}
                    </span>
                    <ChevronDown className="h-4 w-4 shrink-0 opacity-50" />
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
                  <div className="border-b border-border p-2">
                    <Input
                      placeholder={t("action.search")}
                      value={permSearch}
                      onChange={(e) => setPermSearch(e.target.value)}
                      className="h-8"
                    />
                  </div>
                  <div className="max-h-52 overflow-y-auto p-1">
                    {filteredPerms.length === 0 ? (
                      <p className="py-4 text-center text-xs text-muted-foreground">{t("common.empty")}</p>
                    ) : (
                      filteredPerms.map((p) => (
                        <button
                          key={p.id}
                          type="button"
                          onClick={() => togglePerm(p.id)}
                          className="flex w-full items-center gap-2.5 rounded px-2 py-2 text-left text-sm hover:bg-muted"
                        >
                          <Checkbox checked={selectedPermissionIds.has(p.id)} className="pointer-events-none" />
                          <div className="min-w-0">
                            <code className="block truncate font-mono text-xs font-semibold">{p.nom}</code>
                            {p.description && (
                              <span className="block truncate text-xs text-muted-foreground">{p.description}</span>
                            )}
                          </div>
                          {selectedPermissionIds.has(p.id) && (
                            <Check className="ml-auto h-3.5 w-3.5 shrink-0 text-primary" />
                          )}
                        </button>
                      ))
                    )}
                  </div>
                </PopoverContent>
              </Popover>
            </div>
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4 sm:px-6">
          <Button
            type="button"
            variant="outline"
            onClick={onCancel}
            disabled={isPending}
          >
            <X className="h-4 w-4" /> {t("action.cancel")}
          </Button>
          <Button type="submit" className="gap-2" disabled={isPending}>
            {isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Save className="h-4 w-4" />
            )}
            {t("action.save")}
          </Button>
        </div>
      </form>
    </div>
  );
}

// ─── Gestionnaire des membres du groupe ────────────────────────────────────
// Recherche parmi tous les utilisateurs (aucun endpoint "membres du groupe"
// côté backend) et les ajoute/retire via POST/DELETE /users/{userId}/groupes/
// {groupeId}. Pour un membre sélectionné, permet de retirer/restaurer
// individuellement un privilège du groupe via PATCH /users/{id}/permissions.

function GroupMembersManager({
  groupe,
  onBack,
}: {
  groupe: ApiGroupe;
  onBack: () => void;
}) {
  const t = useT();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [expandedMemberId, setExpandedMemberId] = useState<number | null>(null);
  const [permSearch, setPermSearch] = useState("");

  // État réel des permissions du membre déplié — GET /users/{id}/permissions
  // existe désormais (confirmé en direct le 2026-08-21) : plus besoin de
  // deviner "tout accordé", on lit le vrai état granted/revoked/effective.
  const { data: memberPermsData, isLoading: memberPermsLoading } = useQuery({
    queryKey: ["user-permissions", expandedMemberId],
    queryFn: () => getUserPermissions(expandedMemberId!),
    enabled: expandedMemberId != null,
  });
  const revokedForExpandedMember = new Set(
    (memberPermsData?.data?.revoked ?? []).map((p) => p.id),
  );

  const { data: usersData, isLoading: usersLoading } = useQuery({
    queryKey: ["users"],
    queryFn: () => listUsers({ page: 1, limit: 1000 }),
  });
  const allUsers: ApiUser[] = [...(usersData?.data?.data ?? [])].sort((a, b) =>
    `${a.lastName} ${a.firstName}`.localeCompare(`${b.lastName} ${b.firstName}`, "fr"),
  );

  // Fiche détail du groupe (garantit les permissions, contrairement à l'objet
  // "groupe" reçu de la liste).
  const { data: groupeDetailData, isLoading: groupeLoading } = useQuery({
    queryKey: ["groupes", groupe.id],
    queryFn: () => getGroupeById(groupe.id),
  });
  const { data: allPermsData } = useQuery({
    queryKey: ["permissions"],
    queryFn: () => listPermissions({ page: 1, limit: 1000 }),
  });
  const allPermissions: ApiPermission[] = allPermsData?.data?.data ?? [];
  // Bug backend confirmé : GET /groupes/{id} renvoie le bon nombre d'entrées
  // dans "permissions" mais chaque entrée est un tableau vide (aucun id/nom
  // sérialisé). On ignore ce qu'on ne peut pas identifier plutôt que
  // d'afficher un privilège fantôme cliquable (qui provoquait l'erreur
  // "Permission -1 introuvable" au moment de le retirer).
  const groupePermissions: ApiPermission[] = (groupeDetailData?.data.permissions ?? [])
    .map((p) => resolvePermission(p, allPermissions))
    .filter((p) => p.id !== -1);
  const unresolvedPermissionsCount =
    (groupeDetailData?.data.permissions?.length ?? 0) - groupePermissions.length;
  const filteredGroupePermissions = groupePermissions.filter(
    (p) =>
      p.nom.toLowerCase().includes(permSearch.toLowerCase()) ||
      p.description?.toLowerCase().includes(permSearch.toLowerCase()),
  );

  const q = search.toLowerCase();
  const filteredUsers = allUsers.filter(
    (u) =>
      `${u.firstName} ${u.lastName}`.toLowerCase().includes(q) ||
      u.email.toLowerCase().includes(q),
  );

  const reportError = (err: unknown) => toast.error(apiErrorMessage(err) ?? t("toast.error"));
  const invalidateUsers = () => queryClient.invalidateQueries({ queryKey: ["users"] });

  const addMutation = useMutation({
    mutationFn: (userId: number) => assignGroupeToUser(userId, groupe.id),
    onSuccess: invalidateUsers,
    onError: reportError,
  });

  const removeMutation = useMutation({
    mutationFn: (userId: number) => removeGroupeFromUser(userId, groupe.id),
    onSuccess: (_data, userId) => {
      invalidateUsers();
      setExpandedMemberId((current) => (current === userId ? null : current));
    },
    onError: reportError,
  });

  // Activer/désactiver un privilège pour un membre — PUT /users/{id}
  // (updateUser), pas de route /permissions dédiée qui fonctionne (confirmé
  // en direct : PATCH/PUT/POST y répondent 405 ou 404).
  //
  // ⚠️ granted_permission_ids / revoked_permission_ids sont un REMPLACEMENT
  // COMPLET de chaque liste, pas incrémental (confirmé en direct : désactiver
  // une permission réactivait une AUTRE permission déjà révoquée
  // individuellement, parce qu'on n'envoyait que l'id en cours et que ça
  // écrasait le reste de la liste). On part donc toujours de l'état réel
  // actuel (granted/revoked lus via GET /users/{id}/permissions) et on
  // renvoie les listes complètes, pas juste le delta.
  //
  // On renvoie aussi tous les champs déjà connus de l'utilisateur (nom,
  // email, service, rôles) en plus — un PUT partiel a déjà silencieusement
  // effacé des champs ailleurs dans ce projet (voir l'incident affectation).
  const permissionMutation = useMutation({
    mutationFn: ({
      user,
      permissionId: permId,
      revoke,
      currentGranted,
      currentRevoked,
    }: {
      user: ApiUser;
      permissionId: number;
      revoke: boolean;
      currentGranted: number[];
      currentRevoked: number[];
    }) => {
      const newRevoked = revoke
        ? [...new Set([...currentRevoked, permId])]
        : currentRevoked.filter((id) => id !== permId);
      const newGranted = revoke
        ? currentGranted.filter((id) => id !== permId)
        : [...new Set([...currentGranted, permId])];
      return updateUser(user.id, {
        firstName: user.firstName,
        lastName: user.lastName,
        email: user.email,
        matricule: user.matricule ?? undefined,
        cni: user.cni ?? undefined,
        is_active: user.is_active,
        twoFactorEnabled: user.twoFactorEnabled,
        service_id: user.service?.id ?? null,
        role_ids: (user.assignedRoles ?? []).map((r) => r.id),
        revoked_permission_ids: newRevoked,
        granted_permission_ids: newGranted,
      });
    },
    onSuccess: (_data, { user }) => {
      invalidateUsers();
      queryClient.invalidateQueries({ queryKey: ["user-permissions", user.id] });
    },
    onError: reportError,
  });

  const isLoading = usersLoading || groupeLoading;

  return (
    <div className="mx-auto w-full max-w-3xl space-y-4">
      <Button
        type="button"
        variant="ghost"
        size="sm"
        onClick={onBack}
        className="-ml-2 gap-1"
      >
        <ArrowLeft className="h-4 w-4" />
        {t("action.back")}
      </Button>

      <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
        <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <UserPlus className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <h2 className="text-base font-semibold text-foreground">
              {t("groupes.members.manage")} —{" "}
              <span className="text-primary">{groupe.nom}</span>
            </h2>
            <p className="text-xs text-muted-foreground">{t("groupes.members.subtitle")}</p>
          </div>
        </div>

        <div className="px-5 py-5 sm:px-6 sm:py-6">
          <Input
            placeholder={t("action.search")}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="mb-3"
          />

          {isLoading ? (
            <div className="flex h-32 items-center justify-center gap-2 text-muted-foreground">
              <Loader2 className="h-5 w-5 animate-spin" />
              <span>{t("common.loading")}</span>
            </div>
          ) : filteredUsers.length === 0 ? (
            <p className="py-6 text-center text-xs text-muted-foreground">{t("common.empty")}</p>
          ) : (
            <ul className="max-h-[420px] space-y-1 overflow-y-auto">
              {filteredUsers.map((u) => {
                const isMember = isMemberOfGroupe(u, groupe.id);
                const isExpanded = expandedMemberId === u.id;
                const revokedForMember = isExpanded ? revokedForExpandedMember : new Set<number>();
                return (
                  <li key={u.id} className="rounded-lg border border-border bg-card">
                    <div className="flex items-center justify-between gap-2 px-3 py-2">
                      <div className="min-w-0">
                        <span className="block truncate text-sm font-medium">
                          {u.firstName} {u.lastName}
                        </span>
                        <span className="block truncate text-xs text-muted-foreground">
                          {u.email}
                        </span>
                      </div>
                      <div className="flex shrink-0 items-center gap-1.5">
                        {isMember && (groupeDetailData?.data.permissions?.length ?? 0) > 0 && (
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="gap-1.5"
                            onClick={() => {
                              setExpandedMemberId(isExpanded ? null : u.id);
                              setPermSearch("");
                            }}
                          >
                            <ChevronDown
                              className={cn(
                                "h-3.5 w-3.5 transition-transform",
                                isExpanded && "rotate-180",
                              )}
                            />
                            {t("groupes.members.permissions")}
                          </Button>
                        )}
                        {isMember ? (
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="gap-1.5 border-destructive/50 text-destructive hover:bg-destructive/10"
                            disabled={removeMutation.isPending}
                            onClick={() => removeMutation.mutate(u.id)}
                          >
                            <Minus className="h-3.5 w-3.5" /> {t("action.delete")}
                          </Button>
                        ) : (
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="gap-1.5"
                            disabled={addMutation.isPending}
                            onClick={() => addMutation.mutate(u.id)}
                          >
                            <Plus className="h-3.5 w-3.5" /> {t("groupes.members.add")}
                          </Button>
                        )}
                      </div>
                    </div>

                    {isMember && isExpanded && (
                      <div className="border-t border-border bg-muted/20 px-3 py-2.5">
                        <p className="mb-2 text-[11px] text-muted-foreground">
                          {t("groupes.members.permissionsHint")}
                        </p>
                        {unresolvedPermissionsCount > 0 && (
                          <p className="mb-2 text-[11px] font-medium text-destructive">
                            {t("groupes.members.unresolvedPermissions", { count: unresolvedPermissionsCount })}
                          </p>
                        )}
                        {groupePermissions.length > 1 && (
                          <Input
                            placeholder={t("action.search")}
                            value={permSearch}
                            onChange={(e) => setPermSearch(e.target.value)}
                            className="mb-2 h-8 text-xs"
                          />
                        )}
                        {memberPermsLoading ? (
                          <p className="py-3 text-center text-xs text-muted-foreground">
                            {t("common.loading")}
                          </p>
                        ) : filteredGroupePermissions.length === 0 ? (
                          <p className="py-3 text-center text-xs text-muted-foreground">
                            {t("common.empty")}
                          </p>
                        ) : (
                          <ul className="space-y-1">
                            {filteredGroupePermissions.map((p) => {
                              const revoked = revokedForMember.has(p.id);
                              return (
                                <li
                                  key={p.id}
                                  className="flex items-center justify-between gap-2 rounded-md border border-border bg-card px-2.5 py-1.5"
                                >
                                  <code className="min-w-0 truncate font-mono text-xs font-semibold">
                                    {p.nom}
                                  </code>
                                  <Switch
                                    checked={!revoked}
                                    disabled={permissionMutation.isPending || memberPermsLoading}
                                    onCheckedChange={(checked) =>
                                      permissionMutation.mutate({
                                        user: u,
                                        permissionId: p.id,
                                        revoke: !checked,
                                        currentGranted: (memberPermsData?.data?.granted ?? []).map((g) => g.id),
                                        currentRevoked: (memberPermsData?.data?.revoked ?? []).map((r) => r.id),
                                      })
                                    }
                                  />
                                </li>
                              );
                            })}
                          </ul>
                        )}
                      </div>
                    )}
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      </div>
    </div>
  );
}
