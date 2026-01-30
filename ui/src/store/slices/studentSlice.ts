import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import type { PayloadAction } from "@reduxjs/toolkit";
import { api } from "../../api";

export interface ExamDetails {
  title: string;
  maxAttempts: number;
  attemptsRemaining: number;
  cooldownMinutes: number;
  cooldownUntil: string | null;
  canStart: boolean;
}

export interface CurrentAttempt {
  id: string;
  attemptNumber: number;
  startedAt: string;
}

export interface AttemptHistory {
  id: string;
  examId: string;
  examTitle: string;
  attemptNumber: number;
  status: string;
  startedAt: string;
  endedAt: string | null;
}

interface StudentState {
  examId: string;
  examDetails: ExamDetails | null;
  currentAttempt: CurrentAttempt | null;
  attemptHistory: AttemptHistory[];
  message: string;
  isError: boolean;
  isLoading: boolean;
}

const initialState: StudentState = {
  examId: "",
  examDetails: null,
  currentAttempt: null,
  attemptHistory: [],
  message: "",
  isError: false,
  isLoading: false,
};

export const loadAttemptHistory = createAsyncThunk("student/loadAttemptHistory", async (_, { rejectWithValue }) => {
  try {
    const history = await api<AttemptHistory[]>("/student/attempts");
    return history;
  } catch (e: unknown) {
    const errorMessage =
      typeof e === "object" && e !== null && "message" in e ? (e as { message: string }).message : "Unknown error";
    return rejectWithValue(errorMessage);
  }
});

export const loadExamDetails = createAsyncThunk(
  "student/loadExamDetails",
  async (examId: string, { rejectWithValue, dispatch }) => {
    try {
      const [details, attemptRes] = await Promise.all([
        api<ExamDetails>(`/student/exams/${examId}`),
        api<{ currentAttempt: CurrentAttempt | null }>(`/student/exams/${examId}/current-attempt`),
      ]);

      // Refresh attempt history when loading exam details
      dispatch(loadAttemptHistory());

      return { details, currentAttempt: attemptRes.currentAttempt };
    } catch (e: unknown) {
      const errorMessage =
        typeof e === "object" && e !== null && "message" in e ? (e as { message: string }).message : "Unknown error";
      return rejectWithValue(errorMessage);
    }
  },
);

export const startAttempt = createAsyncThunk(
  "student/startAttempt",
  async (
    { examId, examDetails }: { examId: string; examDetails: ExamDetails | null },
    { rejectWithValue, dispatch },
  ) => {
    try {
      const res = await api<{ attemptId: string }>(`/student/exams/${examId}/start`, { method: "POST" });
      const attemptNumber = (examDetails?.maxAttempts ?? 0) - (examDetails?.attemptsRemaining ?? 0) + 1;

      // Reload exam details and history after starting
      dispatch(loadExamDetails(examId));
      dispatch(loadAttemptHistory());

      return {
        id: res.attemptId,
        attemptNumber,
        startedAt: new Date().toISOString(),
      };
    } catch (e: unknown) {
      const errorMessage =
        typeof e === "object" && e !== null && "message" in e ? (e as { message: string }).message : "Unknown error";
      return rejectWithValue(errorMessage);
    }
  },
);

export const submitAttempt = createAsyncThunk(
  "student/submitAttempt",
  async ({ attemptId, examId }: { attemptId: string; examId: string }, { rejectWithValue, dispatch }) => {
    try {
      await api(`/student/attempts/${attemptId}/submit`, { method: "POST" });

      // Reload exam details and history after submitting
      dispatch(loadExamDetails(examId));
      dispatch(loadAttemptHistory());

      return null;
    } catch (e: unknown) {
      const errorMessage =
        typeof e === "object" && e !== null && "message" in e ? (e as { message: string }).message : "Unknown error";
      return rejectWithValue(errorMessage);
    }
  },
);

const studentSlice = createSlice({
  name: "student",
  initialState,
  reducers: {
    setExamId: (state, action: PayloadAction<string>) => {
      state.examId = action.payload;
    },
    clearMessage: (state) => {
      state.message = "";
      state.isError = false;
    },
    resetExam: (state) => {
      state.examId = "";
      state.examDetails = null;
      state.currentAttempt = null;
      state.message = "";
      state.isError = false;
    },
  },
  extraReducers: (builder) => {
    // Load Attempt History
    builder.addCase(loadAttemptHistory.fulfilled, (state, action) => {
      state.attemptHistory = action.payload;
    });

    // Load Exam Details
    builder.addCase(loadExamDetails.pending, (state) => {
      state.isLoading = true;
      state.message = "";
      state.isError = false;
    });
    builder.addCase(loadExamDetails.fulfilled, (state, action) => {
      state.isLoading = false;
      state.examDetails = action.payload.details;
      state.currentAttempt = action.payload.currentAttempt;
    });
    builder.addCase(loadExamDetails.rejected, (state, action) => {
      state.isLoading = false;
      state.message = action.payload as string;
      state.isError = true;
      state.examDetails = null;
      state.currentAttempt = null;
    });

    // Start Attempt
    builder.addCase(startAttempt.pending, (state) => {
      state.message = "";
      state.isError = false;
    });
    builder.addCase(startAttempt.fulfilled, (state, action) => {
      state.currentAttempt = action.payload;
      state.message = "Attempt started successfully";
      state.isError = false;
    });
    builder.addCase(startAttempt.rejected, (state, action) => {
      state.message = action.payload as string;
      state.isError = true;
    });

    // Submit Attempt
    builder.addCase(submitAttempt.pending, (state) => {
      state.message = "";
      state.isError = false;
    });
    builder.addCase(submitAttempt.fulfilled, (state) => {
      state.currentAttempt = null;
      state.message = "Attempt submitted successfully";
      state.isError = false;
    });
    builder.addCase(submitAttempt.rejected, (state, action) => {
      state.message = action.payload as string;
      state.isError = true;
    });
  },
});

export const { setExamId, clearMessage, resetExam } = studentSlice.actions;
export default studentSlice.reducer;
