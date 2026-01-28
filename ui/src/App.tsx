import { useState } from "react";
import AdminExam from "./admin/AdminExam";
import StudentExam from "./student/StudentExam";
import "./App.css";

export default function App() {
  const [view, setView] = useState<"admin" | "student">("admin");

  return (
    <div className="app-container">
      <header className="app-header">
        <h1>Exam Portal</h1>
        <p>Manage and take exams</p>
      </header>

      <nav className="tab-nav">
        <button
          className={`tab-btn ${view === "admin" ? "active" : ""}`}
          onClick={() => setView("admin")}
        >
          Admin
        </button>
        <button
          className={`tab-btn ${view === "student" ? "active" : ""}`}
          onClick={() => setView("student")}
        >
          Student
        </button>
      </nav>

      <div className="card">
        {view === "admin" ? <AdminExam /> : <StudentExam />}
      </div>
    </div>
  );
}
