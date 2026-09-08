import type { Task } from './domain'

export interface ChannelNoteSummary {
  id: number
  title: string
  content: string
  version: number
  createdByUserId: number | null
  createdByEmail: string | null
  createdAt: string
  updatedAt: string
  archivedAt: string | null
  deletedAt: string | null
  taskCount: number
  completedTaskCount: number
}

export interface ChannelNote
  extends Omit<
    ChannelNoteSummary,
    'taskCount' | 'completedTaskCount'
  > {
  tasks: Task[]
}
