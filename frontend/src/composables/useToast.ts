import {
  readonly,
  ref,
} from 'vue'

export type ToastKind =
  | 'success'
  | 'error'
  | 'info'

interface ToastState {
  message: string
  kind: ToastKind
  visible: boolean
}

const toast =
  ref<ToastState>({
    message: '',
    kind: 'info',
    visible: false,
  })

let timer:
  ReturnType<typeof setTimeout>
  | null = null

function hide(): void {
  toast.value.visible = false

  if (timer !== null) {
    clearTimeout(timer)
    timer = null
  }
}

function show(
  message: string,
  kind: ToastKind = 'info',
  duration = 2800,
): void {
  if (timer !== null) {
    clearTimeout(timer)
  }

  toast.value = {
    message,
    kind,
    visible: true,
  }

  timer =
    setTimeout(
      () => {
        toast.value.visible = false
        timer = null
      },
      duration,
    )
}

function success(
  message: string,
): void {
  show(
    message,
    'success',
  )
}

function error(
  message: string,
): void {
  show(
    message,
    'error',
    4200,
  )
}

function info(
  message: string,
): void {
  show(
    message,
    'info',
  )
}

export function useToast() {
  return {
    toast:
      readonly(toast),

    show,
    success,
    error,
    info,
    hide,
  }
}
