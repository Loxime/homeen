import {
  api,
} from './api'

export interface ProfileEmail {
  id: number
  email: string
  isPrimary: boolean
}

export interface Profile {
  id: number
  primaryEmail: string
  notificationSoundEnabled: boolean
  emails: ProfileEmail[]
}

export async function getProfile():
Promise<Profile> {
  return api<Profile>(
    '/api/profile',
  )
}

export async function addProfileEmail(
  email: string,
): Promise<void> {
  await api<void>(
    '/api/profile/emails',
    {
      method: 'POST',
      body: JSON.stringify({
        email,
      }),
    },
  )
}

export async function removeProfileEmail(
  emailId: number,
): Promise<void> {
  await api<void>(
    `/api/profile/emails/${emailId}`,
    {
      method: 'DELETE',
    },
  )
}

export async function changeProfilePassword(
  currentPassword: string,
  password: string,
  confirmation: string,
): Promise<void> {
  await api<void>(
    '/api/profile/password',
    {
      method: 'POST',
      body: JSON.stringify({
        currentPassword,
        password,
        confirmation,
      }),
    },
  )
}

export async function deleteProfile(
  password: string,
): Promise<void> {
  await api<void>(
    '/api/profile',
    {
      method: 'DELETE',
      body: JSON.stringify({
        password,
      }),
    },
  )
}
