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
  search,
} from '../searchApi'

describe('searchApi', () => {
  beforeEach(() => {
    mocks.api.mockReset()
  })

  it('searches the application', async () => {
    mocks.api.mockResolvedValue({
      notes: [],
      tasks: [],
    })

    await expect(
      search('harpocrate'),
    ).resolves.toEqual({
      notes: [],
      tasks: [],
    })

    expect(
      mocks.api,
    ).toHaveBeenCalledWith(
      '/api/search?q=harpocrate',
    )
  })
})
