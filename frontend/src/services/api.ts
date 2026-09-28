import {
  useToast,
} from '../composables/useToast'

let csrfToken: string | null = null

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code: string | null = null,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

export function setCsrfToken(
  token: string | null,
): void {
  csrfToken = token
}

export function getCsrfToken(): string | null {
  return csrfToken
}

function showAndThrow(
  error: ApiError,
): never {
  useToast().error(
    error.message,
  )

  throw error
}

function errorPayload(
  value: unknown,
): {
  error?: string
  code?: string
} {
  if (
    value === null
    || typeof value !== 'object'
    || Array.isArray(value)
  ) {
    return {}
  }

  const record =
    value as Record<
      string,
      unknown
    >

  return {
    error:
      typeof record.error === 'string'
        ? record.error
        : undefined,

    code:
      typeof record.code === 'string'
        ? record.code
        : undefined,
  }
}

export async function api<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const method = (
    init.method ?? 'GET'
  ).toUpperCase()

  const headers =
    new Headers(
      init.headers,
    )

  headers.set(
    'Accept',
    'application/json',
  )

  const isFormData =
    typeof FormData !== 'undefined'
    && init.body instanceof FormData

  if (
    init.body !== undefined
    && !isFormData
    && !headers.has('Content-Type')
  ) {
    headers.set(
      'Content-Type',
      'application/json',
    )
  }

  if (
    ![
      'GET',
      'HEAD',
      'OPTIONS',
    ].includes(method)
    && csrfToken
  ) {
    headers.set(
      'X-CSRF-TOKEN',
      csrfToken,
    )
  }

  let response: Response

  try {
    response =
      await fetch(
        path,
        {
          ...init,
          headers,
          credentials: 'same-origin',
        },
      )
  } catch {
    return showAndThrow(
      new ApiError(
        'Impossible de contacter le serveur.',
        0,
        'NETWORK_ERROR',
      ),
    )
  }

  if (response.status === 204) {
    return undefined as T
  }

  let data: unknown

  try {
    data =
      await response.json()
  } catch {
    if (response.ok) {
      return showAndThrow(
        new ApiError(
          'Le serveur a renvoyé une réponse invalide.',
          response.status,
          'INVALID_API_RESPONSE',
        ),
      )
    }

    data = {}
  }

  if (!response.ok) {
    const payload =
      errorPayload(
        data,
      )

    if (
      payload.code
      === 'USER_AUTH_REQUIRED'
      && typeof window !== 'undefined'
    ) {
      window.dispatchEvent(
        new CustomEvent(
          'homeen:user-auth-required',
        ),
      )
    }

    return showAndThrow(
      new ApiError(
        payload.error
          ?? `Request failed with HTTP ${response.status}.`,
        response.status,
        payload.code ?? null,
      ),
    )
  }

  return data as T
}
