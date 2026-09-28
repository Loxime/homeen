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
  acceptProjectInvitation,
  createProject,
  deleteProject,
  getPendingProjectInvitations,
  getProjectMembers,
  getProjects,
  inviteToProject,
  leaveProject,
  lookupProjectInvitee,
  rejectProjectInvitation,
  removeProjectMember,
  setProjectMemberRole,
} from '../projectApi'

describe('project management API', () => {
  beforeEach(() => {
    mocks.api.mockReset()
  })

  it('lists, creates and deletes projects', async () => {
    mocks.api
      .mockResolvedValueOnce({
        projects: [
          {
            id: 4,
          },
        ],
      })
      .mockResolvedValueOnce({
        id: 5,
      })
      .mockResolvedValueOnce(undefined)

    await expect(
      getProjects(),
    ).resolves.toEqual([
      {
        id: 4,
      },
    ])

    await createProject({
      name: 'Harpocrate',
      description: 'Projet',
      color: '#1A73E8',
    })

    await deleteProject(5)

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      1,
      '/api/projects',
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/projects',
      {
        method: 'POST',
        body: JSON.stringify({
          name: 'Harpocrate',
          description: 'Projet',
          color: '#1A73E8',
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      3,
      '/api/projects/5',
      {
        method: 'DELETE',
      },
    )
  })

  it('manages invitations', async () => {
    mocks.api
      .mockResolvedValueOnce({
        invitations: [
          {
            id: 8,
          },
        ],
      })
      .mockResolvedValueOnce({
        email: 'user@example.test',
      })
      .mockResolvedValueOnce(undefined)
      .mockResolvedValueOnce(undefined)
      .mockResolvedValueOnce(undefined)

    await expect(
      getPendingProjectInvitations(),
    ).resolves.toEqual([
      {
        id: 8,
      },
    ])

    await expect(
      lookupProjectInvitee(
        4,
        'user@example.test',
      ),
    ).resolves.toEqual({
      email: 'user@example.test',
    })

    await inviteToProject(
      4,
      'user@example.test',
    )

    await acceptProjectInvitation(8)
    await rejectProjectInvitation(9)

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/projects/4/invitees/lookup',
      {
        method: 'POST',
        body: JSON.stringify({
          email: 'user@example.test',
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      3,
      '/api/projects/4/invitations',
      {
        method: 'POST',
        body: JSON.stringify({
          email: 'user@example.test',
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      4,
      '/api/project-invitations/8/accept',
      {
        method: 'POST',
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      5,
      '/api/project-invitations/9',
      {
        method: 'DELETE',
      },
    )
  })

  it('manages project members', async () => {
    mocks.api
      .mockResolvedValueOnce({
        members: [
          {
            userId: 12,
          },
        ],
      })
      .mockResolvedValueOnce(undefined)
      .mockResolvedValueOnce(undefined)
      .mockResolvedValueOnce(undefined)

    await expect(
      getProjectMembers(4),
    ).resolves.toEqual([
      {
        userId: 12,
      },
    ])

    await setProjectMemberRole(
      4,
      12,
      'admin',
    )

    await removeProjectMember(
      4,
      12,
    )

    await leaveProject(4)

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      2,
      '/api/projects/4/members/12/role',
      {
        method: 'PUT',
        body: JSON.stringify({
          role: 'admin',
        }),
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      3,
      '/api/projects/4/members/12',
      {
        method: 'DELETE',
      },
    )

    expect(
      mocks.api,
    ).toHaveBeenNthCalledWith(
      4,
      '/api/projects/4/leave',
      {
        method: 'POST',
      },
    )
  })
})
