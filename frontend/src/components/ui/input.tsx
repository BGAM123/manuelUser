import * as React from "react";

import { cn } from "@/utils/utils";

const Input = React.forwardRef<HTMLInputElement, React.ComponentProps<"input">>(
  ({ className, type, onKeyDown, onPaste, min, ...props }, ref) => {
    // Aucun champ numérique de l'application n'accepte de valeur négative
    // (quantités, montants, ordres, codes, coordonnées géographiques du
    // Cameroun toujours positives...) — on bloque la saisie du signe "-"
    // au niveau du composant partagé plutôt que dans chaque formulaire.
    const isNumber = type === "number";
    return (
      <input
        type={type}
        min={isNumber && min === undefined ? 0 : min}
        onKeyDown={
          isNumber
            ? (e) => {
                if (e.key === "-" || e.key === "Minus" || e.key === "Subtract") e.preventDefault();
                onKeyDown?.(e);
              }
            : onKeyDown
        }
        onPaste={
          isNumber
            ? (e) => {
                const text = e.clipboardData.getData("text");
                if (text.includes("-")) e.preventDefault();
                onPaste?.(e);
              }
            : onPaste
        }
        className={cn(
          "flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
          className,
        )}
        ref={ref}
        {...props}
      />
    );
  },
);
Input.displayName = "Input";

export { Input };
