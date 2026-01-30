import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { Provider } from "react-redux";
import { configureStore } from "@reduxjs/toolkit";
import authReducer from "../store/slices/authSlice";
import adminReducer from "../store/slices/adminSlice";
import studentReducer, { type ExamDetails, type AttemptHistory } from "../store/slices/studentSlice";
import StudentExam from "./StudentExam";
import { api } from "../api";

vi.mock("../api", () => ({
  api: vi.fn(),
}));

const defaultExamDetails: ExamDetails = {
  title: "Math Exam",
  maxAttempts: 3,
  attemptsRemaining: 2,
  cooldownMinutes: 60,
  cooldownUntil: null,
  canStart: true,
};

describe("StudentExam", () => {
  const createStore = (studentState?: Partial<ReturnType<typeof studentReducer>>) =>
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
        },
        student: {
          examId: "",
          examDetails: null,
          currentAttempt: null,
          attemptHistory: [],
          message: "",
          isError: false,
          isLoading: false,
          ...studentState,
        },
      },
    });

  beforeEach(() => {
    vi.mocked(api).mockReset();
    vi.mocked(api).mockResolvedValue([]);
  });

  const renderStudentExam = (store = createStore()) =>
    render(
      <Provider store={store}>
        <StudentExam />
      </Provider>
    );

  it("renders Student Portal heading and exam ID input", () => {
    renderStudentExam();
    expect(screen.getByRole("heading", { name: /student portal/i })).toBeInTheDocument();
    expect(screen.getByLabelText(/exam id/i)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /load exam/i })).toBeInTheDocument();
  });

  it("renders My Attempts History section", () => {
    renderStudentExam();
    expect(screen.getByRole("heading", { name: /my attempts history/i })).toBeInTheDocument();
    expect(screen.getByText(/no attempts yet/i)).toBeInTheDocument();
  });

  it("dispatches setStudentExamId when typing exam ID", async () => {
    const user = userEvent.setup();
    const store = createStore();
    renderStudentExam(store);
    await user.type(screen.getByLabelText(/exam id/i), "exam-1");
    await waitFor(() => {
      expect(store.getState().student.examId).toBe("exam-1");
    });
  });

  it("shows exam details when examDetails is set", () => {
    const store = createStore({ examId: "e1", examDetails: defaultExamDetails });
    renderStudentExam(store);
    expect(screen.getByRole("heading", { name: /exam details/i })).toBeInTheDocument();
    expect(screen.getByText("Math Exam")).toBeInTheDocument();
    expect(screen.getByText("2")).toBeInTheDocument(); // attempts remaining
    expect(screen.getByRole("button", { name: /start attempt/i })).toBeInTheDocument();
  });

  it("shows no attempts left message when attemptsRemaining is 0", () => {
    const store = createStore({
      examDetails: { ...defaultExamDetails, attemptsRemaining: 0, canStart: false },
    });
    renderStudentExam(store);
    expect(screen.getByText(/no attempts left/i)).toBeInTheDocument();
  });

  it("shows current attempt and Submit Attempt button when currentAttempt is set", () => {
    const store = createStore({
      examId: "e1",
      examDetails: defaultExamDetails,
      currentAttempt: { id: "att-1", attemptNumber: 1, startedAt: "2025-01-01T12:00:00Z" },
    });
    renderStudentExam(store);
    expect(screen.getByText(/current attempt #1/i)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /submit attempt/i })).toBeInTheDocument();
  });

  it("shows attempt history table when attemptHistory has items", async () => {
    const history: AttemptHistory[] = [
      {
        id: "h1",
        examId: "e1",
        examTitle: "Math",
        attemptNumber: 1,
        status: "COMPLETED",
        startedAt: "2025-01-01T10:00:00Z",
        endedAt: "2025-01-01T11:00:00Z",
      },
    ];
    vi.mocked(api).mockResolvedValueOnce(history);
    const store = createStore();
    renderStudentExam(store);
    await waitFor(() => {
      expect(screen.getByText("Math")).toBeInTheDocument();
    });
    expect(screen.getByText("1")).toBeInTheDocument();
    expect(screen.getByText("Completed")).toBeInTheDocument();
  });

  it("shows success message when message is set and not error", () => {
    const store = createStore({ message: "Attempt started successfully", isError: false });
    renderStudentExam(store);
    expect(screen.getByText("Attempt started successfully")).toBeInTheDocument();
  });

  it("Load Exam button is disabled when examId is empty", () => {
    renderStudentExam();
    expect(screen.getByRole("button", { name: /load exam/i })).toBeDisabled();
  });
});
