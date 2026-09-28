import { createApp } from 'vue'
import App from './App.vue'
import { router } from './router'

import {
  initializeTheme,
} from './composables/useTheme'

import 'sweetalert2/dist/sweetalert2.min.css'
import './style.css'
import './styles/shell.css'
import './styles/google-refresh.css'

initializeTheme()

createApp(App).use(router).mount('#app')
