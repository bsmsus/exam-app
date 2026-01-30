import { useEffect } from "react";
import {
  useAppDispatch,
  useAppSelector,
  loadAttemptHistory,
  loadExamDetails,
  startAttempt,
  submitAttempt,
  setStudentExamId,
} from "../store";

export default function StudentExam() {
  const dispatch = useAppDispatch();
  const { examId, examDetails, currentAttempt, attemptHistory, message, isError, isLoading } =
    useAppSelector((state) => state.student);

  useEffect(() => {
    dispatch(loadAttemptHistory());
  }, [dispatch]);

  // Auto-refresh when cooldown expires
  useEffect(() => {
    if (!examDetails?.cooldownUntil || examDetails.canStart) {
      return;
    }

    const cooldownEnd = new Date(examDetails.cooldownUntil).getTime();
    const now = Date.now();
    const timeUntilExpiry = cooldownEnd - now;

    console.log("Cooldown timer set:", {
      cooldownUntil: examDetails.cooldownUntil,
      timeUntilExpiry: timeUntilExpiry / 1000,
      seconds: "s",
    });

    if (timeUntilExpiry <= 0) {
      // Already expired, reload immediately
      dispatch(loadExamDetails(examId));
      return;
    }

    // Set a timer to reload when cooldown expires
    const timer = setTimeout(() => {
      console.log("Cooldown expired, refreshing exam details...");
      dispatch(loadExamDetails(examId));
    }, timeUntilExpiry);

    return () => clearTimeout(timer);
  }, [examDetails?.cooldownUntil, examDetails?.canStart, examId, dispatch]);

  function handleLoadExamDetails() {
    if (examId) {
      dispatch(loadExamDetails(examId));
    }
  }

  function handleContinueAttempt(attemptExamId: string) {
    dispatch(setStudentExamId(attemptExamId));
    dispatch(loadExamDetails(attemptExamId));
  }

  function handleStartAttempt() {
    dispatch(startAttempt({ examId, examDetails }));
  }

  function handleSubmitAttempt() {
    if (currentAttempt) {
      dispatch(submitAttempt({ attemptId: currentAttempt.id, examId }));
    }
  }

  function formatDateTime(isoString: string | null): string {
    if (!isoString) return "-";
    return new Date(isoString).toLocaleString();
  }

  function getStatusMessage(): string | null {
    if (!examDetails) return null;

    if (examDetails.attemptsRemaining === 0) {
      return "No attempts left.";
    }

    if (examDetails.cooldownUntil && !examDetails.canStart) {
      return `Your next attempt will be available at ${formatDateTime(examDetails.cooldownUntil)}`;
    }

    return null;
  }

  return (
    <>
      <h2>Student Portal</h2>

      {/* Exam ID Input */}
      <div className="form-group">
        <label htmlFor="student-exam-id">Exam ID</label>
        <div className="form-row">
          <input
            id="student-exam-id"
            placeholder="Enter your exam ID"
            value={examId}
            onChange={(e) => dispatch(setStudentExamId(e.target.value))}
          />
          <button onClick={handleLoadExamDetails} disabled={!examId || isLoading}>
            {isLoading ? "Loading..." : "Load Exam"}
          </button>
        </div>
      </div>

      {/* Exam Dashboard */}
      {examDetails && (
        <div className="exam-dashboard">
          <h3>Exam Details</h3>
          <table className="details-table">
            <tbody>
              <tr>
                <th>Title</th>
                <td>{examDetails.title}</td>
              </tr>
              <tr>
                <th>Max Attempts</th>
                <td>{examDetails.maxAttempts}</td>
              </tr>
              <tr>
                <th>Attempts Remaining</th>
                <td>{examDetails.attemptsRemaining}</td>
              </tr>
              <tr>
                <th>Cooldown Time</th>
                <td>{examDetails.cooldownMinutes} minutes</td>
              </tr>
            </tbody>
          </table>

          {/* Status Messages */}
          {getStatusMessage() && <div className="info-message">{getStatusMessage()}</div>}

          {/* Current Attempt */}
          {currentAttempt && (
            <div className="current-attempt">
              <h4>Current Attempt #{currentAttempt.attemptNumber}</h4>
              <p>Started at: {formatDateTime(currentAttempt.startedAt)}</p>
              <button onClick={handleSubmitAttempt} className="submit-btn">
                Submit Attempt
              </button>
            </div>
          )}

          {/* Start Button */}
          {!currentAttempt && examDetails.canStart && (
            <button onClick={handleStartAttempt} className="start-btn">
              Start Attempt
            </button>
          )}
        </div>
      )}

      {/* Messages */}
      {message && <div className={isError ? "error-message" : "success-message"}>{message}</div>}

      {/* Attempt History */}
      <div className="attempt-history">
        <h3>My Attempts History</h3>
        {attemptHistory.length === 0 ? (
          <p className="no-data">No attempts yet.</p>
        ) : (
          <table className="history-table">
            <thead>
              <tr>
                <th>Exam</th>
                <th>Attempt #</th>
                <th>Status</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {attemptHistory.map((attempt) => (
                <tr key={attempt.id}>
                  <td>
                    <button
                      onClick={() => handleContinueAttempt(attempt.examId)}
                      className="link-btn"
                      disabled={isLoading}
                    >
                      {attempt.examTitle}
                    </button>
                  </td>
                  <td>{attempt.attemptNumber}</td>
                  <td>
                    <span className={`status-badge ${attempt.status.toLowerCase()}`}>
                      {attempt.status === "IN_PROGRESS" ? "In Progress" : "Completed"}
                    </span>
                  </td>
                  <td>{formatDateTime(attempt.startedAt)}</td>
                  <td>{formatDateTime(attempt.endedAt)}</td>
                  <td>
                    {attempt.status === "IN_PROGRESS" && (
                      <button
                        onClick={() => handleContinueAttempt(attempt.examId)}
                        className="button-secondary"
                        disabled={isLoading}
                      >
                        Continue
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
