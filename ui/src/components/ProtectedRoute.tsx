import { Navigate } from "react-router-dom";
import { useAppSelector } from "../store";

interface ProtectedRouteProps {
  children: React.ReactNode;
  allowedRole: "admin" | "student";
}

export default function ProtectedRoute({ children, allowedRole }: Readonly<ProtectedRouteProps>) {
  const { user, isLoading } = useAppSelector((state) => state.auth);

  if (isLoading) {
    return <div className="loading">Loading...</div>;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (user.type !== allowedRole) {
    return <Navigate to={`/${user.type}`} replace />;
  }

  return <>{children}</>;
}
