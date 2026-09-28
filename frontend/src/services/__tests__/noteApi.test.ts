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
  getNote,
} from '../noteApi'

describe('noteApi', () => {
  beforeEach(() => {
    mocks.api.mockReset()
  })

  it('loads a note', async () => {
    mocks.api.mockResolvedValue({
      id: 8,
    })

    await getNote(8)

    expect(
      mocks.api,
    ).toHaveBeenCalledWith(
      '/api/notes/8',
    )
  })
})
