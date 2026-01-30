import { describe, it, expect, vi, beforeEach } from "vitest";
import authReducer, { login, refreshAccessToken, logout, clearError } from "./authSlice";

describe("authSlice", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
    localStorage.clear();
  });

  const initialState = {
    user: null,
    accessToken: null,
    isLoading: true,
    error: null,
  };

  it("has correct initial state", () => {
    expect(authReducer(undefined, { type: "unknown" })).toEqual(initialState);
  });

  it("clearError sets error to null", () => {
    const state = authReducer({ ...initialState, isLoading: false, error: "Something wrong" }, clearError());
    expect(state.error).toBeNull();
  });

  it("login.pending sets isLoading true and clears error", () => {
    const state = authReducer(
      { ...initialState, isLoading: false, error: "Old error" },
      login.pending("", { email: "a@a.com", password: "p", userType: "student" }),
    );
    expect(state.isLoading).toBe(true);
    expect(state.error).toBeNull();
  });

  it("login.fulfilled sets user and accessToken", () => {
    const payload = {
      user: { id: "1", name: "U", email: "u@u.com", type: "student" as const },
      accessToken: "token",
    };
    const state = authReducer(
      { ...initialState, isLoading: true },
      login.fulfilled(payload, "", { email: "u@u.com", password: "p", userType: "student" }),
    );
    expect(state.isLoading).toBe(false);
    expect(state.user).toEqual(payload.user);
    expect(state.accessToken).toBe("token");
    expect(state.error).toBeNull();
  });

  it("login.rejected sets error", () => {
    const action = login.rejected(null, "req", { email: "a@a.com", password: "p", userType: "student" });
    const state = authReducer({ ...initialState, isLoading: true }, { ...action, payload: "Invalid credentials" });
    expect(state.isLoading).toBe(false);
    expect(state.error).toBe("Invalid credentials");
  });

  it("refreshAccessToken.fulfilled updates user and accessToken", () => {
    const payload = {
      user: { id: "2", name: "Admin", email: "a@a.com", type: "admin" as const },
      accessToken: "new-token",
    };
    const state = authReducer({ ...initialState, isLoading: true }, refreshAccessToken.fulfilled(payload, ""));
    expect(state.isLoading).toBe(false);
    expect(state.user).toEqual(payload.user);
    expect(state.accessToken).toBe("new-token");
  });

  it("refreshAccessToken.rejected clears user and accessToken", () => {
    const state = authReducer(
      {
        user: { id: "1", name: "U", email: "u@u.com", type: "student" },
        accessToken: "old",
        isLoading: true,
        error: null,
      },
      refreshAccessToken.rejected(null, ""),
    );
    expect(state.isLoading).toBe(false);
    expect(state.user).toBeNull();
    expect(state.accessToken).toBeNull();
  });

  it("logout.fulfilled clears user and accessToken", () => {
    const state = authReducer(
      {
        user: { id: "1", name: "U", email: "u@u.com", type: "student" },
        accessToken: "t",
        isLoading: false,
        error: "err",
      },
      logout.fulfilled(undefined, "", "student"),
    );
    expect(state.user).toBeNull();
    expect(state.accessToken).toBeNull();
    expect(state.error).toBeNull();
  });
});
