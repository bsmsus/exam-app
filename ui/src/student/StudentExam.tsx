import { useState } from "react";
import { api } from "../api";

export default function StudentExam() {
  const [examId, setExamId] = useState("");
  const [attemptId, setAttemptId] = useState("");
  const [message, setMessage] = useState("");

  async function start() {
    try {
      const res = await api<{ attemptId: string }>(`/student/exams/${examId}/start`, { method: "POST" });
      setAttemptId(res.attemptId);
      setMessage("Attempt started");
    } catch (e: any) {
      setMessage(e.message);
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
      setMessage("Attempt submitted");
    } catch (e: any) {
      setMessage(e.message);
    }
  }

  return (
    <>
      <h2>Student</h2>

      <input placeholder="Exam ID" value={examId} onChange={(e) => setExamId(e.target.value)} />

      <br />

      <button onClick={start}>Start Attempt</button>
      <button onClick={submit} disabled={!attemptId}>
        Submit Attempt
      </button>

      {message && <p>{message}</p>}
    </>
  );
}
