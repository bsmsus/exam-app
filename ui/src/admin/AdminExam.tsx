import { useState, useEffect } from "react";
import {
  useAppDispatch,
  useAppSelector,
  createExam,
  updateExam,
  loadAttempts,
  loadExamsList,
  setTitle,
  setMaxAttempts,
  setCooldownMinutes,
  setAdminExamId,
  clearAdminError,
  resetAdminForm,
} from "../store";

export default function AdminExam() {
  const dispatch = useAppDispatch();
  const [copied, setCopied] = useState(false);

  const { examsList, examId, title, maxAttempts, cooldownMinutes, attempts, error, isLoading } = useAppSelector(
    (state) => state.admin
  );

  useEffect(() => {
    dispatch(loadExamsList());
  }, [dispatch]);

  async function copyExamId(id: string) {
    await navigator.clipboard.writeText(id);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }

  function handleSelectExam(exam: { id: string; title: string; maxAttempts: number; cooldownMinutes: number }) {
    dispatch(setAdminExamId(exam.id));
    dispatch(setTitle(exam.title));
    dispatch(setMaxAttempts(exam.maxAttempts));
    dispatch(setCooldownMinutes(exam.cooldownMinutes));
  }

  function handleNewExam() {
    dispatch(resetAdminForm());
  }

  function handleCreate() {
    dispatch(createExam({ title, maxAttempts, cooldownMinutes }));
  }

  function handleUpdate() {
    dispatch(updateExam({ examId, title, maxAttempts, cooldownMinutes }));
  }

  function handleLoadAttempts() {
    dispatch(loadAttempts(examId));
  }

  return (
    <>
      <h2>Admin Panel</h2>

      {/* Exams List */}
      <div className="exams-list">
        <div className="section-header">
          <h3>Your Exams</h3>
          {examId && (
            <button onClick={handleNewExam} className="button-secondary">
              + New Exam
            </button>
          )}
        </div>
        {examsList.length === 0 ? (
          <p className="no-data">No exams created yet.</p>
        ) : (
          <table className="history-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Max Attempts</th>
                <th>Cooldown</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {examsList.map((exam) => (
                <tr key={exam.id} className={exam.id === examId ? "selected" : ""}>
                  <td className="mono">{exam.id}</td>
                  <td>{exam.title}</td>
                  <td>{exam.maxAttempts}</td>
                  <td>{exam.cooldownMinutes} min</td>
                  <td>
                    <button
                      onClick={() => handleSelectExam(exam)}
                      disabled={isLoading || exam.id === examId}
                      className="button-secondary"
                    >
                      {exam.id === examId ? "Selected" : "Edit"}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <h3>{examId ? "Edit Exam" : "Create New Exam"}</h3>

      <div className="form-group">
        <label htmlFor="exam-title">Exam Title</label>
        <input
          id="exam-title"
          placeholder="Enter exam title"
          value={title}
          onChange={(e) => dispatch(setTitle(e.target.value))}
        />
      </div>

      <div className="form-row">
        <div className="form-group">
          <label htmlFor="max-attempts">Max Attempts</label>
          <input
            id="max-attempts"
            type="number"
            value={maxAttempts}
            onChange={(e) => dispatch(setMaxAttempts(+e.target.value))}
          />
        </div>
        <div className="form-group">
          <label htmlFor="cooldown">Cooldown (min)</label>
          <input
            id="cooldown"
            type="number"
            value={cooldownMinutes}
            onChange={(e) => dispatch(setCooldownMinutes(+e.target.value))}
          />
        </div>
      </div>

      <div className="button-group">
        <button onClick={handleCreate} disabled={isLoading}>
          {isLoading ? "Creating..." : "Create Exam"}
        </button>
        <button onClick={handleUpdate} disabled={!examId || isLoading} className="button-secondary">
          {isLoading ? "Updating..." : "Update Exam"}
        </button>
        <button onClick={handleLoadAttempts} disabled={!examId || isLoading} className="button-secondary">
          Load Attempts
        </button>
      </div>

      {examId && (
        <div className="success-message">
          Exam ID: <span className="mono">{examId}</span>
          <button
            type="button"
            onClick={() => copyExamId(examId)}
            className="copy-btn"
            title="Copy to clipboard"
          >
            {copied ? "Copied!" : "Copy"}
          </button>
        </div>
      )}
      {error && (
        <div className="error-message">
          {error}
          <button onClick={() => dispatch(clearAdminError())} className="dismiss-btn">
            &times;
          </button>
        </div>
      )}

      {attempts.length > 0 && (
        <div className="attempts-list">
          <h3>Attempts</h3>
          <table className="history-table">
            <thead>
              <tr>
                <th>Attempt Id</th>
                <th>Attempt #</th>
                <th>Status</th>
                <th>Start Time (UTC)</th>
                <th>End Time (UTC)</th>
              </tr>
            </thead>
            <tbody>
              {attempts.map((a) => (
                <tr key={a.id}>
                  <td className="mono">{a.id}</td>
                  <td>{a.attemptNumber}</td>
                  <td>
                    <span className={`status-badge ${a.status.toLowerCase()}`}>
                      {a.status === "IN_PROGRESS" ? "In Progress" : "Completed"}
                    </span>
                  </td>
                  <td>{a.startedAt ? new Date(a.startedAt).toISOString().replace("T", " ").slice(0, 19) : "-"}</td>
                  <td>{a.endedAt ? new Date(a.endedAt).toISOString().replace("T", " ").slice(0, 19) : "-"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
