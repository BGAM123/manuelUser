/**
 * Page — Profil de l'utilisateur connecté.
 * Affiche les informations du compte en lecture seule + changement de mot de passe.
 */

import { useState } from "react";
import { useQuery, useMutation } from "@tanstack/react-query";
import { Eye, EyeOff, Save, Loader2, User, KeyRound } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { AppShell } from "@/components/shared/AppShell";
import { ViewShell } from "@/components/shared/ViewShell";
import { useT } from "@/utils/i18n";
import { getProfile, updateProfilePassword } from "@/api/profile/profile.api";

export default function Profile() {
  const t = useT();
  const [password, setPassword] = useState("");
  const [passwordConfirm, setPasswordConfirm] = useState("");
  const [showPwd, setShowPwd] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [pwdError, setPwdError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["profile"],
    queryFn: getProfile,
  });
  const profile = data?.data;

  const passwordMutation = useMutation({
    mutationFn: updateProfilePassword,
    onSuccess: () => {
      toast.success(t("toast.saved"));
      setPassword("");
      setPasswordConfirm("");
      setPwdError(null);
    },
    onError: (err: unknown) => {
      const apiMsg = (err as { response?: { data?: { message?: string } } })
        ?.response?.data?.message;
      toast.error(apiMsg ?? t("toast.error"));
    },
  });

  const handlePasswordSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (password.length < 8) {
      setPwdError(t("profile.password.minLength"));
      return;
    }
    if (password !== passwordConfirm) {
      setPwdError(t("profile.password.mismatch"));
      return;
    }
    setPwdError(null);
    passwordMutation.mutate({ password, passwordConfirm });
  };

  return (
    <AppShell>
      <ViewShell title={t("profile.title")} subtitle={t("profile.subtitle")}>
        <div className="mx-auto max-w-2xl space-y-6">
          {/* ── Informations du compte ── */}
          <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
            <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <User className="h-5 w-5" />
              </div>
              <div>
                <h2 className="text-base font-semibold">{t("profile.info")}</h2>
                <p className="text-xs text-muted-foreground">{t("profile.subtitle")}</p>
              </div>
            </div>

            {isLoading ? (
              <div className="flex h-40 items-center justify-center gap-2 text-muted-foreground">
                <Loader2 className="h-5 w-5 animate-spin" />
                <span>{t("common.loading")}</span>
              </div>
            ) : profile ? (
              <dl className="divide-y divide-border px-5 py-4 sm:px-6">
                <Row label={t("profile.fullName")}>
                  <span className="font-semibold">
                    {profile.firstName} {profile.lastName}
                  </span>
                </Row>
                <Row label="Email">
                  <span className="font-mono text-sm">{profile.email}</span>
                </Row>
                <Row label={t("profile.service")}>
                  {profile.service ? (
                    <span>
                      {profile.service.nom}
                      {profile.service.sigle && (
                        <span className="ml-1 text-xs text-muted-foreground">
                          ({profile.service.sigle})
                        </span>
                      )}
                    </span>
                  ) : (
                    <span className="text-muted-foreground">—</span>
                  )}
                </Row>
                <Row label={t("profile.roles")}>
                  <div className="flex flex-wrap gap-1">
                    {profile.assignedRoles.length > 0 ? (
                      profile.assignedRoles.map((r) => (
                        <Badge key={r.id} variant="secondary">
                          {r.nom}
                        </Badge>
                      ))
                    ) : (
                      <span className="text-muted-foreground">{t("users.noRole")}</span>
                    )}
                  </div>
                </Row>
                <Row label={t("profile.twoFactor")}>
                  <Badge
                    variant={profile.twoFactorEnabled ? "default" : "outline"}
                    className={
                      profile.twoFactorEnabled
                        ? "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400"
                        : "text-muted-foreground"
                    }
                  >
                    {profile.twoFactorEnabled ? t("profile.twoFactor.on") : t("profile.twoFactor.off")}
                  </Badge>
                </Row>
                <Row label={t("profile.memberSince")}>
                  <span className="text-sm text-muted-foreground">{profile.createdAt}</span>
                </Row>
              </dl>
            ) : null}
          </div>

          {/* ── Changement de mot de passe ── */}
          <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
            <div className="flex items-start gap-3 border-b border-border bg-muted/30 px-5 py-4 sm:px-6">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <KeyRound className="h-5 w-5" />
              </div>
              <div>
                <h2 className="text-base font-semibold">{t("profile.changePassword")}</h2>
                <p className="text-xs text-muted-foreground">{t("profile.changePassword.hint")}</p>
              </div>
            </div>

            <form onSubmit={handlePasswordSubmit} className="px-5 py-5 sm:px-6 sm:py-6">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label>
                    {t("profile.newPassword")} <span className="text-destructive">*</span>
                  </Label>
                  <div className="relative">
                    <Input
                      type={showPwd ? "text" : "password"}
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className="pr-10"
                      autoComplete="new-password"
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setShowPwd((v) => !v)}
                      className="absolute inset-y-0 right-2 flex items-center text-muted-foreground hover:text-foreground"
                    >
                      {showPwd ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>
                <div className="space-y-1.5">
                  <Label>
                    {t("profile.confirmPassword")} <span className="text-destructive">*</span>
                  </Label>
                  <div className="relative">
                    <Input
                      type={showConfirm ? "text" : "password"}
                      value={passwordConfirm}
                      onChange={(e) => setPasswordConfirm(e.target.value)}
                      className="pr-10"
                      autoComplete="new-password"
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setShowConfirm((v) => !v)}
                      className="absolute inset-y-0 right-2 flex items-center text-muted-foreground hover:text-foreground"
                    >
                      {showConfirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>
              </div>
              {pwdError && (
                <p className="mt-3 text-xs font-medium text-destructive">{pwdError}</p>
              )}
              <div className="mt-4 flex justify-end">
                <Button type="submit" className="gap-2" disabled={passwordMutation.isPending}>
                  {passwordMutation.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <Save className="h-4 w-4" />
                  )}
                  {t("action.save")}
                </Button>
              </div>
            </form>
          </div>
        </div>
      </ViewShell>
    </AppShell>
  );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 py-3 sm:flex-row sm:items-start sm:gap-4">
      <dt className="w-40 shrink-0 text-xs font-medium text-muted-foreground">{label}</dt>
      <dd className="text-sm text-foreground">{children}</dd>
    </div>
  );
}
