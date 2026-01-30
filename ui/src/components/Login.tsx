import { useState } from "react";
import { useAppDispatch, useAppSelector, login, register, clearAuthError } from "../store";

export default function Login() {
  const dispatch = useAppDispatch();
  const { error: authError, isLoading } = useAppSelector((state) => state.auth);

  const [mode, setMode] = useState<"login" | "register">("login");
  const [userType, setUserType] = useState<"admin" | "student">("student");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    dispatch(clearAuthError());

    if (mode === "login") {
      dispatch(login({ email, password, userType }));
    } else {
      dispatch(register({ name, email, password, userType }));
    }
  }

  return (
    <div className="login-container">
      <h2>{mode === "login" ? "Sign In" : "Create Account"}</h2>

      <div className="user-type-toggle">
        <button
          type="button"
          className={`toggle-btn ${userType === "student" ? "active" : ""}`}
          onClick={() => setUserType("student")}
        >
          Student
        </button>
        <button
          type="button"
          className={`toggle-btn ${userType === "admin" ? "active" : ""}`}
          onClick={() => setUserType("admin")}
        >
          Admin
        </button>
      </div>

      <form onSubmit={handleSubmit}>
        {mode === "register" && (
          <div className="form-group">
            <label htmlFor="name">Name</label>
            <input
              id="name"
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Enter your name"
              required
            />
          </div>
        )}

        <div className="form-group">
          <label htmlFor="email">Email</label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="Enter your email"
            required
          />
        </div>

        <div className="form-group">
          <label htmlFor="password">Password</label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="Enter your password"
            required
            minLength={6}
          />
        </div>

        {authError && <div className="error-message">{authError}</div>}

        <button type="submit" disabled={isLoading} className="submit-btn">
          {isLoading ? "Please wait..." : mode === "login" ? "Sign In" : "Create Account"}
        </button>
      </form>

      <p className="switch-mode">
        {mode === "login" ? (
          <>
            Don't have an account?{" "}
            <button type="button" onClick={() => setMode("register")} className="link-btn">
              Register
            </button>
          </>
        ) : (
          <>
            Already have an account?{" "}
            <button type="button" onClick={() => setMode("login")} className="link-btn">
              Sign In
            </button>
          </>
        )}
      </p>
    </div>
  );
}
