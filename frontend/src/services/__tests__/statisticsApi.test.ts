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
  getStatistics,
} from '../statisticsApi'

describe('statisticsApi', () => {
  beforeEach(() => {
    mocks.api.mockReset()
  })

  it('loads comparison statistics', async () => {
    mocks.api.mockResolvedValue({
      range: {},
    })

    await getStatistics({
      start: '2026-09-01',
      end: '2026-09-07',
      compareStart: '2026-08-25',
      compareEnd: '2026-08-31',
    })

    expect(
      mocks.api,
    ).toHaveBeenCalledWith(
      '/api/statistics?start=2026-09-01&end=2026-09-07&compareStart=2026-08-25&compareEnd=2026-08-31',
    )
  })
})
