import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";

export interface User {
  id: string;
  name: string;
  email: string;
  type: "admin" | "student";
}

interface AuthState {
  user: User | null;
  accessToken: string | null;
  isLoading: boolean;
  error: string | null;
}

const initialState: AuthState = {
  user: null,
  accessToken: null,
  isLoading: true,
  error: null,
};

export const login = createAsyncThunk(
  "auth/login",
  async (
    { email, password, userType }: { email: string; password: string; userType: "admin" | "student" },
    { rejectWithValue }
  ) => {
    const res = await fetch(`${API_BASE}/auth/${userType}/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
    });

    if (!res.ok) {
      const data = await res.json();
      return rejectWithValue(data.error || "Login failed");
    }

    const data = await res.json();
    localStorage.setItem("refreshToken", data.refreshToken);
    localStorage.setItem("userType", userType);
    return { user: data.user, accessToken: data.accessToken };
  }
);

export const register = createAsyncThunk(
  "auth/register",
  async (
    { name, email, password, userType }: { name: string; email: string; password: string; userType: "admin" | "student" },
    { rejectWithValue }
  ) => {
    const res = await fetch(`${API_BASE}/auth/${userType}/register`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, email, password }),
    });

    if (!res.ok) {
      const data = await res.json();
      return rejectWithValue(data.error || "Registration failed");
    }

    const data = await res.json();
    localStorage.setItem("refreshToken", data.refreshToken);
    localStorage.setItem("userType", userType);
    return { user: data.user, accessToken: data.accessToken };
  }
);

export const refreshAccessToken = createAsyncThunk(
  "auth/refreshToken",
  async (_, { rejectWithValue }) => {
    const refreshToken = localStorage.getItem("refreshToken");
    const userType = localStorage.getItem("userType");

    if (!refreshToken || !userType) {
      return rejectWithValue("No refresh token");
    }

    const res = await fetch(`${API_BASE}/auth/${userType}/refresh`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ refreshToken }),
    });

    if (!res.ok) {
      localStorage.removeItem("refreshToken");
      localStorage.removeItem("userType");
      return rejectWithValue("Token refresh failed");
    }

    const data = await res.json();
    localStorage.setItem("refreshToken", data.refreshToken);
    return { user: data.user, accessToken: data.accessToken };
  }
);

export const logout = createAsyncThunk(
  "auth/logout",
  async (userType: "admin" | "student" | undefined) => {
    const refreshToken = localStorage.getItem("refreshToken");
    const type = userType || localStorage.getItem("userType");

    if (refreshToken && type) {
      fetch(`${API_BASE}/auth/${type}/logout`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ refreshToken }),
      }).catch(() => {});
    }

    localStorage.removeItem("refreshToken");
    localStorage.removeItem("userType");
  }
);

const authSlice = createSlice({
  name: "auth",
  initialState,
  reducers: {
    clearError: (state) => {
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    // Login
    builder.addCase(login.pending, (state) => {
      state.isLoading = true;
      state.error = null;
    });
    builder.addCase(login.fulfilled, (state, action) => {
      state.isLoading = false;
      state.user = action.payload.user;
      state.accessToken = action.payload.accessToken;
    });
    builder.addCase(login.rejected, (state, action) => {
      state.isLoading = false;
      state.error = action.payload as string;
    });

    // Register
    builder.addCase(register.pending, (state) => {
      state.isLoading = true;
      state.error = null;
    });
    builder.addCase(register.fulfilled, (state, action) => {
      state.isLoading = false;
      state.user = action.payload.user;
      state.accessToken = action.payload.accessToken;
    });
    builder.addCase(register.rejected, (state, action) => {
      state.isLoading = false;
      state.error = action.payload as string;
    });

    // Refresh token
    builder.addCase(refreshAccessToken.pending, (state) => {
      state.isLoading = true;
    });
    builder.addCase(refreshAccessToken.fulfilled, (state, action) => {
      state.isLoading = false;
      state.user = action.payload.user;
      state.accessToken = action.payload.accessToken;
    });
    builder.addCase(refreshAccessToken.rejected, (state) => {
      state.isLoading = false;
      state.user = null;
      state.accessToken = null;
    });

    // Logout
    builder.addCase(logout.fulfilled, (state) => {
      state.user = null;
      state.accessToken = null;
      state.error = null;
    });
  },
});

export const { clearError } = authSlice.actions;
export default authSlice.reducer;
