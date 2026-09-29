import {
  api,
} from './api'

import type {
  TaskPriority,
  TaskStatus,
} from '../types/domain'

export interface SearchNoteResult {
  id: number
  title: string
  content: string
  archivedAt: string | null
  updatedAt: string
}

export interface SearchTaskResult {
  id: number
  noteId: number
  noteTitle: string
  content: string
  priority: TaskPriority
  status: TaskStatus
  startDate: string | null
  dueDate: string | null
  noteArchivedAt: string | null
  updatedAt: string
}

export interface SearchResponse {
  notes: SearchNoteResult[]
  tasks: SearchTaskResult[]
}

export async function search(
  query: string,
): Promise<SearchResponse> {
  const params =
    new URLSearchParams({
      q: query,
    })

  return api<SearchResponse>(
    `/api/search?${params}`,
  )
}
