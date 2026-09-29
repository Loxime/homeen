import {
  api,
} from './api'

import type {
  PomodoroHistoryPagination,
  PomodoroInsights,
  PomodoroPreset,
  PomodoroSession,
} from '../types/domain'

export interface PomodoroHistoryResponse {
  sessions: PomodoroSession[]
  pagination: PomodoroHistoryPagination
}

export async function getPomodoroActive():
Promise<PomodoroSession | null> {
  const response =
    await api<{
      session: PomodoroSession | null
    }>(
      '/api/pomodoro/active',
    )

  return response.session
}

export async function getPomodoroPresets():
Promise<PomodoroPreset[]> {
  const response =
    await api<{
      presets: PomodoroPreset[]
    }>(
      '/api/pomodoro/presets',
    )

  return response.presets
}

export async function startPomodoroSession(
  workMinutes: number,
): Promise<PomodoroSession> {
  return api<PomodoroSession>(
    '/api/pomodoro/sessions',
    {
      method: 'POST',
      body: JSON.stringify({
        workMinutes,
      }),
    },
  )
}

export async function stopPomodoroSession(
  sessionId: number,
): Promise<PomodoroSession> {
  return api<PomodoroSession>(
    `/api/pomodoro/sessions/${sessionId}/stop`,
    {
      method: 'POST',
    },
  )
}

export async function getPomodoroHistory(
  page: number,
): Promise<PomodoroHistoryResponse> {
  return api<PomodoroHistoryResponse>(
    `/api/pomodoro/history?page=${page}`,
  )
}

export async function getPomodoroInsights():
Promise<PomodoroInsights> {
  return api<PomodoroInsights>(
    '/api/pomodoro/insights',
  )
}

export async function ratePomodoroSession(
  sessionId: number,
  rating: 1 | 2 | 3,
): Promise<PomodoroSession> {
  return api<PomodoroSession>(
    `/api/pomodoro/sessions/${sessionId}/rating`,
    {
      method: 'POST',
      body: JSON.stringify({
        rating,
      }),
    },
  )
}
