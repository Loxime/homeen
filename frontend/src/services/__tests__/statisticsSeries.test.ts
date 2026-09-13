import {
  describe,
  expect,
  it,
} from 'vitest'

import {
  buildStatisticsSeries,
} from '../statisticsSeries'

import type {
  DailyStatistic,
} from '../../types/domain'

function day(
  date: string,
  focusSeconds = 60,
): DailyStatistic {
  return {
    date,
    pomodoroSessions: 1,
    focusSeconds,
    breakSeconds: 0,
    tasksCompleted: 1,
    notesCreated: 0,
    activeAppSeconds: 30,
  }
}

describe(
  'buildStatisticsSeries',
  () => {
    it(
      'keeps short periods daily',
      () => {
        const result =
          buildStatisticsSeries([
            day('2026-09-01'),
            day('2026-09-02'),
          ])

        expect(result).toHaveLength(2)
        expect(result[0]?.key)
          .toBe('2026-09-01')
      },
    )

    it(
      'groups long periods by month',
      () => {
        const days:
          DailyStatistic[] = []

        for (
          let index = 0;
          index < 91;
          index += 1
        ) {
          const date =
            new Date(
              Date.UTC(
                2026,
                0,
                1 + index,
              ),
            )
              .toISOString()
              .slice(0, 10)

          days.push(
            day(
              date,
              120,
            ),
          )
        }

        const result =
          buildStatisticsSeries(
            days,
          )

        expect(
          result.length,
        ).toBeLessThan(91)

        expect(
          result.reduce(
            (
              total,
              point,
            ) =>
              total
              + point.focusSeconds,
            0,
          ),
        ).toBe(
          91 * 120,
        )
      },
    )
  },
)
