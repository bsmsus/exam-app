import { useState } from "react";
import { api } from "../api";

export default function AdminExam() {
  const [examId, setExamId] = useState("");
  const [title, setTitle] = useState("");
  const [maxAttempts, setMaxAttempts] = useState(3);
  const [cooldown, setCooldown] = useState(60);
  const [attempts, setAttempts] = useState<any[]>([]);
  const [error, setError] = useState("");

  async function create() {
    try {
      const res = await api<{ examId: string }>("/admin/exams", {
        method: "POST",
        body: JSON.stringify({ title, maxAttempts, cooldownMinutes: cooldown }),
      });
      setExamId(res.examId);
    } catch (e: any) {
      setError(e.message);
    }
  }

  async function update() {
    try {
      await api(`/admin/exams/${examId}`, {
        method: "PUT",
        body: JSON.stringify({ title, maxAttempts, cooldownMinutes: cooldown }),
      });
      setAttempts([]);
    } catch (e: any) {
      setError(e.message);
    }
  }

  async function loadAttempts() {
    const res = await api<any[]>(`/admin/exams/${examId}/attempts`);
    setAttempts(res);
  }

  return (
    <>
      <h2>Admin</h2>

      <input placeholder="Title" onChange={(e) => setTitle(e.target.value)} />
      <input type="number" value={maxAttempts} onChange={(e) => setMaxAttempts(+e.target.value)} />
      <input type="number" value={cooldown} onChange={(e) => setCooldown(+e.target.value)} />

      <br />

      <button onClick={create}>Create Exam</button>
      <button onClick={update} disabled={!examId}>
        Update Exam
      </button>
      <button onClick={loadAttempts} disabled={!examId}>
        Load Attempts
      </button>

      {examId && <p>Exam ID: {examId}</p>}
      {error && <p style={{ color: "red" }}>{error}</p>}

      <ul>
        {attempts.map((a) => (
          <li key={a.attemptId}>
            #{a.attemptNumber} — {a.status}
          </li>
        ))}
      </ul>
    </>
  );
}
