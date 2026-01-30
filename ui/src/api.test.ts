import { describe, it, expect, vi, beforeEach } from "vitest";
import { setAccessToken, setRefreshTokenFn, api } from "./api";

describe("api", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
    setAccessToken(null);
    setRefreshTokenFn(() => Promise.resolve(null));
  });

  it("setAccessToken updates stored token", async () => {
    setAccessToken("token-123");
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(
      new Response(JSON.stringify({ ok: true }), { status: 200 }),
    );
    await api("/test");
    expect(fetch).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({
        headers: expect.objectContaining({ Authorization: "Bearer token-123" }),
      }),
    );
  });

  it("api sends request without Authorization when no token", async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(
      new Response(JSON.stringify({ data: 1 }), { status: 200 }),
    );
    const result = await api<{ data: number }>("/test");
    expect(result).toEqual({ data: 1 });
    expect(fetch).toHaveBeenCalledWith(
      expect.stringMatching(/\/test$/),
      expect.objectContaining({
        headers: expect.not.objectContaining({ Authorization: expect.any(String) }),
      }),
    );
  });

  it("api includes Content-Type application/json", async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(new Response(JSON.stringify({}), { status: 200 }));
    await api("/test");
    expect(fetch).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({
        headers: expect.objectContaining({ "Content-Type": "application/json" }),
      }),
    );
  });

  it("api returns parsed JSON on success", async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(
      new Response(JSON.stringify({ id: "1", name: "Test" }), { status: 200 }),
    );
    const result = await api<{ id: string; name: string }>("/resource");
    expect(result).toEqual({ id: "1", name: "Test" });
  });

  it("api throws on non-ok response with error message", async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(
      new Response(JSON.stringify({ error: "Not found" }), { status: 404 }),
    );
    await expect(api("/missing")).rejects.toThrow("Not found");
  });

  it("api retries with new token on 401 when refreshTokenFn returns token", async () => {
    setAccessToken("old-token");
    const refreshFn = vi.fn().mockResolvedValue("new-token");
    setRefreshTokenFn(refreshFn);
    (globalThis.fetch as ReturnType<typeof vi.fn>)
      .mockResolvedValueOnce(new Response(JSON.stringify({}), { status: 401 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ success: true }), { status: 200 }));
    const result = await api<{ success: boolean }>("/protected");
    expect(refreshFn).toHaveBeenCalled();
    expect(fetch).toHaveBeenCalledTimes(2);
    expect(result).toEqual({ success: true });
  });

  it("api returns undefined for empty response body", async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(new Response("", { status: 200 }));
    const result = await api("/empty");
    expect(result).toBeUndefined();
  });
});
