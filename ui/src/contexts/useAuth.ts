import { useContext } from "react";
import { AuthContext } from "./authContextDef";
import type { AuthContextType } from "./authContextDef";

export function useAuth(): AuthContextType {
  const ctx = useContext(AuthContext);
  if (ctx == null) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return ctx;
}
