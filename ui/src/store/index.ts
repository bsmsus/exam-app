export { store } from "./store";
export type { RootState, AppDispatch } from "./store";
export { useAppDispatch, useAppSelector } from "./hooks";

// Auth exports
export { login, register, logout, refreshAccessToken, clearError as clearAuthError } from "./slices/authSlice";
export type { User } from "./slices/authSlice";

// Admin exports
export {
  createExam,
  updateExam,
  loadAttempts,
  loadExamsList,
  setTitle,
  setMaxAttempts,
  setCooldownMinutes,
  setExamId as setAdminExamId,
  clearError as clearAdminError,
  resetForm as resetAdminForm,
} from "./slices/adminSlice";
export type { ExamListItem } from "./slices/adminSlice";

// Student exports
export {
  loadAttemptHistory,
  loadExamDetails,
  startAttempt,
  submitAttempt,
  setExamId as setStudentExamId,
  clearMessage,
  resetExam,
} from "./slices/studentSlice";
export type { ExamDetails, CurrentAttempt, AttemptHistory } from "./slices/studentSlice";
