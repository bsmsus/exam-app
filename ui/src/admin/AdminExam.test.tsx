import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { Provider } from "react-redux";
import { configureStore } from "@reduxjs/toolkit";
import authReducer from "../store/slices/authSlice";
import adminReducer from "../store/slices/adminSlice";
import studentReducer from "../store/slices/studentSlice";
import AdminExam from "./AdminExam";

import { api } from "../api";

vi.mock("../api", () => ({
  api: vi.fn(),
}));

describe("AdminExam", () => {
  const createStore = (adminState?: Partial<ReturnType<typeof adminReducer>>) =>
    configureStore({
      reducer: { auth: authReducer, admin: adminReducer, student: studentReducer },
      preloadedState: {
        auth: { user: null, accessToken: null, isLoading: false, error: null },
        admin: {
          examsList: [],
          examId: "",
          title: "",
          maxAttempts: 3,
          cooldownMinutes: 60,
          attempts: [],
          isLoading: false,
          error: null,
          ...adminState,
        },
        student: { examId: "", examDetails: null, currentAttempt: null, attemptHistory: [], message: "", isError: false, isLoading: false },
      },
    });

  beforeEach(() => {
    vi.mocked(api).mockReset();
    // Default mock that handles different endpoints
    vi.mocked(api).mockImplementation((url: string) => {
      if (url === "/admin/exams" || url.match(/^\/admin\/exams$/)) {
        return Promise.resolve([]);
      }
      return Promise.resolve({});
    });
  });

  const renderAdminExam = (store = createStore()) =>
    render(
      <Provider store={store}>
        <AdminExam />
      </Provider>
    );

  it("renders Admin Panel heading and form fields", () => {
    renderAdminExam();
    expect(screen.getByRole("heading", { name: /admin panel/i })).toBeInTheDocument();
    expect(screen.getByLabelText(/exam title/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/max attempts/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/cooldown/i)).toBeInTheDocument();
  });

  it("renders Create Exam, Update Exam, and Load Attempts buttons", () => {
    renderAdminExam();
    expect(screen.getByRole("button", { name: /create exam/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /update exam/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /load attempts/i })).toBeInTheDocument();
  });

  it("dispatches setTitle when typing in exam title", async () => {
    const user = userEvent.setup();
    const store = createStore();
    renderAdminExam(store);
    await user.type(screen.getByLabelText(/exam title/i), "Math Test");
    expect(store.getState().admin.title).toBe("Math Test");
  });

  it("dispatches createExam when Create Exam is clicked", async () => {
    const user = userEvent.setup();
    vi.mocked(api).mockImplementation((_url: string, options?: { method?: string }) => {
      if (options?.method === "POST") {
        return Promise.resolve({ examId: "exam-123" });
      }
      return Promise.resolve([]); // For loadExamsList
    });
    const store = createStore({ title: "Test", maxAttempts: 2, cooldownMinutes: 30 });
    renderAdminExam(store);
    await user.click(screen.getByRole("button", { name: /create exam/i }));
    expect(api).toHaveBeenCalledWith(
      "/admin/exams",
      expect.objectContaining({
        method: "POST",
        body: JSON.stringify({ title: "Test", maxAttempts: 2, cooldownMinutes: 30 }),
      })
    );
  });

  it("shows exam ID after create succeeds", () => {
    const store = createStore({ examId: "exam-456" });
    renderAdminExam(store);
    expect(screen.getByText("Exam ID:")).toBeInTheDocument();
    expect(screen.getByText("exam-456")).toBeInTheDocument();
  });

  it("shows error message and dismiss button when error is set", async () => {
    const user = userEvent.setup();
    const store = createStore({ error: "Something went wrong" });
    renderAdminExam(store);
    expect(screen.getByText("Something went wrong")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: /×/ }));
    expect(store.getState().admin.error).toBeNull();
  });

  it("shows attempts list when attempts are loaded", () => {
    const store = createStore({
      attempts: [
        {
          id: "a1",
          attemptNumber: 1,
          status: "COMPLETED",
          startedAt: "2025-01-30T10:00:00+00:00",
          endedAt: "2025-01-30T10:30:00+00:00",
          studentName: "Test",
          studentEmail: "test@test.com",
        },
        {
          id: "a2",
          attemptNumber: 2,
          status: "IN_PROGRESS",
          startedAt: "2025-01-30T11:00:00+00:00",
          endedAt: null,
          studentName: "Test",
          studentEmail: "test@test.com",
        },
      ],
    });
    renderAdminExam(store);
    expect(screen.getByRole("heading", { name: /attempts/i })).toBeInTheDocument();
    expect(screen.getByRole("columnheader", { name: /attempt #/i })).toBeInTheDocument();
    expect(screen.getByRole("columnheader", { name: /attempt id/i })).toBeInTheDocument();
    expect(screen.getByText("Completed")).toBeInTheDocument();
    expect(screen.getByText("In Progress")).toBeInTheDocument();
    expect(screen.getByText("a1")).toBeInTheDocument();
    expect(screen.getByText("a2")).toBeInTheDocument();
  });

  it("Update Exam and Load Attempts are disabled when examId is empty", () => {
    renderAdminExam();
    expect(screen.getByRole("button", { name: /update exam/i })).toBeDisabled();
    expect(screen.getByRole("button", { name: /load attempts/i })).toBeDisabled();
  });
});
