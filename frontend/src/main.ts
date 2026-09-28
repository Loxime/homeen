import { createApp } from 'vue'
import App from './App.vue'
import { router } from './router'

import {
  initializeTheme,
} from './composables/useTheme'

import {
  useToast,
} from './composables/useToast'

import 'sweetalert2/dist/sweetalert2.min.css'
import './style.css'
import './styles/shell.css'
import './styles/google-refresh.css'

initializeTheme()

const {
  error:
    showErrorToast,
} = useToast()

window.addEventListener(
  'error',
  event => {
    const message =
      event.error instanceof Error
        && event.error.message.trim()
        !== ''
        ? event.error.message
        : 'Une erreur inattendue est survenue.'

    showErrorToast(
      message,
    )
  },
)

window.addEventListener(
  'unhandledrejection',
  event => {
    /*
     * Les erreurs API sont normalement
     * interceptées par les vues.
     * Ce handler couvre les promesses
     * réellement non gérées.
     */
    const message =
      event.reason instanceof Error
        && event.reason.message.trim()
        !== ''
        ? event.reason.message
        : 'Une erreur inattendue est survenue.'

    showErrorToast(
      message,
    )
  },
)

createApp(App).use(router).mount('#app')
