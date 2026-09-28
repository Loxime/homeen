import {
  afterEach,
  beforeEach,
  describe,
  expect,
  it,
  vi,
} from 'vitest'

import {
  ref,
} from 'vue'

import {
  watchErrorToast,
} from '../useErrorToast'

import {
  useToast,
} from '../useToast'

describe('watchErrorToast', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    useToast().hide()
  })

  afterEach(() => {
    useToast().hide()
    vi.useRealTimers()
  })

  it('shows a trimmed error message', () => {
    const error =
      ref<string | null>(
        null,
      )

    watchErrorToast(
      error,
    )

    error.value =
      '  Failure  '

    expect(
      useToast()
        .toast
        .value,
    ).toMatchObject({
      message:
        'Failure',
      kind:
        'error',
      visible:
        true,
    })
  })

  it('ignores empty errors', () => {
    const error =
      ref<string | null>(
        null,
      )

    watchErrorToast(
      error,
    )

    error.value =
      '   '

    expect(
      useToast()
        .toast
        .value
        .visible,
    ).toBe(false)
  })
})
