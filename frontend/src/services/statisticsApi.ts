import {
  api,
} from './api'

import type {
  StatisticsResponse,
} from '../types/domain'

export interface StatisticsQuery {
  start: string
  end: string
  compareStart: string
  compareEnd: string
}

export async function getStatistics(
  input: StatisticsQuery,
): Promise<StatisticsResponse> {
  const params =
    new URLSearchParams({
      start: input.start,
      end: input.end,
      compareStart:
        input.compareStart,
      compareEnd:
        input.compareEnd,
    })

  return api<StatisticsResponse>(
    `/api/statistics?${params}`,
  )
}
