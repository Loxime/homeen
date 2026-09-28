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
  createNoteTask,
  deleteTask,
  getTask,
  setTaskCompleted,
  updateTask,
} from '../taskApi'

describe('taskApi', () => {
  beforeEach(() => {
    mocks.api.mockReset()
  })

  it('creates a note task', async () => {
    mocks.api.mockResolvedValue({
      id: 6,
    })

    await createNoteTask(
      3,
      {
        title: 'Sous-tâche',
        description: '',
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenCalledWith(
      '/api/notes/3/tasks',
      {
        method: 'POST',
        body: JSON.stringify({
          title: 'Sous-tâche',
          description: '',
        }),
      },
    )
  })

  it('loads and updates a task', async () => {
    mocks.api
      .mockResolvedValueOnce({
        id: 4,
      })
      .mockResolvedValueOnce({
        id: 4,
      })

    await getTask(4)

    await updateTask(
      4,
      {
        title: 'Titre',
        description: 'Description',
        priority: 'high',
        status: 'in_progress',
        startDate: null,
        dueDate: '2026-09-30',
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      1,
      '/api/tasks/4',
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/tasks/4',
      {
        method: 'PUT',
        body: JSON.stringify({
          title: 'Titre',
          description: 'Description',
          priority: 'high',
          status: 'in_progress',
          startDate: null,
          dueDate: '2026-09-30',
        }),
      },
    )
  })

  it('completes and deletes a task', async () => {
    mocks.api
      .mockResolvedValueOnce({
        id: 4,
      })
      .mockResolvedValueOnce(undefined)

    await setTaskCompleted(
      4,
      true,
    )

    await deleteTask(4)

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      1,
      '/api/tasks/4/completed',
      {
        method: 'PUT',
        body: JSON.stringify({
          completed: true,
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/tasks/4',
      {
        method: 'DELETE',
      },
    )
  })
})
