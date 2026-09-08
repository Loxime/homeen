import { createApp } from 'vue'
import App from './App.vue'
import { router } from './router'
import './style.css'
import './styles/shell.css'
import './styles/google-refresh.css'

createApp(App).use(router).mount('#app')
