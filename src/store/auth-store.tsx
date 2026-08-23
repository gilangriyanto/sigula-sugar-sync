import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from "react";
import { clearToken, getToken, onUnauthorized, setToken } from "@/lib/api-client";
import * as authApi from "@/lib/api/auth";
import type { AuthUser, LoginPayload } from "@/lib/api/auth";

type Status = "checking" | "authenticated" | "unauthenticated";

interface Ctx {
  user: AuthUser | null;
  status: Status;
  login: (payload: LoginPayload) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<Ctx | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [status, setStatus] = useState<Status>("checking");

  const reset = useCallback(() => {
    setUser(null);
    setStatus("unauthenticated");
  }, []);

  // Token yang ditolak backend (401) dari request mana pun harus langsung mencabut sesi lokal.
  useEffect(() => {
    onUnauthorized(reset);
  }, [reset]);

  // Hydrate sesi dari token tersimpan (mis. setelah refresh halaman).
  useEffect(() => {
    const token = getToken();
    if (!token) {
      setStatus("unauthenticated");
      return;
    }
    authApi
      .me()
      .then((u) => {
        setUser(u);
        setStatus("authenticated");
      })
      .catch(() => {
        clearToken();
        setStatus("unauthenticated");
      });
  }, []);

  const login = useCallback(async (payload: LoginPayload) => {
    const { token, user: loggedInUser } = await authApi.login(payload);
    setToken(token);
    setUser(loggedInUser);
    setStatus("authenticated");
  }, []);

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } catch {
      // Token mungkin sudah kedaluwarsa di server — tetap bersihkan sesi lokal.
    } finally {
      clearToken();
      setUser(null);
      setStatus("unauthenticated");
    }
  }, []);

  return (
    <AuthContext.Provider value={{ user, status, login, logout }}>{children}</AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth harus dipakai di dalam AuthProvider");
  return ctx;
}
