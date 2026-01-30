import { useEffect } from "react";
import { BrowserRouter, Routes, Route, Navigate, useNavigate } from "react-router-dom";
import { Provider } from "react-redux";
import { store, useAppSelector, useAppDispatch, refreshAccessToken, logout } from "./store";
import { setAccessToken, setRefreshTokenFn } from "./api";
import Login from "./components/Login";
import ProtectedRoute from "./components/ProtectedRoute";
import AdminExam from "./admin/AdminExam";
import StudentExam from "./student/StudentExam";
import "./App.css";

function Layout({ children }: { children: React.ReactNode }) {
  const user = useAppSelector((state) => state.auth.user);
  const dispatch = useAppDispatch();

  const handleLogout = () => {
    dispatch(logout(user?.type));
  };

  return (
    <div className="app-container">
      <header className="app-header">
        <h1>Exam Portal</h1>
        {user ? (
          <div className="user-info">
            <span>Welcome, {user.name}</span>
            <span className="user-badge">{user.type}</span>
            <button onClick={handleLogout} className="logout-btn">
              Logout
            </button>
          </div>
        ) : (
          <p>Manage and take exams</p>
        )}
      </header>
      <div className="card">{children}</div>
    </div>
  );
}

function HomeRedirect() {
  const { user, isLoading } = useAppSelector((state) => state.auth);

  if (isLoading) {
    return (
      <div className="app-container">
        <div className="loading">Loading...</div>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  return <Navigate to={`/${user.type}`} replace />;
}

function LoginPage() {
  const { user, isLoading } = useAppSelector((state) => state.auth);
  const navigate = useNavigate();

  useEffect(() => {
    if (!isLoading && user) {
      navigate(`/${user.type}`, { replace: true });
    }
  }, [user, isLoading, navigate]);

  if (isLoading) {
    return (
      <Layout>
        <div className="loading">Loading...</div>
      </Layout>
    );
  }

  if (user) {
    return null;
  }

  return (
    <Layout>
      <Login />
    </Layout>
  );
}

function AppContent() {
  const { accessToken } = useAppSelector((state) => state.auth);
  const dispatch = useAppDispatch();

  // Initialize auth on mount
  useEffect(() => {
    dispatch(refreshAccessToken());
  }, [dispatch]);

  // Sync access token with API utility
  useEffect(() => {
    setAccessToken(accessToken);
    setRefreshTokenFn(async () => {
      const result = await dispatch(refreshAccessToken());
      if (refreshAccessToken.fulfilled.match(result)) {
        return result.payload.accessToken;
      }
      return null;
    });
  }, [accessToken, dispatch]);

  return (
    <Routes>
      <Route path="/" element={<HomeRedirect />} />
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/admin"
        element={
          <ProtectedRoute allowedRole="admin">
            <Layout>
              <AdminExam />
            </Layout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/student"
        element={
          <ProtectedRoute allowedRole="student">
            <Layout>
              <StudentExam />
            </Layout>
          </ProtectedRoute>
        }
      />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

export default function App() {
  return (
    <Provider store={store}>
      <BrowserRouter>
        <AppContent />
      </BrowserRouter>
    </Provider>
  );
}
