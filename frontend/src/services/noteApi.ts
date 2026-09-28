import {
  api,
} from './api'

import type {
  Note,
  NoteSummary,
  NoteType,
} from '../types/domain'

export type NoteScope =
  | 'active'
  | 'archived'
  | 'trash'

export interface NoteListInput {
  scope: NoteScope
  q?: string
  projectId?: number | null
}

export interface NoteCreateInput {
  title: string
  content: string
  noteType?: NoteType
  isPinned: boolean
  color: string
  tagIds: number[]
  projectId: number | null
}

export interface NoteUpdateInput {
  title?: string
  content?: string
  isPinned?: boolean
  color?: string
  tagIds?: number[]
  projectId?: number | null
}

export async function getNotes(
  input: NoteListInput,
): Promise<NoteSummary[]> {
  const params =
    new URLSearchParams({
      scope: input.scope,
    })

  if (input.q?.trim()) {
    params.set(
      'q',
      input.q.trim(),
    )
  }

  if (
    input.projectId !== null
    && input.projectId !== undefined
  ) {
    params.set(
      'projectId',
      String(input.projectId),
    )
  }

  const response =
    await api<{
      notes: NoteSummary[]
    }>(
      `/api/notes?${params}`,
    )

  return response.notes
}

export async function getNote(
  noteId: number,
): Promise<Note> {
  return api<Note>(
    `/api/notes/${noteId}`,
  )
}

export async function createNote(
  input: NoteCreateInput,
): Promise<Note> {
  return api<Note>(
    '/api/notes',
    {
      method: 'POST',
      body: JSON.stringify(input),
    },
  )
}

export async function updateNote(
  noteId: number,
  input: NoteUpdateInput,
): Promise<Note> {
  return api<Note>(
    `/api/notes/${noteId}`,
    {
      method: 'PUT',
      body: JSON.stringify(input),
    },
  )
}

export async function duplicateNote(
  noteId: number,
): Promise<Note> {
  return api<Note>(
    `/api/notes/${noteId}/duplicate`,
    {
      method: 'POST',
    },
  )
}

export async function archiveNote(
  noteId: number,
): Promise<Note> {
  return api<Note>(
    `/api/notes/${noteId}/archive`,
    {
      method: 'POST',
    },
  )
}

export async function unarchiveNote(
  noteId: number,
): Promise<Note> {
  return api<Note>(
    `/api/notes/${noteId}/unarchive`,
    {
      method: 'POST',
    },
  )
}

export async function restoreNote(
  noteId: number,
): Promise<Note> {
  return api<Note>(
    `/api/notes/${noteId}/restore`,
    {
      method: 'POST',
    },
  )
}

export async function deleteNote(
  noteId: number,
): Promise<void> {
  await api<void>(
    `/api/notes/${noteId}`,
    {
      method: 'DELETE',
    },
  )
}
