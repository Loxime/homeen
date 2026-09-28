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
  createProjectTask,
  createWorkflowStage,
  deleteProjectTask,
  deleteWorkflowStage,
  getProject,
  getProjectTask,
  getProjectTasks,
  getProjectWorkflow,
  renameWorkflowStage,
  reorderProjectTasks,
  reorderProjectWorkflow,
  setProjectTaskCompleted,
  updateProject,
  updateProjectTask,
} from '../projectApi'

describe('projectApi', () => {
  beforeEach(() => {
    mocks.api.mockReset()
  })

  it('loads project resources', async () => {
    mocks.api
      .mockResolvedValueOnce({
        id: 7,
      })
      .mockResolvedValueOnce({
        stages: [
          {
            id: 11,
          },
        ],
      })
      .mockResolvedValueOnce({
        tasks: [
          {
            id: 21,
          },
        ],
      })

    await expect(
      getProject(7),
    ).resolves.toEqual({
      id: 7,
    })

    await expect(
      getProjectWorkflow(7),
    ).resolves.toEqual([
      {
        id: 11,
      },
    ])

    await expect(
      getProjectTasks(7),
    ).resolves.toEqual([
      {
        id: 21,
      },
    ])

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      1,
      '/api/projects/7',
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/projects/7/workflow',
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      3,
      '/api/projects/7/tasks',
    )
  })

  it('loads a project task', async () => {
    mocks.api.mockResolvedValue({
      id: 21,
    })

    await expect(
      getProjectTask(
        7,
        21,
      ),
    ).resolves.toEqual({
      id: 21,
    })

    expect(
      mocks.api,
    ).toHaveBeenCalledWith(
      '/api/projects/7/tasks/21',
    )
  })

  it('updates a project', async () => {
    mocks.api.mockResolvedValue({
      id: 7,
    })

    await updateProject(
      7,
      {
        name: 'Harpocrate',
        description: 'Description',
        color: '#123456',
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenCalledWith(
      '/api/projects/7',
      {
        method: 'PUT',
        body: JSON.stringify({
          name: 'Harpocrate',
          description: 'Description',
          color: '#123456',
        }),
      },
    )
  })

  it('manages workflow stages', async () => {
    mocks.api
      .mockResolvedValueOnce({
        id: 3,
      })
      .mockResolvedValueOnce({
        id: 3,
      })
      .mockResolvedValueOnce(undefined)
      .mockResolvedValueOnce({
        stages: [],
      })

    await createWorkflowStage(
      7,
      'À faire',
    )

    await renameWorkflowStage(
      7,
      3,
      'En cours',
    )

    await deleteWorkflowStage(
      7,
      3,
    )

    await expect(
      reorderProjectWorkflow(
        7,
        [
          3,
          1,
          2,
        ],
      ),
    ).resolves.toEqual([])

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      1,
      '/api/projects/7/workflow/stages',
      {
        method: 'POST',
        body: JSON.stringify({
          name: 'À faire',
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/projects/7/workflow/stages/3',
      {
        method: 'PUT',
        body: JSON.stringify({
          name: 'En cours',
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      3,
      '/api/projects/7/workflow/stages/3',
      {
        method: 'DELETE',
      },
    )
  })

  it('manages project tasks', async () => {
    mocks.api
      .mockResolvedValueOnce({
        id: 21,
      })
      .mockResolvedValueOnce({
        id: 21,
      })
      .mockResolvedValueOnce({
        id: 21,
      })
      .mockResolvedValueOnce(undefined)
      .mockResolvedValueOnce({
        tasks: [],
      })

    await createProjectTask(
      7,
      {
        title: 'Issue',
        description: 'Details',
        workflowStageId: 3,
      },
    )

    await updateProjectTask(
      7,
      21,
      {
        workflowStageId: 4,
      },
    )

    await setProjectTaskCompleted(
      7,
      21,
      true,
    )

    await deleteProjectTask(
      7,
      21,
    )

    await expect(
      reorderProjectTasks(
        7,
        [
          {
            workflowStageId: 3,
            taskIds: [
              21,
            ],
          },
        ],
      ),
    ).resolves.toEqual([])

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      1,
      '/api/projects/7/tasks',
      {
        method: 'POST',
        body: JSON.stringify({
          title: 'Issue',
          description: 'Details',
          workflowStageId: 3,
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/projects/7/tasks/21',
      {
        method: 'PUT',
        body: JSON.stringify({
          workflowStageId: 4,
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      3,
      '/api/projects/7/tasks/21/completed',
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
      4,
      '/api/projects/7/tasks/21',
      {
        method: 'DELETE',
      },
    )
  })
})
