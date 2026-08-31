/**
 * Sélecteur d'année pour les filtres "Exercice" — préreempli avec l'année en
 * cours par défaut, avec boutons ← / → (année n-1 / n+1) ET saisie libre
 * autorisée au clavier (demande explicite 2026-08-29 : la valeur par défaut
 * évite d'avoir à retaper les 4 chiffres, mais ne doit pas bloquer une saisie
 * directe d'une autre année).
 *
 * La saisie est tenue dans un état local le temps que l'utilisateur tape —
 * onChange n'est déclenché qu'une fois les 4 chiffres d'une année valide
 * saisis (comme l'ancien comportement par Input libre), pour éviter des
 * requêtes sur des années partielles ("2", "20"...).
 */
import { useEffect, useState } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { cn } from "@/utils/utils";

interface YearStepperProps {
  value: number;
  onChange: (year: number) => void;
  minYear?: number;
  maxYear?: number;
  className?: string;
}

export function YearStepper({
  value,
  onChange,
  minYear = 2000,
  maxYear = new Date().getFullYear() + 5,
  className,
}: YearStepperProps) {
  const [text, setText] = useState(String(value));

  // Resynchronise l'affichage quand la valeur change depuis l'extérieur
  // (boutons ← / →, réinitialisation des filtres...).
  useEffect(() => {
    setText(String(value));
  }, [value]);

  const step = (delta: number) => {
    const next = Math.min(maxYear, Math.max(minYear, value + delta));
    setText(String(next));
    onChange(next);
  };

  const handleTextChange = (raw: string) => {
    const digits = raw.replace(/\D/g, "").slice(0, 4);
    setText(digits);
    if (digits.length === 4) {
      const year = Number(digits);
      if (year >= minYear && year <= maxYear) onChange(year);
    }
  };

  // Si l'utilisateur quitte le champ avec une saisie incomplète/invalide,
  // on retombe sur la dernière valeur appliquée plutôt que de laisser un
  // champ vide ou incohérent.
  const handleBlur = () => {
    if (text.length !== 4 || Number(text) < minYear || Number(text) > maxYear) {
      setText(String(value));
    }
  };

  return (
    <div
      className={cn(
        "flex h-9 items-center justify-between gap-1 rounded-md border border-input bg-background px-1 text-sm shadow-sm",
        className,
      )}
    >
      <button
        type="button"
        onClick={() => step(-1)}
        disabled={value <= minYear}
        aria-label="Année précédente"
        className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded text-muted-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-30"
      >
        <ChevronLeft className="h-4 w-4" />
      </button>
      <input
        type="text"
        inputMode="numeric"
        value={text}
        onChange={(e) => handleTextChange(e.target.value)}
        onBlur={handleBlur}
        aria-label="Année"
        className="w-12 flex-1 bg-transparent text-center font-medium tabular-nums outline-none"
      />
      <button
        type="button"
        onClick={() => step(1)}
        disabled={value >= maxYear}
        aria-label="Année suivante"
        className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded text-muted-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-30"
      >
        <ChevronRight className="h-4 w-4" />
      </button>
    </div>
  );
}
