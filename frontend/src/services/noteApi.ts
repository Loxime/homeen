import {
  api,
} from './api'

import type {
  Note,
} from '../types/domain'

export async function getNote(
  noteId: number,
): Promise<Note> {
  return api<Note>(
    `/api/notes/${noteId}`,
  )
}
