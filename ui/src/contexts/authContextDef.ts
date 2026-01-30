import { createContext } from "react";

export interface User {
  id: string;
  name: string;
  email: string;
  type: "admin" | "student";
}

export interface AuthContextType {
  user: User | null;
  accessToken: string | null;
  isLoading: boolean;
  login: (email: string, password: string, userType: "admin" | "student") => Promise<void>;
  register: (name: string, email: string, password: string, userType: "admin" | "student") => Promise<void>;
  logout: () => void;
  refreshAccessToken: () => Promise<string | null>;
}

export const AuthContext = createContext<AuthContextType | null>(null);
