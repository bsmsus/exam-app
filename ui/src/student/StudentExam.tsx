import { useState } from "react";
import { api } from "../api";

export default function StudentExam() {
  const [examId, setExamId] = useState("");
  const [attemptId, setAttemptId] = useState("");
  const [message, setMessage] = useState("");
  const [isError, setIsError] = useState(false);

  async function start() {
    try {
      const res = await api<{ attemptId: string }>(`/student/exams/${examId}/start`, { method: "POST" });
      setAttemptId(res.attemptId);
      setMessage("Attempt started successfully");
      setIsError(false);
    } catch (e: any) {
      setMessage(e.message);
      setIsError(true);
    }
  }

  async function submit() {
    try {
      await api(`/student/attempts/${attemptId}/submit`, {
        method: "POST",
        body: JSON.stringify({
          status: "COMPLETED",
        }),
      });

      setAttemptId("");
      setMessage("Attempt submitted successfully");
      setIsError(false);
    } catch (e: any) {
      setMessage(e.message);
      setIsError(true);
    }
  }

  return (
    <>
      <h2>Student Portal</h2>

      <div className="form-group">
        <label htmlFor="student-exam-id">Exam ID</label>
        <input
          id="student-exam-id"
          placeholder="Enter your exam ID"
          value={examId}
          onChange={(e) => setExamId(e.target.value)}
        />
      </div>

      <div className="button-group">
        <button onClick={start} disabled={!examId}>
          Start Attempt
        </button>
        <button onClick={submit} disabled={!attemptId} className="button-secondary">
          Submit Attempt
        </button>
      </div>

      {message && (
        <div className={isError ? "error-message" : "success-message"}>
          {message}
        </div>
      )}

      {attemptId && (
        <div className="info-message">
          Current Attempt ID: {attemptId}
        </div>
      )}
    </>
  );
}
