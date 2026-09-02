import { reactive } from 'vue'
import { api, setCsrfToken } from '../services/api'

interface AccessState {
  loading: boolean
  authenticated: boolean
  error: string | null
}

const state = reactive<AccessState>({
  loading: true,
  authenticated: false,
  error: null,
})

let initialized = false

window.addEventListener('homeen:unauthorized', () => {
  state.authenticated = false
  setCsrfToken(null)
})

export function useAccess() {
  async function initialize(): Promise<void> {
    if (initialized) return
    initialized = true
    state.loading = true
    try {
      const response = await api<{ authenticated: boolean; csrfToken: string | null }>('/api/access/status')
      state.authenticated = response.authenticated
      setCsrfToken(response.csrfToken)
    } catch (error) {
      state.error = error instanceof Error ? error.message : 'Unable to check access.'
      state.authenticated = false
    } finally {
      state.loading = false
    }
  }

  async function login(accessKey: string): Promise<void> {
    state.error = null
    const response = await api<{ authenticated: boolean; csrfToken: string }>('/api/access/login', {
      method: 'POST',
      body: JSON.stringify({ accessKey }),
    })
    state.authenticated = response.authenticated
    setCsrfToken(response.csrfToken)
  }

  async function logout(): Promise<void> {
    await api('/api/access/logout', { method: 'POST' })
    setCsrfToken(null)
    state.authenticated = false
  }

  return { state, initialize, login, logout }
}
