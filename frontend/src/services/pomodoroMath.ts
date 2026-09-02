export interface PomodoroLiveState {
  phase: 'work' | 'break'
  remainingSeconds: number
  completedWorkCycles: number
  completedBreakCycles: number
  focusSeconds: number
  breakSeconds: number
}

export const BREAK_MINUTES = 5

export function calculatePomodoroState(startedAt: string, workMinutes: number, nowMs = Date.now()): PomodoroLiveState {
  if (!Number.isInteger(workMinutes) || workMinutes < 5) {
    throw new Error('Work duration must be an integer of at least 5 minutes.')
  }

  const elapsed = Math.max(0, Math.floor((nowMs - new Date(startedAt).getTime()) / 1000))
  const workSeconds = workMinutes * 60
  const breakSeconds = BREAK_MINUTES * 60
  const cycleSeconds = workSeconds + breakSeconds
  const fullCycles = Math.floor(elapsed / cycleSeconds)
  const within = elapsed % cycleSeconds
  const focusTotal = fullCycles * workSeconds + Math.min(within, workSeconds)
  const breakTotal = fullCycles * breakSeconds + Math.max(0, within - workSeconds)

  if (within < workSeconds) {
    return {
      phase: 'work',
      remainingSeconds: workSeconds - within,
      completedWorkCycles: fullCycles,
      completedBreakCycles: fullCycles,
      focusSeconds: focusTotal,
      breakSeconds: breakTotal,
    }
  }

  return {
    phase: 'break',
    remainingSeconds: cycleSeconds - within,
    completedWorkCycles: fullCycles + 1,
    completedBreakCycles: fullCycles,
    focusSeconds: focusTotal,
    breakSeconds: breakTotal,
  }
}
