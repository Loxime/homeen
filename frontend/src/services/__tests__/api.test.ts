import {
  afterEach,
  beforeEach,
  describe,
  expect,
  it,
  vi,
} from 'vitest'

const mocks =
  vi.hoisted(() => ({
    toastError:
      vi.fn(),
  }))

vi.mock(
  '../../composables/useToast',
  () => ({
    useToast: () => ({
      error:
        mocks.toastError,
    }),
  }),
)

import {
  ApiError,
  api,
  getCsrfToken,
  setCsrfToken,
} from '../api'

describe('api', () => {
  const fetchMock =
    vi.fn<typeof fetch>()

  beforeEach(() => {
    fetchMock.mockReset()
    mocks.toastError.mockReset()

    vi.stubGlobal(
      'fetch',
      fetchMock,
    )

    setCsrfToken(null)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    setCsrfToken(null)
  })

  it('stores and clears the CSRF token', () => {
    setCsrfToken('token')

    expect(
      getCsrfToken(),
    ).toBe('token')

    setCsrfToken(null)

    expect(
      getCsrfToken(),
    ).toBeNull()
  })

  it('returns a successful JSON response', async () => {
    fetchMock.mockResolvedValue(
      new Response(
        JSON.stringify({
          value: 42,
        }),
        {
          status: 200,
          headers: {
            'Content-Type':
              'application/json',
          },
        },
      ),
    )

    await expect(
      api<{ value: number }>(
        '/api/test',
      ),
    ).resolves.toEqual({
      value: 42,
    })

    expect(
      mocks.toastError,
    ).not.toHaveBeenCalled()
  })

  it('returns undefined for a 204 response', async () => {
    fetchMock.mockResolvedValue(
      new Response(
        null,
        {
          status: 204,
        },
      ),
    )

    await expect(
      api<void>(
        '/api/test',
        {
          method: 'DELETE',
        },
      ),
    ).resolves.toBeUndefined()
  })

  it('adds JSON and CSRF headers to mutations', async () => {
    setCsrfToken(
      'csrf-token',
    )

    fetchMock.mockResolvedValue(
      new Response(
        JSON.stringify({
          ok: true,
        }),
        {
          status: 200,
        },
      ),
    )

    await api(
      '/api/test',
      {
        method: 'POST',

        body:
          JSON.stringify({
            value: 1,
          }),
      },
    )

    expect(
      fetchMock,
    ).toHaveBeenCalledOnce()

    const call =
      fetchMock.mock.calls[0]

    expect(call)
      .toBeDefined()

    const [
      path,
      init,
    ] = call!

    expect(path)
      .toBe('/api/test')

    expect(
      init?.credentials,
    ).toBe('same-origin')

    const headers =
      new Headers(
        init?.headers,
      )

    expect(
      headers.get('Accept'),
    ).toBe(
      'application/json',
    )

    expect(
      headers.get(
        'Content-Type',
      ),
    ).toBe(
      'application/json',
    )

    expect(
      headers.get(
        'X-CSRF-TOKEN',
      ),
    ).toBe(
      'csrf-token',
    )
  })

  it('does not send CSRF on GET', async () => {
    setCsrfToken(
      'csrf-token',
    )

    fetchMock.mockResolvedValue(
      new Response(
        JSON.stringify({
          ok: true,
        }),
        {
          status: 200,
        },
      ),
    )

    await api(
      '/api/test',
    )

    const call =
      fetchMock.mock.calls[0]

    expect(call)
      .toBeDefined()

    const init =
      call![1]

    const headers =
      new Headers(
        init?.headers,
      )

    expect(
      headers.has(
        'X-CSRF-TOKEN',
      ),
    ).toBe(false)
  })

  it('throws a typed network error', async () => {
    fetchMock.mockRejectedValue(
      new TypeError(
        'Network failure',
      ),
    )

    let thrown:
      unknown = null

    try {
      await api(
        '/api/test',
      )
    } catch (error) {
      thrown = error
    }

    expect(
      thrown,
    ).toBeInstanceOf(
      ApiError,
    )

    const error =
      thrown as ApiError

    expect(
      error.status,
    ).toBe(0)

    expect(
      error.code,
    ).toBe(
      'NETWORK_ERROR',
    )

    expect(
      mocks.toastError,
    ).toHaveBeenCalledWith(
      'Impossible de contacter le serveur.',
    )
  })

  it('preserves structured API errors', async () => {
    fetchMock.mockResolvedValue(
      new Response(
        JSON.stringify({
          error:
            'Validation failed.',
          code:
            'INVALID_REQUEST',
        }),
        {
          status: 422,
          headers: {
            'Content-Type':
              'application/json',
          },
        },
      ),
    )

    let thrown:
      unknown = null

    try {
      await api(
        '/api/test',
      )
    } catch (error) {
      thrown = error
    }

    expect(
      thrown,
    ).toBeInstanceOf(
      ApiError,
    )

    const error =
      thrown as ApiError

    expect(
      error.message,
    ).toBe(
      'Validation failed.',
    )

    expect(
      error.status,
    ).toBe(422)

    expect(
      error.code,
    ).toBe(
      'INVALID_REQUEST',
    )
  })

  it('uses an HTTP fallback for malformed error bodies', async () => {
    fetchMock.mockResolvedValue(
      new Response(
        'not-json',
        {
          status: 500,
        },
      ),
    )

    await expect(
      api(
        '/api/test',
      ),
    ).rejects.toMatchObject({
      message:
        'Request failed with HTTP 500.',
      status: 500,
      code: null,
    })

    expect(
      mocks.toastError,
    ).toHaveBeenCalledWith(
      'Request failed with HTTP 500.',
    )
  })

  it('rejects invalid JSON from a successful response', async () => {
    fetchMock.mockResolvedValue(
      new Response(
        'not-json',
        {
          status: 200,
        },
      ),
    )

    await expect(
      api(
        '/api/test',
      ),
    ).rejects.toMatchObject({
      status: 200,
      code:
        'INVALID_API_RESPONSE',
    })

    expect(
      mocks.toastError,
    ).toHaveBeenCalledWith(
      'Le serveur a renvoyé une réponse invalide.',
    )
  })

  it('dispatches the authentication-required event', async () => {
    const target =
      new EventTarget()

    const listener =
      vi.fn()

    target.addEventListener(
      'homeen:user-auth-required',
      listener,
    )

    vi.stubGlobal(
      'window',
      target,
    )

    fetchMock.mockResolvedValue(
      new Response(
        JSON.stringify({
          error:
            'Authentication required.',
          code:
            'USER_AUTH_REQUIRED',
        }),
        {
          status: 401,
          headers: {
            'Content-Type':
              'application/json',
          },
        },
      ),
    )

    await expect(
      api(
        '/api/test',
      ),
    ).rejects.toMatchObject({
      status: 401,
      code:
        'USER_AUTH_REQUIRED',
    })

    expect(
      listener,
    ).toHaveBeenCalledOnce()
  })
})
