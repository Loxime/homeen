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
  addProfileEmail,
  changeProfilePassword,
  deleteProfile,
  getProfile,
  removeProfileEmail,
} from '../profileApi'

describe('profileApi', () => {
  beforeEach(() => {
    mocks.api.mockReset()
  })

  it('loads and manages the profile', async () => {
    mocks.api
      .mockResolvedValueOnce({
        id: 1,
      })
      .mockResolvedValue(undefined)

    await getProfile()

    await addProfileEmail(
      'user@example.test',
    )

    await removeProfileEmail(4)

    await changeProfilePassword(
      'old-password',
      'new-password',
      'new-password',
    )

    await deleteProfile(
      'new-password',
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      1,
      '/api/profile',
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/profile/emails',
      {
        method: 'POST',
        body: JSON.stringify({
          email:
            'user@example.test',
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      3,
      '/api/profile/emails/4',
      {
        method: 'DELETE',
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      4,
      '/api/profile/password',
      {
        method: 'POST',
        body: JSON.stringify({
          currentPassword:
            'old-password',
          password:
            'new-password',
          confirmation:
            'new-password',
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      5,
      '/api/profile',
      {
        method: 'DELETE',
        body: JSON.stringify({
          password:
            'new-password',
        }),
      },
    )
  })
})
