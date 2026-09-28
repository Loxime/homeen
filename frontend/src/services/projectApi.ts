import {
  api,
} from './api'

import type {
  Project,
  ProjectRole,
  ProjectTask,
  ProjectWorkflowStage,
  TaskPriority,
  TaskStatus,
} from '../types/domain'

export interface ProjectCreateInput {
  name: string
  description: string
  color: string
}

export interface ProjectInvitation {
  id: number
  projectId: number
  name: string
  description: string
  color: string
  invitedByEmail: string | null
  createdAt: string
}

export interface ProjectMember {
  userId: number
  email: string
  role: ProjectRole
  joinedAt: string
}

export interface ProjectInvitee {
  email: string
}

export interface ProjectUpdateInput {
  name?: string
  description?: string
  color?: string
}

export interface ProjectTaskCreateInput {
  title: string
  description?: string
  priority?: TaskPriority
  status?: TaskStatus
  workflowStageId: number
  position?: number | null
  tagIds?: number[]
  startDate?: string | null
  dueDate?: string | null
}

export interface ProjectTaskUpdateInput {
  title?: string
  description?: string
  priority?: TaskPriority
  status?: TaskStatus
  workflowStageId?: number
  position?: number | null
  tagIds?: number[]
  startDate?: string | null
  dueDate?: string | null
}

export interface ProjectTaskOrderColumn {
  workflowStageId: number
  taskIds: number[]
}

export async function getProjects():
Promise<Project[]> {
  const response =
    await api<{
      projects: Project[]
    }>(
      '/api/projects',
    )

  return response.projects
}

export async function createProject(
  input: ProjectCreateInput,
): Promise<Project> {
  return api<Project>(
    '/api/projects',
    {
      method: 'POST',
      body: JSON.stringify(input),
    },
  )
}

export async function deleteProject(
  projectId: number,
): Promise<void> {
  await api<void>(
    `/api/projects/${projectId}`,
    {
      method: 'DELETE',
    },
  )
}

export async function getPendingProjectInvitations():
Promise<ProjectInvitation[]> {
  const response =
    await api<{
      invitations: ProjectInvitation[]
    }>(
      '/api/project-invitations',
    )

  return response.invitations
}

export async function lookupProjectInvitee(
  projectId: number,
  email: string,
): Promise<ProjectInvitee> {
  return api<ProjectInvitee>(
    `/api/projects/${projectId}/invitees/lookup`,
    {
      method: 'POST',
      body: JSON.stringify({
        email,
      }),
    },
  )
}

export async function inviteToProject(
  projectId: number,
  email: string,
): Promise<void> {
  await api<void>(
    `/api/projects/${projectId}/invitations`,
    {
      method: 'POST',
      body: JSON.stringify({
        email,
      }),
    },
  )
}

export async function acceptProjectInvitation(
  invitationId: number,
): Promise<void> {
  await api<void>(
    `/api/project-invitations/${invitationId}/accept`,
    {
      method: 'POST',
    },
  )
}

export async function rejectProjectInvitation(
  invitationId: number,
): Promise<void> {
  await api<void>(
    `/api/project-invitations/${invitationId}`,
    {
      method: 'DELETE',
    },
  )
}

export async function getProjectMembers(
  projectId: number,
): Promise<ProjectMember[]> {
  const response =
    await api<{
      members: ProjectMember[]
    }>(
      `/api/projects/${projectId}/members`,
    )

  return response.members
}

export async function setProjectMemberRole(
  projectId: number,
  userId: number,
  role: 'admin' | 'member',
): Promise<void> {
  await api<void>(
    `/api/projects/${projectId}/members/${userId}/role`,
    {
      method: 'PUT',
      body: JSON.stringify({
        role,
      }),
    },
  )
}

export async function removeProjectMember(
  projectId: number,
  userId: number,
): Promise<void> {
  await api<void>(
    `/api/projects/${projectId}/members/${userId}`,
    {
      method: 'DELETE',
    },
  )
}

export async function leaveProject(
  projectId: number,
): Promise<void> {
  await api<void>(
    `/api/projects/${projectId}/leave`,
    {
      method: 'POST',
    },
  )
}

export async function getProject(
  projectId: number,
): Promise<Project> {
  return api<Project>(
    `/api/projects/${projectId}`,
  )
}

export async function updateProject(
  projectId: number,
  input: ProjectUpdateInput,
): Promise<Project> {
  return api<Project>(
    `/api/projects/${projectId}`,
    {
      method: 'PUT',
      body: JSON.stringify(input),
    },
  )
}

export async function getProjectWorkflow(
  projectId: number,
): Promise<ProjectWorkflowStage[]> {
  const response =
    await api<{
      stages: ProjectWorkflowStage[]
    }>(
      `/api/projects/${projectId}/workflow`,
    )

  return response.stages
}

export async function createWorkflowStage(
  projectId: number,
  name: string,
): Promise<ProjectWorkflowStage> {
  return api<ProjectWorkflowStage>(
    `/api/projects/${projectId}/workflow/stages`,
    {
      method: 'POST',
      body: JSON.stringify({
        name,
      }),
    },
  )
}

export async function renameWorkflowStage(
  projectId: number,
  stageId: number,
  name: string,
): Promise<ProjectWorkflowStage> {
  return api<ProjectWorkflowStage>(
    `/api/projects/${projectId}/workflow/stages/${stageId}`,
    {
      method: 'PUT',
      body: JSON.stringify({
        name,
      }),
    },
  )
}

export async function deleteWorkflowStage(
  projectId: number,
  stageId: number,
): Promise<void> {
  await api<void>(
    `/api/projects/${projectId}/workflow/stages/${stageId}`,
    {
      method: 'DELETE',
    },
  )
}

export async function reorderProjectWorkflow(
  projectId: number,
  stageIds: number[],
): Promise<ProjectWorkflowStage[]> {
  const response =
    await api<{
      stages: ProjectWorkflowStage[]
    }>(
      `/api/projects/${projectId}/workflow/order`,
      {
        method: 'PUT',
        body: JSON.stringify({
          stageIds,
        }),
      },
    )

  return response.stages
}

export async function getProjectTask(
  projectId: number,
  taskId: number,
): Promise<ProjectTask> {
  return api<ProjectTask>(
    `/api/projects/${projectId}/tasks/${taskId}`,
  )
}

export async function getProjectTasks(
  projectId: number,
): Promise<ProjectTask[]> {
  const response =
    await api<{
      tasks: ProjectTask[]
    }>(
      `/api/projects/${projectId}/tasks`,
    )

  return response.tasks
}

export async function createProjectTask(
  projectId: number,
  input: ProjectTaskCreateInput,
): Promise<ProjectTask> {
  return api<ProjectTask>(
    `/api/projects/${projectId}/tasks`,
    {
      method: 'POST',
      body: JSON.stringify(input),
    },
  )
}

export async function updateProjectTask(
  projectId: number,
  taskId: number,
  input: ProjectTaskUpdateInput,
): Promise<ProjectTask> {
  return api<ProjectTask>(
    `/api/projects/${projectId}/tasks/${taskId}`,
    {
      method: 'PUT',
      body: JSON.stringify(input),
    },
  )
}

export async function setProjectTaskCompleted(
  projectId: number,
  taskId: number,
  completed: boolean,
): Promise<ProjectTask> {
  return api<ProjectTask>(
    `/api/projects/${projectId}/tasks/${taskId}/completed`,
    {
      method: 'PUT',
      body: JSON.stringify({
        completed,
      }),
    },
  )
}

export async function deleteProjectTask(
  projectId: number,
  taskId: number,
): Promise<void> {
  await api<void>(
    `/api/projects/${projectId}/tasks/${taskId}`,
    {
      method: 'DELETE',
    },
  )
}

export async function reorderProjectTasks(
  projectId: number,
  columns: ProjectTaskOrderColumn[],
): Promise<ProjectTask[]> {
  const response =
    await api<{
      tasks: ProjectTask[]
    }>(
      `/api/projects/${projectId}/tasks/order`,
      {
        method: 'PUT',
        body: JSON.stringify({
          columns,
        }),
      },
    )

  return response.tasks
}
