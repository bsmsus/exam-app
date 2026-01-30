import { describe, it, expect } from "vitest";
import adminReducer, {
  setTitle,
  setMaxAttempts,
  setCooldownMinutes,
  setExamId,
  clearError,
  resetForm,
  createExam,
  updateExam,
  loadAttempts,
} from "./adminSlice";

describe("adminSlice", () => {
  const initialState = {
    examsList: [],
    examId: "",
    title: "",
    maxAttempts: 3,
    cooldownMinutes: 60,
    attempts: [],
    isLoading: false,
    error: null,
  };

  it("has correct initial state", () => {
    expect(adminReducer(undefined, { type: "unknown" })).toEqual(initialState);
  });

  it("setTitle updates title", () => {
    const state = adminReducer(initialState, setTitle("My Exam"));
    expect(state.title).toBe("My Exam");
  });

  it("setMaxAttempts updates maxAttempts", () => {
    const state = adminReducer(initialState, setMaxAttempts(5));
    expect(state.maxAttempts).toBe(5);
  });

  it("setCooldownMinutes updates cooldownMinutes", () => {
    const state = adminReducer(initialState, setCooldownMinutes(30));
    expect(state.cooldownMinutes).toBe(30);
  });

  it("setExamId updates examId", () => {
    const state = adminReducer(initialState, setExamId("exam-123"));
    expect(state.examId).toBe("exam-123");
  });

  it("clearError sets error to null", () => {
    const state = adminReducer({ ...initialState, error: "Failed" }, clearError());
    expect(state.error).toBeNull();
  });

  it("resetForm resets form and list values", () => {
    const state = adminReducer(
      {
        examsList: [{ id: "e1", title: "T", maxAttempts: 5, cooldownMinutes: 30 }],
        examId: "e1",
        title: "T",
        maxAttempts: 5,
        cooldownMinutes: 30,
        attempts: [{ id: "a1", attemptNumber: 1, status: "COMPLETED", startedAt: "", endedAt: null, studentName: "", studentEmail: "" }],
        isLoading: true,
        error: "err",
      },
      resetForm()
    );
    expect(state.examId).toBe("");
    expect(state.title).toBe("");
    expect(state.maxAttempts).toBe(3);
    expect(state.cooldownMinutes).toBe(60);
    expect(state.attempts).toEqual([]);
    expect(state.error).toBeNull();
    expect(state.isLoading).toBe(true);
  });

  it("createExam.pending sets isLoading and clears error", () => {
    const state = adminReducer(
      { ...initialState, error: "Old" },
      createExam.pending("", { title: "T", maxAttempts: 2, cooldownMinutes: 10 })
    );
    expect(state.isLoading).toBe(true);
    expect(state.error).toBeNull();
  });

  it("createExam.fulfilled sets examId", () => {
    const state = adminReducer(
      { ...initialState, isLoading: true },
      createExam.fulfilled("exam-456", "", { title: "T", maxAttempts: 2, cooldownMinutes: 10 })
    );
    expect(state.isLoading).toBe(false);
    expect(state.examId).toBe("exam-456");
  });

  it("createExam.rejected sets error", () => {
    const state = adminReducer(
      { ...initialState, isLoading: true },
      createExam.rejected(null, "", { title: "T", maxAttempts: 2, cooldownMinutes: 10 }, "API error")
    );
    expect(state.isLoading).toBe(false);
    expect(state.error).toBe("API error");
  });

  it("loadAttempts.fulfilled sets attempts", () => {
    const attempts = [
      { id: "a1", attemptNumber: 1, status: "COMPLETED", startedAt: "2025-01-30T10:00:00Z", endedAt: "2025-01-30T10:30:00Z", studentName: "Test", studentEmail: "test@test.com" },
      { id: "a2", attemptNumber: 2, status: "IN_PROGRESS", startedAt: "2025-01-30T11:00:00Z", endedAt: null, studentName: "Test", studentEmail: "test@test.com" },
    ];
    const state = adminReducer(
      { ...initialState, isLoading: true },
      loadAttempts.fulfilled(attempts, "exam-1", "exam-1")
    );
    expect(state.isLoading).toBe(false);
    expect(state.attempts).toEqual(attempts);
  });

  it("updateExam.fulfilled clears attempts", () => {
    const state = adminReducer(
      {
        ...initialState,
        examId: "e1",
        attempts: [{ id: "a1", attemptNumber: 1, status: "COMPLETED", startedAt: "", endedAt: null, studentName: "", studentEmail: "" }],
        isLoading: true,
      },
      updateExam.fulfilled(
        { examId: "e1", title: "New", maxAttempts: 4, cooldownMinutes: 45 },
        "",
        { examId: "e1", title: "New", maxAttempts: 4, cooldownMinutes: 45 }
      )
    );
    expect(state.isLoading).toBe(false);
    expect(state.attempts).toEqual([]);
  });
});
