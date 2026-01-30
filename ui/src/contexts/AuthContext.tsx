import { useState, useCallback, useEffect } from "react";
import type { ReactNode } from "react";
import { AuthContext } from "./authContextDef";
import type { User } from "./authContextDef";

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";

export type { User, AuthContextType } from "./authContextDef";

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [accessToken, setAccessToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const logout = useCallback(() => {
    const refreshToken = localStorage.getItem("refreshToken");
    const userType = user?.type;

    if (refreshToken && userType) {
      fetch(`${API_BASE}/auth/${userType}/logout`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ refreshToken }),
      }).catch(() => {});
    }

    setUser(null);
    setAccessToken(null);
    localStorage.removeItem("refreshToken");
    localStorage.removeItem("userType");
  }, [user?.type]);

  const refreshAccessToken = useCallback(async (): Promise<string | null> => {
    const refreshToken = localStorage.getItem("refreshToken");
    const userType = localStorage.getItem("userType");

    if (!refreshToken || !userType) {
      return null;
    }

    try {
      const res = await fetch(`${API_BASE}/auth/${userType}/refresh`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ refreshToken }),
      });

      if (!res.ok) {
        logout();
        return null;
      }

      const data = await res.json();
      setUser(data.user);
      setAccessToken(data.accessToken);
      localStorage.setItem("refreshToken", data.refreshToken);
      return data.accessToken;
    } catch {
      logout();
      return null;
    }
  }, [logout]);

  useEffect(() => {
    const initAuth = async () => {
      await refreshAccessToken();
      setIsLoading(false);
    };

    initAuth();
  }, [refreshAccessToken]);

  const login = async (email: string, password: string, userType: "admin" | "student") => {
    const res = await fetch(`${API_BASE}/auth/${userType}/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
    });

    if (!res.ok) {
      const data = await res.json();
      throw new Error(data.error || "Login failed");
    }

    const data = await res.json();
    setUser(data.user);
    setAccessToken(data.accessToken);
    localStorage.setItem("refreshToken", data.refreshToken);
    localStorage.setItem("userType", userType);
  };

  const register = async (name: string, email: string, password: string, userType: "admin" | "student") => {
    const res = await fetch(`${API_BASE}/auth/${userType}/register`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, email, password }),
    });

    if (!res.ok) {
      const data = await res.json();
      throw new Error(data.error || "Registration failed");
    }

    const data = await res.json();
    setUser(data.user);
    setAccessToken(data.accessToken);
    localStorage.setItem("refreshToken", data.refreshToken);
    localStorage.setItem("userType", userType);
  };

  return (
    <AuthContext.Provider value={{ user, accessToken, isLoading, login, register, logout, refreshAccessToken }}>
      {children}
    </AuthContext.Provider>
  );
}
