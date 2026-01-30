import { render, screen } from "@testing-library/react";
import { describe, it, expect } from "vitest";
import { Provider } from "react-redux";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { configureStore } from "@reduxjs/toolkit";
import authReducer from "../store/slices/authSlice";
import adminReducer from "../store/slices/adminSlice";
import studentReducer from "../store/slices/studentSlice";
import ProtectedRoute from "./ProtectedRoute";

function createStore(preloadedAuth: {
  user: { id: string; name: string; email: string; type: "admin" | "student" } | null;
  isLoading: boolean;
}) {
  return configureStore({
    reducer: { auth: authReducer, admin: adminReducer, student: studentReducer },
    preloadedState: {
      auth: {
        user: preloadedAuth.user,
        accessToken: preloadedAuth.user ? "token" : null,
        isLoading: preloadedAuth.isLoading,
        error: null,
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

describe("ProtectedRoute", () => {
  const renderWithRouter = (
    initialAuth: {
      user: { id: string; name: string; email: string; type: "admin" | "student" } | null;
      isLoading: boolean;
    },
    path: string,
  ) => {
    const testStore = createStore(initialAuth);
    return render(
      <Provider store={testStore}>
        <MemoryRouter initialEntries={[path]}>
          <Routes>
            <Route
              path="/admin"
              element={
                <ProtectedRoute allowedRole="admin">
                  <div data-testid="admin-content">Admin Content</div>
                </ProtectedRoute>
              }
            />
            <Route
              path="/student"
              element={
                <ProtectedRoute allowedRole="student">
                  <div data-testid="student-content">Student Content</div>
                </ProtectedRoute>
              }
            />
            <Route path="/login" element={<div data-testid="login-page">Login</div>} />
          </Routes>
        </MemoryRouter>
      </Provider>,
    );
  };

  it("shows loading when isLoading is true", () => {
    renderWithRouter({ user: null, isLoading: true }, "/admin");
    expect(screen.getByText("Loading...")).toBeInTheDocument();
  });

  it("redirects to login when user is null", () => {
    renderWithRouter({ user: null, isLoading: false }, "/admin");
    expect(screen.getByTestId("login-page")).toBeInTheDocument();
  });

  it("renders children when user has allowedRole", () => {
    renderWithRouter({ user: { id: "1", name: "Admin", email: "a@a.com", type: "admin" }, isLoading: false }, "/admin");
    expect(screen.getByTestId("admin-content")).toBeInTheDocument();
    expect(screen.getByText("Admin Content")).toBeInTheDocument();
  });

  it("redirects to user role path when user has wrong role", () => {
    renderWithRouter(
      { user: { id: "1", name: "Student", email: "s@s.com", type: "student" }, isLoading: false },
      "/admin",
    );
    expect(screen.getByTestId("student-content")).toBeInTheDocument();
  });

  it("renders student content when student accesses student route", () => {
    renderWithRouter(
      { user: { id: "1", name: "Student", email: "s@s.com", type: "student" }, isLoading: false },
      "/student",
    );
    expect(screen.getByTestId("student-content")).toBeInTheDocument();
    expect(screen.getByText("Student Content")).toBeInTheDocument();
  });
});
