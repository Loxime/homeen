import {
  watch,
  type Ref,
} from 'vue'

import {
  useToast,
} from './useToast'

export function watchErrorToast(
  error: Ref<string | null>,
): void {
  const {
    error:
      showError,
  } = useToast()

  watch(
    error,
    value => {
      const message =
        value?.trim()
        ?? ''

      if (message === '') {
        return
      }

      showError(
        message,
      )
    },
    {
      flush: 'sync',
    },
  )
}
