import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { AuthProvider } from "./AuthContext";
import { useAuth } from "./useAuth";

function TestConsumer() {
  const auth = useAuth();
  return (
    <div>
      <span data-testid="user">{auth.user ? auth.user.name : "none"}</span>
      <span data-testid="loading">{String(auth.isLoading)}</span>
      <button type="button" onClick={() => auth.logout()}>
        Logout
      </button>
    </div>
  );
}

function LoginTrigger() {
  const { login } = useAuth();
  return (
    <button type="button" onClick={() => login("test@test.com", "password", "student")}>
      Do Login
    </button>
  );
}

describe("AuthContext", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
    localStorage.clear();
  });

  it("useAuth throws when used outside AuthProvider", () => {
    expect(() => render(<TestConsumer />)).toThrow("useAuth must be used within an AuthProvider");
  });

  it("AuthProvider provides initial loading then no user when refresh fails", async () => {
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockImplementation(() =>
      Promise.resolve(new Response(JSON.stringify({}), { status: 401 })),
    );
    render(
      <AuthProvider>
        <TestConsumer />
      </AuthProvider>,
    );
    expect(screen.getByTestId("loading")).toHaveTextContent("true");
    await waitFor(() => {
      expect(screen.getByTestId("loading")).toHaveTextContent("false");
    });
    expect(screen.getByTestId("user")).toHaveTextContent("none");
  });

  it("login calls fetch with login URL and updates state when successful", async () => {
    const user = userEvent.setup();
    const loginPayload = {
      user: { id: "1", name: "Test User", email: "test@test.com", type: "student" },
      accessToken: "access",
      refreshToken: "refresh",
    };
    const fetchMock = vi.fn((url: string | URL) => {
      const urlStr = typeof url === "string" ? url : url.toString();
      if (urlStr.includes("login")) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(loginPayload),
        } as Response);
      }
      return Promise.resolve({ ok: false, json: () => Promise.resolve({}) } as Response);
    });
    vi.stubGlobal("fetch", fetchMock);
    render(
      <AuthProvider>
        <LoginTrigger />
        <TestConsumer />
      </AuthProvider>,
    );
    await waitFor(() => {
      expect(screen.getByTestId("loading")).toHaveTextContent("false");
    });
    await user.click(screen.getByRole("button", { name: /do login/i }));
    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledWith(
        expect.stringMatching(/auth\/student\/login/),
        expect.objectContaining({
          method: "POST",
          body: JSON.stringify({ email: "test@test.com", password: "password" }),
        }),
      );
    });
  });

  it("logout clears user and tokens", async () => {
    const user = userEvent.setup();
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockImplementation((url: string) => {
      if (typeof url === "string" && url.includes("refresh")) {
        return Promise.resolve(
          new Response(
            JSON.stringify({
              user: { id: "1", name: "User", email: "u@u.com", type: "admin" },
              accessToken: "a",
              refreshToken: "r",
            }),
            { status: 200 },
          ),
        );
      }
      return Promise.resolve(new Response(JSON.stringify({}), { status: 200 }));
    });
    localStorage.setItem("refreshToken", "r");
    localStorage.setItem("userType", "admin");
    render(
      <AuthProvider>
        <TestConsumer />
      </AuthProvider>,
    );
    await waitFor(
      () => {
        expect(screen.getByTestId("user")).toHaveTextContent("User");
      },
      { timeout: 2000 },
    );
    await user.click(screen.getByRole("button", { name: /logout/i }));
    await waitFor(() => {
      expect(screen.getByTestId("user")).toHaveTextContent("none");
    });
    expect(localStorage.getItem("refreshToken")).toBeNull();
    expect(localStorage.getItem("userType")).toBeNull();
  });
});
