import { useCallback, useRef, useState } from "react";

export function useViewStack<V extends string>(initial: V) {
  const [view, setView] = useState<V>(initial);
  const stack = useRef<V[]>([initial]);

  const go = useCallback((next: V) => {
    stack.current.push(next);
    setView(next);
  }, []);

  const back = useCallback(() => {
    if (stack.current.length > 1) stack.current.pop();
    const prev = stack.current[stack.current.length - 1] ?? initial;
    setView(prev);
  }, [initial]);

  const reset = useCallback(
    (to?: V) => {
      const target = to ?? initial;
      stack.current = [target];
      setView(target);
    },
    [initial]
  );

  return { view, go, back, reset, canBack: stack.current.length > 1 };
}