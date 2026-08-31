import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Eye, EyeOff, LogIn, User, Lock, Globe, Moon, Sun, ShieldCheck } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useI18n, useT } from "@/utils/i18n";
import { useTheme } from "@/components/theme/ThemeProvider";
import { loginApi } from "@/api/authentication/auth.api";
import api from "@/api/axios";
import { USER_KEY } from "@/api/axios";
import type { AuthUser } from "@/api/authentication/auth.api";
import type { ApiResponse } from "@/api/types";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

function AuthShell() {
  const t = useT();
  const { lang, setLang } = useI18n();
  const { theme, toggle } = useTheme();

  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden p-4">
      {/* Image en arrière-plan plein écran */}
      <img
        src="/minepia-login-photo.jpg"
        alt="Bureau institutionnel MINEPIA"
        className="absolute inset-0 -z-20 h-full w-full object-cover"
      />
      <div className="absolute inset-0 -z-10 bg-black/50" />

      {/* Contrôles langue / thème */}
      <div className="absolute right-4 top-4 flex items-center gap-1 rounded-lg bg-background/80 p-1 shadow-sm backdrop-blur">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="sm" className="gap-1.5">
              <Globe className="h-4 w-4" />
              <span className="text-xs font-semibold uppercase">{lang}</span>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={() => setLang("fr")}>Français</DropdownMenuItem>
            <DropdownMenuItem onClick={() => setLang("en")}>English</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <Button variant="ghost" size="icon" onClick={toggle}>
          {theme === "dark" ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
        </Button>
      </div>

      {/* Carte centrée */}
      <div className="w-full max-w-md">
        {/* En-tête MINEPIA — HORS de la carte, centré */}
        <div className="mb-6 flex flex-col items-center text-center">
          <img
            src="/minepia-logo.png"
            alt="MINEPIA"
            className="h-24 w-24 rounded-full bg-white object-contain p-1 shadow-lg ring-1 ring-white/40 sm:h-28 sm:w-28"
          />
          <p className="mt-3 text-3xl font-extrabold leading-tight text-white drop-shadow sm:text-4xl">
            MINEPIA
          </p>
          <p className="mt-1 max-w-xs text-xs font-semibold leading-snug text-white/90 drop-shadow sm:text-sm">
            Ministère de l'Élevage, des Pêches et des Industries Animales
          </p>
        </div>

        <div className="rounded-2xl border border-border bg-card/95 p-6 shadow-2xl backdrop-blur sm:p-8">
          <SignInView />
        </div>

        <p className="mt-6 text-center text-[11px] text-white/80 drop-shadow">
          © {new Date().getFullYear()} Ministère de l'Élevage, des Pêches et des Industries Animales — République du Cameroun
        </p>
      </div>
    </div>
  );
}

function SignInView() {
  const t = useT();
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [show, setShow] = useState(false);
  const [loading, setLoading] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim() || !password.trim()) return;
    setLoading(true);
    try {
      // Étape 1 — authentification (bloquante, indispensable)
      await loginApi({ email, password });

      // Étape 2 — profil en arrière-plan (non bloquante)
      // La navigation n'attend pas ce fetch ; le heartbeat le retentera si besoin.
      api
        .get<ApiResponse<AuthUser>>("/profile")
        .then((res) => localStorage.setItem(USER_KEY, JSON.stringify(res.data.data)))
        .catch(() => {
          /* silencieux — AppShell affichera les données dès que disponibles */
        });

      toast.success("Connexion réussie");
      navigate("/dashboard", { replace: true });
    } catch (err: unknown) {
      const axiosErr = err as { response?: { status?: number } };
      if (axiosErr.response?.status === 401) {
        toast.error("Identifiants incorrects. Veuillez réessayer.");
      } else {
        toast.error("Impossible de joindre le serveur. Vérifiez votre connexion.");
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={submit}>
      {/* Titre + icône cadenas */}
      <div className="flex flex-col items-center text-center">
        <div className="grid h-12 w-12 place-items-center rounded-full bg-primary/10 text-primary">
          <Lock className="h-5 w-5" />
        </div>
        <h2 className="mt-3 text-2xl font-extrabold text-foreground">{t("auth.connexionTitle")}</h2>
        <p className="mt-1 max-w-[280px] text-sm text-muted-foreground">
          {t("auth.connexionSubtitle")}
        </p>
      </div>

      <div className="mt-6 space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="email" className="text-sm font-semibold">
            {t("auth.email")} <span className="text-destructive">*</span>
          </Label>
          <div className="relative">
            <User className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary" />
            <Input
              id="email"
              type="email"
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="votre.email@minepia.cm"
              className="h-11 pl-9"
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="password" className="text-sm font-semibold">
            {t("auth.password")} <span className="text-destructive">*</span>
          </Label>
          <div className="relative">
            <Lock className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary" />
            <Input
              id="password"
              type={show ? "text" : "password"}
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder={t("auth.passwordPh")}
              className="h-11 pl-9 pr-10"
            />
            <button
              type="button"
              onClick={() => setShow((s) => !s)}
              className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-primary hover:text-primary/80"
              aria-label="toggle password"
            >
              {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          </div>
        </div>
      </div>

      <Button type="submit" disabled={loading} className="mt-6 h-12 w-full gap-2 text-base font-semibold">
        <LogIn className="h-5 w-5" />
        {loading ? "…" : t("auth.signin")}
      </Button>

      {/* Bloc sécurité */}
      <div className="mt-5 flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4">
        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary/10 text-primary">
          <ShieldCheck className="h-4 w-4" />
        </div>
        <div className="min-w-0">
          <p className="text-sm font-semibold text-primary">{t("auth.security.title")}</p>
          <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
            {t("auth.security.body")}
          </p>
        </div>
      </div>
    </form>
  );
}

export default AuthShell;
