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
      setError("");
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
      setError("");
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
      <h2>Admin Panel</h2>

      <div className="form-group">
        <label htmlFor="exam-title">Exam Title</label>
        <input
          id="exam-title"
          placeholder="Enter exam title"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
        />
      </div>

      <div className="form-row">
        <div className="form-group">
          <label htmlFor="max-attempts">Max Attempts</label>
          <input
            id="max-attempts"
            type="number"
            value={maxAttempts}
            onChange={(e) => setMaxAttempts(+e.target.value)}
          />
        </div>
        <div className="form-group">
          <label htmlFor="cooldown">Cooldown (min)</label>
          <input id="cooldown" type="number" value={cooldown} onChange={(e) => setCooldown(+e.target.value)} />
        </div>
      </div>

      <div className="button-group">
        <button onClick={create}>Create Exam</button>
        <button onClick={update} disabled={!examId} className="button-secondary">
          Update Exam
        </button>
        <button onClick={loadAttempts} disabled={!examId} className="button-secondary">
          Load Attempts
        </button>
      </div>

      {examId && <div className="success-message">Exam ID: {examId}</div>}
      {error && <div className="error-message">{error}</div>}

      {attempts.length > 0 && (
        <div className="attempts-list">
          <h3>Attempts</h3>
          <ul>
            {attempts.map((a) => (
              <li key={a.attemptId}>
                <span className="attempt-number">Attempt #{a.attemptNumber}</span>
                <span className={`attempt-status ${a.status.toLowerCase()}`}>{a.status}</span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </>
  );
}
