import { useState, useEffect, useRef, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { useQueryClient } from "@tanstack/react-query";
import { Eye, EyeOff, LogIn, User, Lock, Globe, Moon, Sun, ShieldCheck, KeyRound, Loader2, ArrowLeft } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useI18n, useT } from "@/utils/i18n";
import { useTheme } from "@/components/theme/ThemeProvider";
import { loginApi, verifyOtpApi } from "@/api/authentication/auth.api";
import api from "@/api/axios";
import { USER_KEY } from "@/api/axios";
import { emitAuthChanged } from "@/utils/authEvents";
import type { AuthUser } from "@/api/authentication/auth.api";
import type { ApiResponse } from "@/api/types";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { LOGIN_BACKGROUNDS } from "@/data/loginBackgrounds";

// ─── Utilitaire shuffle (Fisher-Yates) ─────────────────────────────────────
function shuffled<T>(arr: T[]): T[] {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

// ─── Composant Slideshow ────────────────────────────────────────────────────
function LoginSlideshow() {
  const prefersReducedMotion =
    typeof window !== "undefined" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const images = useRef(shuffled(LOGIN_BACKGROUNDS));
  const [current, setCurrent] = useState(0);
  const [next, setNext] = useState<number | null>(null);
  const [transitioning, setTransitioning] = useState(false);

  const advance = useCallback(() => {
    const nextIdx = (current + 1) % images.current.length;
    // Précharger l'image suivante
    const img = new Image();
    img.src = encodeURI(images.current[nextIdx]);
    img.onload = () => {
      setNext(nextIdx);
      setTransitioning(true);
      setTimeout(() => {
        setCurrent(nextIdx);
        setNext(null);
        setTransitioning(false);
      }, 500);
    };
    // Fallback si l'image ne se charge pas
    img.onerror = () => {
      setCurrent(nextIdx);
    };
  }, [current]);

  useEffect(() => {
    if (prefersReducedMotion) return;
    const id = setInterval(advance, 30_000);
    return () => clearInterval(id);
  }, [advance, prefersReducedMotion]);

  return (
    <>
      {/* Calque actuel */}
      <div
        className="absolute inset-0 -z-20 bg-cover bg-center"
        style={{
          backgroundImage: `url("${encodeURI(images.current[current])}")`,
          transition: "opacity 0.5s ease-in-out",
          opacity: transitioning ? 0 : 1,
        }}
      />
      {/* Calque suivant (crossfade) */}
      {next !== null && (
        <div
          className="absolute inset-0 -z-20 bg-cover bg-center"
          style={{
            backgroundImage: `url("${encodeURI(images.current[next])}")`,
            transition: "opacity 0.5s ease-in-out",
            opacity: transitioning ? 1 : 0,
          }}
        />
      )}
    </>
  );
}

function AuthShell() {
  const t = useT();
  const { lang, setLang } = useI18n();
  const { theme, toggle } = useTheme();

  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden p-4">
      {/* Diaporama d'images en arrière-plan */}
      <LoginSlideshow />
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
            {t("auth.subtitle")}
          </p>
        </div>

        <div className="rounded-2xl border border-border bg-card/95 p-6 shadow-2xl backdrop-blur sm:p-8">
          <AuthFlow />
        </div>

        <p className="mt-6 text-center text-[11px] text-white/80 drop-shadow">
          © {new Date().getFullYear()} {t("auth.footer")}
        </p>
      </div>
    </div>
  );
}

// ─── Orchestrateur du flux d'authentification ────────────────────────────

type AuthStep = "login" | "otp";

function AuthFlow() {
  const [step, setStep] = useState<AuthStep>("login");
  const [pendingEmail, setPendingEmail] = useState("");

  if (step === "otp") {
    return (
      <OtpView
        email={pendingEmail}
        onBack={() => setStep("login")}
      />
    );
  }

  return (
    <SignInView
      onOtpRequired={(email) => {
        setPendingEmail(email);
        setStep("otp");
      }}
    />
  );
}

// ─── Formulaire de connexion ─────────────────────────────────────────────

function SignInView({ onOtpRequired }: { onOtpRequired: (email: string) => void }) {
  const t = useT();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [show, setShow] = useState(false);
  const [loading, setLoading] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim() || !password.trim()) return;
    setLoading(true);
    try {
      const result = await loginApi({ email, password });

      if (result.requiresOtp) {
        // 2FA activé — basculer vers la saisie OTP
        onOtpRequired(result.email);
        return;
      }

      // Purge le cache React Query de la session précédente — sans ça, un
      // utilisateur qui se connecte juste après un autre (même onglet, sans
      // rechargement complet) voit un instant les données mises en cache par
      // le compte précédent (ex : liste des biens d'un admin) tant que leur
      // staleTime n'est pas écoulé, avant qu'un refetch ne les corrige.
      queryClient.clear();

      // Connexion directe — récupérer le profil en arrière-plan
      api
        .get<ApiResponse<AuthUser>>("/profile")
        .then((res) => {
          // Ne jamais écrire une valeur invalide : si la réponse n'a pas la
          // forme attendue (ex. réponse HTML au lieu de JSON, cas observé
          // juste après un déploiement — proxy/cache pas encore à jour),
          // res.data.data serait `undefined` et JSON.stringify(undefined)
          // écrirait la chaîne littérale "undefined" dans le localStorage,
          // qui fait planter tout lecteur ultérieur (JSON.parse dessus).
          if (res.data?.data) {
            localStorage.setItem(USER_KEY, JSON.stringify(res.data.data));
            emitAuthChanged();
          }
        })
        .catch(() => { /* silencieux */ });

      toast.success(t("auth.loginSuccess"));
      navigate("/statistiques", { replace: true });
    } catch (err: unknown) {
      const axiosErr = err as {
        response?: { status?: number; data?: unknown };
        code?: string;
        message?: string;
      };
      // Diagnostic : la cause exacte (statut HTTP, code réseau) reste dans la
      // console pour pouvoir distinguer un vrai refus serveur d'un problème
      // de réseau / proxy côté hébergement.
      console.error("[login] échec", {
        status: axiosErr.response?.status,
        code: axiosErr.code,
        message: axiosErr.message,
        data: axiosErr.response?.data,
      });
      if (axiosErr.response?.status === 401) {
        toast.error(t("auth.invalidCredentials"));
      } else if (axiosErr.message === "INVALID_API_RESPONSE") {
        toast.error(
          "Réponse inattendue du serveur d'authentification (aperçu). Réessayez ou utilisez l'application publiée.",
        );
      } else {
        const detail = axiosErr.response?.status
          ? `HTTP ${axiosErr.response.status}`
          : (axiosErr.code ?? axiosErr.message ?? "réseau");
        toast.error(`${t("auth.serverUnreachable")} (${detail})`);
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
              aria-label={t("auth.togglePassword")}
            >
              {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          </div>
        </div>
      </div>

      <Button type="submit" disabled={loading} className="mt-6 h-12 w-full gap-2 text-base font-semibold">
        {loading ? <Loader2 className="h-5 w-5 animate-spin" /> : <LogIn className="h-5 w-5" />}
        {loading ? t("auth.signingIn") : t("auth.signin")}
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

// ─── Formulaire de saisie OTP ─────────────────────────────────────────────

function OtpView({ email, onBack }: { email: string; onBack: () => void }) {
  const t = useT();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [otp, setOtp] = useState("");
  const [loading, setLoading] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (otp.trim().length < 4) return;
    setLoading(true);
    try {
      await verifyOtpApi({ email, otp: otp.trim() });

      // Purge le cache de la session précédente — voir le commentaire
      // équivalent dans LoginView.submit.
      queryClient.clear();

      // Profil en arrière-plan
      api
        .get<ApiResponse<AuthUser>>("/profile")
        .then((res) => {
          // Ne jamais écrire une valeur invalide : si la réponse n'a pas la
          // forme attendue (ex. réponse HTML au lieu de JSON, cas observé
          // juste après un déploiement — proxy/cache pas encore à jour),
          // res.data.data serait `undefined` et JSON.stringify(undefined)
          // écrirait la chaîne littérale "undefined" dans le localStorage,
          // qui fait planter tout lecteur ultérieur (JSON.parse dessus).
          if (res.data?.data) {
            localStorage.setItem(USER_KEY, JSON.stringify(res.data.data));
            emitAuthChanged();
          }
        })
        .catch(() => { /* silencieux */ });

      toast.success(t("auth.loginSuccess"));
      navigate("/statistiques", { replace: true });
    } catch (err: unknown) {
      const axiosErr = err as { response?: { status?: number } };
      if (axiosErr.response?.status === 400 || axiosErr.response?.status === 401) {
        toast.error(t("auth.otp.invalid"));
      } else {
        toast.error(t("auth.serverUnreachable"));
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={submit}>
      {/* Titre */}
      <div className="flex flex-col items-center text-center">
        <div className="grid h-12 w-12 place-items-center rounded-full bg-primary/10 text-primary">
          <KeyRound className="h-5 w-5" />
        </div>
        <h2 className="mt-3 text-2xl font-extrabold text-foreground">{t("auth.otp.title")}</h2>
        <p className="mt-1 max-w-[300px] text-sm text-muted-foreground">
          {t("auth.otp.subtitle")}
        </p>
        <p className="mt-1 text-xs font-medium text-primary">{email}</p>
      </div>

      <div className="mt-6 space-y-1.5">
        <Label htmlFor="otp" className="text-sm font-semibold">
          {t("auth.otp.label")} <span className="text-destructive">*</span>
        </Label>
        <Input
          id="otp"
          type="text"
          inputMode="numeric"
          pattern="[0-9]*"
          maxLength={8}
          autoComplete="one-time-code"
          value={otp}
          onChange={(e) => setOtp(e.target.value.replace(/\D/g, ""))}
          placeholder={t("auth.otp.placeholder")}
          className="h-11 text-center text-lg font-mono tracking-widest"
          autoFocus
        />
      </div>

      <Button
        type="submit"
        disabled={loading || otp.trim().length < 4}
        className="mt-6 h-12 w-full gap-2 text-base font-semibold"
      >
        {loading ? <Loader2 className="h-5 w-5 animate-spin" /> : <ShieldCheck className="h-5 w-5" />}
        {loading ? t("auth.verifying") : t("auth.otp.submit")}
      </Button>

      <Button
        type="button"
        variant="ghost"
        className="mt-3 h-10 w-full gap-2 text-sm text-muted-foreground"
        onClick={onBack}
        disabled={loading}
      >
        <ArrowLeft className="h-4 w-4" />
        {t("auth.otp.back")}
      </Button>
    </form>
  );
}

export default AuthShell;
