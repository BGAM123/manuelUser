import { Navigate } from "react-router-dom";
import { TOKEN_KEY, REFRESH_TOKEN_KEY } from "@/api/axios";

export default function IndexPage() {
  const token = localStorage.getItem(TOKEN_KEY);
  const refreshToken = localStorage.getItem(REFRESH_TOKEN_KEY);

  if (token || refreshToken) {
    return <Navigate to="/statistiques" replace />;
  }

  return <Navigate to="/authentification" replace />;
}
