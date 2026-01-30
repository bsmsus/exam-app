import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { Provider } from "react-redux";
import { configureStore } from "@reduxjs/toolkit";
import authReducer from "../store/slices/authSlice";
import adminReducer from "../store/slices/adminSlice";
import studentReducer from "../store/slices/studentSlice";
import Login from "./Login";

function createStore(authOverrides?: { isLoading?: boolean; error?: string | null }) {
  return configureStore({
    reducer: { auth: authReducer, admin: adminReducer, student: studentReducer },
    preloadedState: {
      auth: {
        user: null,
        accessToken: null,
        isLoading: false,
        error: null,
        ...authOverrides,
      },
      admin: {
        examsList: [],
        examId: "",
        title: "",
        maxAttempts: 3,
        cooldownMinutes: 60,
        attempts: [],
        isLoading: false,
        error: null,
      },
      student: {
        examId: "",
        examDetails: null,
        currentAttempt: null,
        attemptHistory: [],
        message: "",
        isError: false,
        isLoading: false,
      },
    },
  });
}

describe("Login", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  const renderLogin = (store = createStore()) =>
    render(
      <Provider store={store}>
        <Login />
      </Provider>,
    );

  it("renders sign in form by default", () => {
    renderLogin();
    expect(screen.getByRole("heading", { name: /sign in/i })).toBeInTheDocument();
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /sign in/i })).toBeInTheDocument();
  });

  it("shows Student and Admin toggle buttons", () => {
    renderLogin();
    expect(screen.getByRole("button", { name: /^student$/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /^admin$/i })).toBeInTheDocument();
  });

  it("switches to register mode and shows name field", async () => {
    const user = userEvent.setup();
    renderLogin();
    await user.click(screen.getByRole("button", { name: /register/i }));
    expect(screen.getByRole("heading", { name: /create account/i })).toBeInTheDocument();
    expect(screen.getByLabelText(/name/i)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /create account/i })).toBeInTheDocument();
  });

  it("switches user type to admin", async () => {
    const user = userEvent.setup();
    renderLogin();
    await user.click(screen.getByRole("button", { name: /^admin$/i }));
    expect(screen.getByRole("button", { name: /^admin$/i })).toHaveClass("active");
  });

  it("dispatches login on submit when in login mode", async () => {
    const user = userEvent.setup();
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
      ok: true,
      json: () =>
        Promise.resolve({
          user: { id: "1", name: "Test", email: "test@test.com", type: "student" },
          accessToken: "token",
          refreshToken: "refresh",
        }),
    });
    renderLogin(createStore({ error: null }));
    await user.type(screen.getByLabelText(/email/i), "test@example.com");
    await user.type(screen.getByLabelText(/password/i), "password123");
    await user.click(screen.getByRole("button", { name: /sign in/i }));
    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining("/auth/student/login"),
        expect.objectContaining({
          method: "POST",
          body: JSON.stringify({ email: "test@example.com", password: "password123" }),
        }),
      );
    });
  });

  it("dispatches register on submit when in register mode", async () => {
    const user = userEvent.setup();
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
      ok: true,
      json: () =>
        Promise.resolve({
          user: { id: "1", name: "New User", email: "new@example.com", type: "student" },
          accessToken: "token",
          refreshToken: "refresh",
        }),
    });
    renderLogin();
    await user.click(screen.getByRole("button", { name: /register/i }));
    await user.type(screen.getByLabelText(/name/i), "New User");
    await user.type(screen.getByLabelText(/email/i), "new@example.com");
    await user.type(screen.getByLabelText(/password/i), "password123");
    await user.click(screen.getByRole("button", { name: /create account/i }));
    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining("/auth/student/register"),
        expect.objectContaining({
          method: "POST",
          body: JSON.stringify({
            name: "New User",
            email: "new@example.com",
            password: "password123",
          }),
        }),
      );
    });
  });

  it("shows error message when auth fails", async () => {
    const user = userEvent.setup();
    (globalThis.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
      ok: false,
      json: () => Promise.resolve({ error: "Invalid credentials" }),
    });
    renderLogin(createStore());
    await user.type(screen.getByLabelText(/email/i), "bad@example.com");
    await user.type(screen.getByLabelText(/password/i), "wrong");
    await user.click(screen.getByRole("button", { name: /sign in/i }));
    await waitFor(() => {
      expect(screen.getByText("Invalid credentials")).toBeInTheDocument();
    });
  });
});
