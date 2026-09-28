import { reactive } from 'vue'

import {
  api,
  getCsrfToken,
  setCsrfToken,
} from '../services/api'

interface AccessStatusResponse {
  userAuthenticated: boolean
  mustChangePassword: boolean
  authenticated: boolean
  email: string | null
  csrfToken: string
}

interface UserLoginResponse {
  userAuthenticated: boolean
  mustChangePassword: boolean
  authenticated: boolean
  email: string
}

interface PasswordChangeResponse
  extends UserLoginResponse {
  csrfToken: string
}

interface AccessState {
  loading: boolean
  userAuthenticated: boolean
  mustChangePassword: boolean
  authenticated: boolean
  email: string | null
  error: string | null
}

const state =
  reactive<AccessState>({
    loading: true,
    userAuthenticated: false,
    mustChangePassword: false,
    authenticated: false,
    email: null,
    error: null,
  })

let initialized = false

function resetUser(): void {
  state.userAuthenticated = false
  state.mustChangePassword = false
  state.authenticated = false
  state.email = null

  setCsrfToken(null)
}

async function fetchAccessStatus():
Promise<AccessStatusResponse> {
  return api<AccessStatusResponse>(
    '/api/access/status',
  )
}

async function ensureCsrfToken():
Promise<void> {
  if (getCsrfToken()) {
    return
  }

  const response =
    await fetchAccessStatus()

  setCsrfToken(
    response.csrfToken,
  )
}

window.addEventListener(
  'homeen:user-auth-required',
  resetUser,
)

export function useAccess() {
  async function initialize(): Promise<void> {
    if (initialized) {
      return
    }

    initialized = true
    state.loading = true
    state.error = null

    try {
      const response =
        await fetchAccessStatus()

      state.userAuthenticated =
        response.userAuthenticated

      state.mustChangePassword =
        response.mustChangePassword

      state.authenticated =
        response.authenticated

      state.email =
        response.email

      setCsrfToken(
        response.csrfToken,
      )
    } catch (error) {
      state.error =
        error instanceof Error
          ? error.message
          : 'Impossible de vérifier la session.'

      resetUser()
      initialized = false
    } finally {
      state.loading = false
    }
  }

  async function loginUser(
    email: string,
    password: string,
  ): Promise<void> {
    state.error = null

    /*
     * Logout invalidates the Symfony session,
     * therefore the previous CSRF token becomes
     * invalid as well. Always reacquire one when
     * the client no longer has a valid token
     * before posting credentials.
     */
    await ensureCsrfToken()

    const response =
      await api<UserLoginResponse>(
        '/api/auth/login',
        {
          method: 'POST',

          body: JSON.stringify({
            email,
            password,
          }),
        },
      )

    state.userAuthenticated =
      response.userAuthenticated

    state.mustChangePassword =
      response.mustChangePassword

    state.authenticated =
      response.authenticated

    state.email =
      response.email
  }

  async function changeTemporaryPassword(
    password: string,
    confirmation: string,
  ): Promise<void> {
    state.error = null

    const response =
      await api<PasswordChangeResponse>(
        '/api/auth/change-temporary-password',
        {
          method: 'POST',

          body: JSON.stringify({
            password,
            confirmation,
          }),
        },
      )

    state.userAuthenticated =
      response.userAuthenticated

    state.mustChangePassword =
      response.mustChangePassword

    state.authenticated =
      response.authenticated

    state.email =
      response.email

    setCsrfToken(
      response.csrfToken,
    )
  }

  async function logout(): Promise<void> {
    await api(
      '/api/access/logout',
      {
        method: 'POST',
      },
    )

    /*
     * Symfony invalidates the current session
     * during logout. The old CSRF token must not
     * survive that boundary.
     */
    resetUser()

    /*
     * Prepare the anonymous session immediately
     * so the next login works without a reload.
     * If this refresh fails, loginUser() retries
     * lazily through ensureCsrfToken().
     */
    try {
      await ensureCsrfToken()
    } catch {
      setCsrfToken(null)
    }
  }

  return {
    state,
    initialize,
    loginUser,
    changeTemporaryPassword,
    logout,
  }
}
