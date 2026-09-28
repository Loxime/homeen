import {
  api,
} from './api'

export interface AccessStatus {
  email: string | null
}

export async function getAccessStatus():
Promise<AccessStatus> {
  return api<AccessStatus>(
    '/api/access/status',
  )
}
