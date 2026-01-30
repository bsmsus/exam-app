const API_BASE = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";

let accessToken: string | null = null;
let refreshTokenFn: (() => Promise<string | null>) | null = null;

export function setAccessToken(token: string | null) {
  accessToken = token;
}

export function setRefreshTokenFn(fn: () => Promise<string | null>) {
  refreshTokenFn = fn;
}

export async function api<T>(path: string, options?: RequestInit): Promise<T> {
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    ...(options?.headers as Record<string, string>),
  };

  if (accessToken) {
    headers["Authorization"] = `Bearer ${accessToken}`;
  }

  let res = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
  });

  // Handle 401 - try to refresh token
  if (res.status === 401 && refreshTokenFn) {
    const newToken = await refreshTokenFn();
    if (newToken) {
      accessToken = newToken;
      headers["Authorization"] = `Bearer ${newToken}`;
      res = await fetch(`${API_BASE}${path}`, {
        ...options,
        headers,
      });
    }
  }

  // error response
  if (!res.ok) {
    const text = await res.text();
    try {
      const json = JSON.parse(text);
      // Handle Symfony validation format (violations array)
      if (json.violations?.length) {
        const msg = json.violations
          .map((v: { message?: string }) => v.message)
          .filter(Boolean)
          .join(" ");
        throw new Error(msg || json.title || "Validation error");
      }
      throw new Error(json.error || json.message || "API error");
    } catch (e) {
      if (e instanceof Error) throw e;
      throw new Error(text || "API error");
    }
  }

  // handle empty body (204 / empty 200)
  const text = await res.text();
  if (!text) {
    return undefined as T;
  }

  return JSON.parse(text) as T;
}
