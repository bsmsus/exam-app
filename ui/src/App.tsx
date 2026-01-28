import { useState } from "react";
import AdminExam from "./admin/AdminExam";
import StudentExam from "./student/StudentExam";

export default function App() {
  const [view, setView] = useState<"admin" | "student">("admin");

  return (
    <div style={{ padding: 20 }}>
      <button onClick={() => setView("admin")}>Admin</button>
      <button onClick={() => setView("student")}>Student</button>

      <hr />

      {view === "admin" ? <AdminExam /> : <StudentExam />}
    </div>
  );
}
