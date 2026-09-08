import { reactive } from 'vue'
import {
  api,
  setCsrfToken,
} from '../services/api'

interface AccessStatusResponse {
  accessGranted: boolean
  userAuthenticated: boolean
  mustChangePassword: boolean
  authenticated: boolean
  email: string | null
  csrfToken: string | null
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
  accessGranted: boolean
  userAuthenticated: boolean
  mustChangePassword: boolean
  authenticated: boolean
  email: string | null
  error: string | null
}

const state = reactive<AccessState>({
  loading: true,
  accessGranted: false,
  userAuthenticated: false,
  mustChangePassword: false,
  authenticated: false,
  email: null,
  error: null,
})

let initialized = false

function resetAll(): void {
  state.accessGranted = false
  state.userAuthenticated = false
  state.mustChangePassword = false
  state.authenticated = false
  state.email = null
  setCsrfToken(null)
}

function resetUser(): void {
  state.userAuthenticated = false
  state.mustChangePassword = false
  state.authenticated = false
  state.email = null
}

window.addEventListener(
  'homeen:access-required',
  resetAll,
)

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
        await api<AccessStatusResponse>(
          '/api/access/status',
        )

      state.accessGranted =
        response.accessGranted

      state.userAuthenticated =
        response.userAuthenticated

      state.mustChangePassword =
        response.mustChangePassword

      state.authenticated =
        response.authenticated

      state.email = response.email

      setCsrfToken(
        response.csrfToken,
      )
    } catch (error) {
      state.error =
        error instanceof Error
          ? error.message
          : 'Unable to check access.'

      resetAll()
    } finally {
      state.loading = false
    }
  }

  async function loginAccess(
    accessKey: string,
  ): Promise<void> {
    state.error = null

    const response =
      await api<AccessStatusResponse>(
        '/api/access/login',
        {
          method: 'POST',
          body: JSON.stringify({
            accessKey,
          }),
        },
      )

    state.accessGranted =
      response.accessGranted

    state.userAuthenticated =
      response.userAuthenticated

    state.mustChangePassword =
      response.mustChangePassword

    state.authenticated =
      response.authenticated

    state.email = response.email

    setCsrfToken(
      response.csrfToken,
    )
  }

  async function loginUser(
    email: string,
    password: string,
  ): Promise<void> {
    state.error = null

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

    state.email = response.email
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

    state.email = response.email

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

    resetAll()
  }

  return {
    state,
    initialize,
    loginAccess,
    loginUser,
    changeTemporaryPassword,
    logout,
  }
}
