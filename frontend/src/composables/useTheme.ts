import {
  readonly,
  ref,
} from 'vue'

export type AppTheme =
  | 'light'
  | 'dark'

const STORAGE_KEY =
  'harpocrate-theme'

const theme =
  ref<AppTheme>('light')

function applyTheme(
  value: AppTheme,
): void {
  theme.value = value

  document.documentElement.dataset.theme =
    value

  document.documentElement.style.colorScheme =
    value
}

export function initializeTheme():
void {
  let stored:
    string | null = null

  try {
    stored =
      window.localStorage.getItem(
        STORAGE_KEY,
      )
  } catch {
    stored = null
  }

  if (
    stored === 'light'
    || stored === 'dark'
  ) {
    applyTheme(stored)
    return
  }

  applyTheme(
    window.matchMedia(
      '(prefers-color-scheme: dark)',
    ).matches
      ? 'dark'
      : 'light',
  )
}

function setTheme(
  value: AppTheme,
): void {
  applyTheme(value)

  try {
    window.localStorage.setItem(
      STORAGE_KEY,
      value,
    )
  } catch {
    // Theme persistence is optional.
  }
}

function toggleTheme(): void {
  setTheme(
    theme.value === 'dark'
      ? 'light'
      : 'dark',
  )
}

export function useTheme() {
  return {
    theme:
      readonly(theme),

    setTheme,
    toggleTheme,
  }
}
