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
  archiveNote,
  createNote,
  deleteNote,
  duplicateNote,
  getNote,
  getNotes,
  restoreNote,
  unarchiveNote,
  updateNote,
} from '../noteApi'

describe('noteApi', () => {
  beforeEach(() => {
    mocks.api.mockReset()
  })

  it('lists notes with filters', async () => {
    mocks.api
      .mockResolvedValueOnce({
        notes: [
          {
            id: 1,
          },
        ],
      })
      .mockResolvedValueOnce({
        notes: [],
      })

    await expect(
      getNotes({
        scope: 'active',
        q: '  test  ',
        projectId: 4,
      }),
    ).resolves.toEqual([
      {
        id: 1,
      },
    ])

    await expect(
      getNotes({
        scope: 'archived',
        q: '',
        projectId: null,
      }),
    ).resolves.toEqual([])

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      1,
      '/api/notes?scope=active&q=test&projectId=4',
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/notes?scope=archived',
    )
  })

  it('gets, creates and updates a note', async () => {
    mocks.api
      .mockResolvedValueOnce({
        id: 1,
      })
      .mockResolvedValueOnce({
        id: 2,
      })
      .mockResolvedValueOnce({
        id: 2,
      })

    await getNote(1)

    await createNote({
      title: 'Titre',
      content: 'Contenu',
      noteType: 'text',
      isPinned: false,
      color: '#FFFFFF',
      tagIds: [],
      projectId: null,
    })

    await updateNote(
      2,
      {
        color: '#D3F9D8',
        isPinned: true,
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      1,
      '/api/notes/1',
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/notes',
      {
        method: 'POST',
        body: JSON.stringify({
          title: 'Titre',
          content: 'Contenu',
          noteType: 'text',
          isPinned: false,
          color: '#FFFFFF',
          tagIds: [],
          projectId: null,
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      3,
      '/api/notes/2',
      {
        method: 'PUT',
        body: JSON.stringify({
          color: '#D3F9D8',
          isPinned: true,
        }),
      },
    )
  })

  it('duplicates and changes note lifecycle', async () => {
    mocks.api
      .mockResolvedValueOnce({
        id: 3,
      })
      .mockResolvedValueOnce({
        id: 3,
      })
      .mockResolvedValueOnce({
        id: 3,
      })
      .mockResolvedValueOnce({
        id: 3,
      })
      .mockResolvedValueOnce(undefined)

    await duplicateNote(3)
    await archiveNote(3)
    await unarchiveNote(3)
    await restoreNote(3)
    await deleteNote(3)

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      1,
      '/api/notes/3/duplicate',
      {
        method: 'POST',
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/notes/3/archive',
      {
        method: 'POST',
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      3,
      '/api/notes/3/unarchive',
      {
        method: 'POST',
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      4,
      '/api/notes/3/restore',
      {
        method: 'POST',
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      5,
      '/api/notes/3',
      {
        method: 'DELETE',
      },
    )
  })
})
