import { render, screen, waitFor } from "@testing-library/react";
import { describe, it, expect, vi, beforeEach } from "vitest";
import App from "./App";

vi.mock("./admin/AdminExam", () => ({
  default: () => <div data-testid="admin-exam">Admin Exam</div>,
}));

vi.mock("./student/StudentExam", () => ({
  default: () => <div data-testid="student-exam">Student Exam</div>,
}));

describe("App", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
    localStorage.clear();
    (fetch as ReturnType<typeof vi.fn>).mockImplementation((url: string) => {
      if (typeof url === "string" && url.includes("/auth/") && url.includes("/refresh")) {
        return Promise.resolve(new Response(JSON.stringify({}), { status: 401 }));
      }
      return Promise.resolve(new Response(JSON.stringify({}), { status: 404 }));
    });
  });

  it("eventually shows login when not authenticated", async () => {
    render(<App />);
    await waitFor(
      () => {
        expect(screen.getByRole("heading", { name: /sign in/i })).toBeInTheDocument();
      },
      { timeout: 3000 },
    );
  });

  it("shows Exam Portal header on login page", async () => {
    render(<App />);
    await waitFor(
      () => {
        expect(screen.getByRole("heading", { name: /exam portal/i })).toBeInTheDocument();
      },
      { timeout: 3000 },
    );
  });

  it("renders without crashing", () => {
    render(<App />);
    expect(document.body).toBeInTheDocument();
  });
});
