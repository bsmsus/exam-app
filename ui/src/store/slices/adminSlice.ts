import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import type { PayloadAction } from "@reduxjs/toolkit";
import { api } from "../../api";

interface Attempt {
  id: string;
  attemptNumber: number;
  status: string;
  startedAt: string;
  endedAt: string | null;
  studentName: string;
  studentEmail: string;
}

export interface ExamListItem {
  id: string;
  title: string;
  maxAttempts: number;
  cooldownMinutes: number;
}

interface AdminState {
  examsList: ExamListItem[];
  examId: string;
  title: string;
  maxAttempts: number;
  cooldownMinutes: number;
  attempts: Attempt[];
  isLoading: boolean;
  error: string | null;
}

const initialState: AdminState = {
  examsList: [],
  examId: "",
  title: "",
  maxAttempts: 3,
  cooldownMinutes: 60,
  attempts: [],
  isLoading: false,
  error: null,
};

export const loadExamsList = createAsyncThunk("admin/loadExamsList", async (_, { rejectWithValue }) => {
  try {
    const exams = await api<ExamListItem[]>("/admin/exams");
    return exams;
  } catch (e: unknown) {
    const errorMessage =
      typeof e === "object" && e !== null && "message" in e ? (e as { message: string }).message : "Unknown error";
    return rejectWithValue(errorMessage);
  }
});

export const createExam = createAsyncThunk(
  "admin/createExam",
  async (
    { title, maxAttempts, cooldownMinutes }: { title: string; maxAttempts: number; cooldownMinutes: number },
    { rejectWithValue, dispatch },
  ) => {
    try {
      const res = await api<{ examId: string }>("/admin/exams", {
        method: "POST",
        body: JSON.stringify({ title, maxAttempts, cooldownMinutes }),
      });
      dispatch(loadExamsList());
      return res.examId;
    } catch (e: unknown) {
      const errorMessage =
        typeof e === "object" && e !== null && "message" in e ? (e as { message: string }).message : "Unknown error";
      return rejectWithValue(errorMessage);
    }
  },
);

export const updateExam = createAsyncThunk(
  "admin/updateExam",
  async (
    {
      examId,
      title,
      maxAttempts,
      cooldownMinutes,
    }: { examId: string; title: string; maxAttempts: number; cooldownMinutes: number },
    { rejectWithValue, dispatch },
  ) => {
    try {
      await api(`/admin/exams/${examId}`, {
        method: "PUT",
        body: JSON.stringify({ title, maxAttempts, cooldownMinutes }),
      });
      dispatch(loadExamsList());
      return { examId, title, maxAttempts, cooldownMinutes };
    } catch (e: unknown) {
      const errorMessage =
        typeof e === "object" && e !== null && "message" in e ? (e as { message: string }).message : "Unknown error";
      return rejectWithValue(errorMessage);
    }
  },
);

export const loadAttempts = createAsyncThunk("admin/loadAttempts", async (examId: string, { rejectWithValue }) => {
  try {
    const res = await api<Attempt[]>(`/admin/exams/${examId}/attempts`);
    return res;
  } catch (e: unknown) {
    const errorMessage =
      typeof e === "object" && e !== null && "message" in e ? (e as { message: string }).message : "Unknown error";
    return rejectWithValue(errorMessage);
  }
});

const adminSlice = createSlice({
  name: "admin",
  initialState,
  reducers: {
    setTitle: (state, action: PayloadAction<string>) => {
      state.title = action.payload;
    },
    setMaxAttempts: (state, action: PayloadAction<number>) => {
      state.maxAttempts = action.payload;
    },
    setCooldownMinutes: (state, action: PayloadAction<number>) => {
      state.cooldownMinutes = action.payload;
    },
    setExamId: (state, action: PayloadAction<string>) => {
      state.examId = action.payload;
    },
    clearError: (state) => {
      state.error = null;
    },
    resetForm: (state) => {
      state.examId = "";
      state.title = "";
      state.maxAttempts = 3;
      state.cooldownMinutes = 60;
      state.attempts = [];
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    // Create Exam
    builder.addCase(createExam.pending, (state) => {
      state.isLoading = true;
      state.error = null;
    });
    builder.addCase(createExam.fulfilled, (state, action) => {
      state.isLoading = false;
      state.examId = action.payload;
    });
    builder.addCase(createExam.rejected, (state, action) => {
      state.isLoading = false;
      state.error = action.payload as string;
    });

    // Update Exam
    builder.addCase(updateExam.pending, (state) => {
      state.isLoading = true;
      state.error = null;
    });
    builder.addCase(updateExam.fulfilled, (state) => {
      state.isLoading = false;
      state.attempts = [];
    });
    builder.addCase(updateExam.rejected, (state, action) => {
      state.isLoading = false;
      state.error = action.payload as string;
    });

    // Load Attempts
    builder.addCase(loadAttempts.pending, (state) => {
      state.isLoading = true;
      state.error = null;
    });
    builder.addCase(loadAttempts.fulfilled, (state, action) => {
      state.isLoading = false;
      state.attempts = action.payload;
    });
    builder.addCase(loadAttempts.rejected, (state, action) => {
      state.isLoading = false;
      state.error = action.payload as string;
    });

    // Load Exams List
    builder.addCase(loadExamsList.fulfilled, (state, action) => {
      state.examsList = action.payload;
    });
  },
});

export const { setTitle, setMaxAttempts, setCooldownMinutes, setExamId, clearError, resetForm } = adminSlice.actions;
export default adminSlice.reducer;
