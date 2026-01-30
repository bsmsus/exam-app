import { describe, it, expect } from "vitest";
import studentReducer, {
  setExamId,
  clearMessage,
  resetExam,
  loadAttemptHistory,
  loadExamDetails,
  startAttempt,
  submitAttempt,
} from "./studentSlice";

describe("studentSlice", () => {
  const initialState = {
    examId: "",
    examDetails: null,
    currentAttempt: null,
    attemptHistory: [],
    message: "",
    isError: false,
    isLoading: false,
  };

  it("has correct initial state", () => {
    expect(studentReducer(undefined, { type: "unknown" })).toEqual(initialState);
  });

  it("setExamId updates examId", () => {
    const state = studentReducer(initialState, setExamId("exam-1"));
    expect(state.examId).toBe("exam-1");
  });

  it("clearMessage clears message and isError", () => {
    const state = studentReducer(
      { ...initialState, message: "Error", isError: true },
      clearMessage()
    );
    expect(state.message).toBe("");
    expect(state.isError).toBe(false);
  });

  it("resetExam resets exam-related state", () => {
    const state = studentReducer(
      {
        examId: "e1",
        examDetails: { title: "T", maxAttempts: 3, attemptsRemaining: 2, cooldownMinutes: 60, cooldownUntil: null, canStart: true },
        currentAttempt: { id: "a1", attemptNumber: 1, startedAt: "" },
        attemptHistory: [],
        message: "msg",
        isError: true,
        isLoading: false,
      },
      resetExam()
    );
    expect(state.examId).toBe("");
    expect(state.examDetails).toBeNull();
    expect(state.currentAttempt).toBeNull();
    expect(state.message).toBe("");
    expect(state.isError).toBe(false);
  });

  it("loadAttemptHistory.fulfilled sets attemptHistory", () => {
    const history = [
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
    const state = studentReducer(initialState, loadAttemptHistory.fulfilled(history, ""));
    expect(state.attemptHistory).toEqual(history);
  });

  it("loadExamDetails.pending sets isLoading and clears message", () => {
    const state = studentReducer(
      { ...initialState, message: "Old", isError: true },
      loadExamDetails.pending("", "exam-1")
    );
    expect(state.isLoading).toBe(true);
    expect(state.message).toBe("");
    expect(state.isError).toBe(false);
  });

  it("loadExamDetails.fulfilled sets examDetails and currentAttempt", () => {
    const details = {
      title: "Math",
      maxAttempts: 3,
      attemptsRemaining: 2,
      cooldownMinutes: 60,
      cooldownUntil: null,
      canStart: true,
    };
    const currentAttempt = { id: "a1", attemptNumber: 1, startedAt: "2025-01-01T12:00:00Z" };
    const state = studentReducer(
      { ...initialState, isLoading: true },
      loadExamDetails.fulfilled({ details, currentAttempt }, "exam-1", "exam-1")
    );
    expect(state.isLoading).toBe(false);
    expect(state.examDetails).toEqual(details);
    expect(state.currentAttempt).toEqual(currentAttempt);
  });

  it("loadExamDetails.rejected sets message and isError", () => {
    const state = studentReducer(
      { ...initialState, isLoading: true },
      loadExamDetails.rejected(null, "exam-1", "exam-1", "Exam not found")
    );
    expect(state.isLoading).toBe(false);
    expect(state.message).toBe("Exam not found");
    expect(state.isError).toBe(true);
    expect(state.examDetails).toBeNull();
    expect(state.currentAttempt).toBeNull();
  });

  it("startAttempt.fulfilled sets currentAttempt and success message", () => {
    const attempt = { id: "a1", attemptNumber: 1, startedAt: "2025-01-01T12:00:00Z" };
    const state = studentReducer(
      initialState,
      startAttempt.fulfilled(attempt, "", { examId: "e1", examDetails: null })
    );
    expect(state.currentAttempt).toEqual(attempt);
    expect(state.message).toBe("Attempt started successfully");
    expect(state.isError).toBe(false);
  });

  it("startAttempt.rejected sets error message", () => {
    const state = studentReducer(
      initialState,
      startAttempt.rejected(null, "", { examId: "e1", examDetails: null }, "Cannot start")
    );
    expect(state.message).toBe("Cannot start");
    expect(state.isError).toBe(true);
  });

  it("submitAttempt.fulfilled clears currentAttempt and sets success message", () => {
    const state = studentReducer(
      {
        ...initialState,
        currentAttempt: { id: "a1", attemptNumber: 1, startedAt: "" },
      },
      submitAttempt.fulfilled(null, "", { attemptId: "a1", examId: "e1" })
    );
    expect(state.currentAttempt).toBeNull();
    expect(state.message).toBe("Attempt submitted successfully");
    expect(state.isError).toBe(false);
  });
});
