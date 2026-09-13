import type {
  DailyStatistic,
} from '../types/domain'

export interface StatisticSeriesPoint {
  key: string
  label: string
  pomodoroSessions: number
  focusSeconds: number
  breakSeconds: number
  tasksCompleted: number
  notesCreated: number
  activeAppSeconds: number
}

function monthLabel(
  key: string,
): string {
  return new Intl.DateTimeFormat(
    'fr-FR',
    {
      month: 'short',
      year: 'numeric',
    },
  ).format(
    new Date(
      `${key}-01T12:00:00`,
    ),
  )
}

export function buildStatisticsSeries(
  days: DailyStatistic[],
): StatisticSeriesPoint[] {
  if (days.length <= 90) {
    return days.map(
      day => ({
        key: day.date,
        label:
          new Intl.DateTimeFormat(
            'fr-FR',
            {
              day: '2-digit',
              month: 'short',
            },
          ).format(
            new Date(
              `${day.date}T12:00:00`,
            ),
          ),

        pomodoroSessions:
          day.pomodoroSessions,

        focusSeconds:
          day.focusSeconds,

        breakSeconds:
          day.breakSeconds,

        tasksCompleted:
          day.tasksCompleted,

        notesCreated:
          day.notesCreated,

        activeAppSeconds:
          day.activeAppSeconds,
      }),
    )
  }

  const grouped =
    new Map<
      string,
      StatisticSeriesPoint
    >()

  for (const day of days) {
    const key =
      day.date.slice(
        0,
        7,
      )

    const current =
      grouped.get(key)
      ?? {
        key,
        label:
          monthLabel(key),
        pomodoroSessions: 0,
        focusSeconds: 0,
        breakSeconds: 0,
        tasksCompleted: 0,
        notesCreated: 0,
        activeAppSeconds: 0,
      }

    current.pomodoroSessions +=
      day.pomodoroSessions

    current.focusSeconds +=
      day.focusSeconds

    current.breakSeconds +=
      day.breakSeconds

    current.tasksCompleted +=
      day.tasksCompleted

    current.notesCreated +=
      day.notesCreated

    current.activeAppSeconds +=
      day.activeAppSeconds

    grouped.set(
      key,
      current,
    )
  }

  return [
    ...grouped.values(),
  ]
}
