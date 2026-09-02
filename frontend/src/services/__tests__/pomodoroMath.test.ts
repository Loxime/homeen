import { describe, expect, it } from 'vitest'
import { calculatePomodoroState } from '../pomodoroMath'

describe('calculatePomodoroState', () => {
  const start = '2026-09-02T10:00:00.000Z'

  it('starts in work mode', () => {
    const state = calculatePomodoroState(start, 25, Date.parse(start))
    expect(state.phase).toBe('work')
    expect(state.remainingSeconds).toBe(1500)
  })

  it('uses an immutable five minute break', () => {
    const state = calculatePomodoroState(start, 25, Date.parse(start) + 25 * 60 * 1000)
    expect(state.phase).toBe('break')
    expect(state.remainingSeconds).toBe(300)
  })

  it('keeps cycling from elapsed timestamps', () => {
    const state = calculatePomodoroState(start, 25, Date.parse(start) + 62 * 60 * 1000)
    expect(state.phase).toBe('work')
    expect(state.completedWorkCycles).toBe(2)
    expect(state.completedBreakCycles).toBe(2)
  })
})
