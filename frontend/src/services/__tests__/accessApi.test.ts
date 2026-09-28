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

vi.mock(
  '../api',
  () => ({
    api: mocks.api,
  }),
)

import {
  getAccessStatus,
} from '../accessApi'

describe('accessApi', () => {
  beforeEach(() => {
    mocks.api.mockReset()
  })

  it('loads access status', async () => {
    mocks.api.mockResolvedValue({
      email: 'user@example.test',
    })

    await expect(
      getAccessStatus(),
    ).resolves.toEqual({
      email: 'user@example.test',
    })

    expect(
      mocks.api,
    ).toHaveBeenCalledWith(
      '/api/access/status',
    )
  })
})
