import { render, screen, fireEvent } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'
import App from './App'

vi.mock('./admin/AdminExam', () => ({
  default: () => <div data-testid="admin-exam">Admin Exam Component</div>
}))

vi.mock('./student/StudentExam', () => ({
  default: () => <div data-testid="student-exam">Student Exam Component</div>
}))

describe('App', () => {
  it('renders Admin and Student buttons', () => {
    render(<App />)
    expect(screen.getByText('Admin')).toBeInTheDocument()
    expect(screen.getByText('Student')).toBeInTheDocument()
  })

  it('shows AdminExam by default', () => {
    render(<App />)
    expect(screen.getByTestId('admin-exam')).toBeInTheDocument()
  })

  it('switches to StudentExam when Student button is clicked', () => {
    render(<App />)
    fireEvent.click(screen.getByText('Student'))
    expect(screen.getByTestId('student-exam')).toBeInTheDocument()
  })

  it('switches back to AdminExam when Admin button is clicked', () => {
    render(<App />)
    fireEvent.click(screen.getByText('Student'))
    fireEvent.click(screen.getByText('Admin'))
    expect(screen.getByTestId('admin-exam')).toBeInTheDocument()
  })
})
