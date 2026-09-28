import {
  afterEach,
  beforeEach,
  describe,
  expect,
  it,
  vi,
} from 'vitest'

import {
  useToast,
} from '../useToast'

describe('useToast', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    useToast().hide()
  })

  afterEach(() => {
    useToast().hide()
    vi.useRealTimers()
  })

  it('shows and hides an error toast', () => {
    const {
      toast,
      error,
      hide,
    } = useToast()

    error(
      'Failure',
    )

    expect(
      toast.value,
    ).toMatchObject({
      message:
        'Failure',
      kind:
        'error',
      visible:
        true,
    })

    hide()

    expect(
      toast.value.visible,
    ).toBe(false)
  })

  it('automatically hides an error after 4200 ms', () => {
    const {
      toast,
      error,
    } = useToast()

    error(
      'Failure',
    )

    vi.advanceTimersByTime(
      4199,
    )

    expect(
      toast.value.visible,
    ).toBe(true)

    vi.advanceTimersByTime(
      1,
    )

    expect(
      toast.value.visible,
    ).toBe(false)
  })

  it('does not extend an identical visible toast', () => {
    const {
      toast,
      error,
    } = useToast()

    error(
      'Failure',
    )

    vi.advanceTimersByTime(
      3000,
    )

    error(
      'Failure',
    )

    vi.advanceTimersByTime(
      1200,
    )

    expect(
      toast.value.visible,
    ).toBe(false)
  })

  it('replaces the active toast with a new one', () => {
    const {
      toast,
      error,
      success,
    } = useToast()

    error(
      'First',
    )

    success(
      'Second',
    )

    expect(
      toast.value,
    ).toMatchObject({
      message:
        'Second',
      kind:
        'success',
      visible:
        true,
    })
  })
})
