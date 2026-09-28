import {
  api,
} from './api'

import type {
  Task,
  TaskPriority,
  TaskStatus,
} from '../types/domain'

export interface TaskUpdateInput {
  title?: string
  description?: string
  priority?: TaskPriority
  status?: TaskStatus
  startDate?: string | null
  dueDate?: string | null
}

export async function getTask(
  taskId: number,
): Promise<Task> {
  return api<Task>(
    `/api/tasks/${taskId}`,
  )
}

export async function updateTask(
  taskId: number,
  input: TaskUpdateInput,
): Promise<Task> {
  return api<Task>(
    `/api/tasks/${taskId}`,
    {
      method: 'PUT',
      body: JSON.stringify(input),
    },
  )
}

export async function setTaskCompleted(
  taskId: number,
  completed: boolean,
): Promise<Task> {
  return api<Task>(
    `/api/tasks/${taskId}/completed`,
    {
      method: 'PUT',
      body: JSON.stringify({
        completed,
      }),
    },
  )
}

export async function deleteTask(
  taskId: number,
): Promise<void> {
  await api<void>(
    `/api/tasks/${taskId}`,
    {
      method: 'DELETE',
    },
  )
}
