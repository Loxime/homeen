import {
  beforeEach,
  describe,
  expect,
  it,
  vi,
} from 'vitest'

const mocks =
  vi.hoisted(() => ({
    api: vi.fn(),
  }))

vi.mock('../api', () => ({
  api: mocks.api,
}))

import {
  getPomodoroActive,
  getPomodoroHistory,
  getPomodoroInsights,
  getPomodoroPresets,
  ratePomodoroSession,
  startPomodoroSession,
  stopPomodoroSession,
} from '../pomodoroApi'

describe('pomodoroApi', () => {
  beforeEach(() => {
    mocks.api.mockReset()
  })

  it('loads pomodoro resources', async () => {
    mocks.api
      .mockResolvedValueOnce({
        session: {
          id: 1,
        },
      })
      .mockResolvedValueOnce({
        presets: [
          {
            id: 2,
          },
        ],
      })
      .mockResolvedValueOnce({
        sessions: [],
        pagination: {
          page: 1,
        },
      })
      .mockResolvedValueOnce({
        ratingCount: 3,
      })

    await expect(
      getPomodoroActive(),
    ).resolves.toEqual({
      id: 1,
    })

    await expect(
      getPomodoroPresets(),
    ).resolves.toEqual([
      {
        id: 2,
      },
    ])

    await getPomodoroHistory(2)
    await getPomodoroInsights()

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      3,
      '/api/pomodoro/history?page=2',
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      4,
      '/api/pomodoro/insights',
    )
  })

  it('manages pomodoro sessions', async () => {
    mocks.api.mockResolvedValue({
      id: 9,
    })

    await startPomodoroSession(25)
    await stopPomodoroSession(9)
    await ratePomodoroSession(
      9,
      3,
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      1,
      '/api/pomodoro/sessions',
      {
        method: 'POST',
        body: JSON.stringify({
          workMinutes: 25,
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/pomodoro/sessions/9/stop',
      {
        method: 'POST',
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      3,
      '/api/pomodoro/sessions/9/rating',
      {
        method: 'POST',
        body: JSON.stringify({
          rating: 3,
        }),
      },
    )
  })
})
