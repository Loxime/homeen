let csrfToken: string | null = null

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code: string | null = null,
  ) {
    super(message)
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

export async function api<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const method = (
    init.method ?? 'GET'
  ).toUpperCase()

  const headers = new Headers(
    init.headers,
  )

  headers.set(
    'Accept',
    'application/json',
  )

  if (
    init.body !== undefined
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

  const response = await fetch(
    path,
    {
      ...init,
      headers,
      credentials: 'same-origin',
    },
  )

  if (response.status === 204) {
    return undefined as T
  }

  const data = (
    await response
      .json()
      .catch(() => ({}))
  ) as {
    error?: string
    code?: string
  } & T

  if (!response.ok) {
    if (
      data.code === 'ACCESS_REQUIRED'
    ) {
      window.dispatchEvent(
        new CustomEvent(
          'homeen:access-required',
        ),
      )
    }

    if (
      data.code === 'USER_AUTH_REQUIRED'
    ) {
      window.dispatchEvent(
        new CustomEvent(
          'homeen:user-auth-required',
        ),
      )
    }

    throw new ApiError(
      data.error
        ?? `Request failed with HTTP ${response.status}.`,
      response.status,
      data.code ?? null,
    )
  }

  return data
}
