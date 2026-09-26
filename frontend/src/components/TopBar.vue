<script setup lang="ts">
import {
  onMounted,
  onUnmounted,
  ref,
  watch,
} from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppIcon from './AppIcon.vue'
import { useAccess } from '../composables/useAccess'

defineProps<{
  sidebarCollapsed: boolean
}>()

const emit = defineEmits<{
  'toggle-sidebar': []
}>()

const route = useRoute()
const router = useRouter()

const { logout } = useAccess()

const query = ref(
  typeof route.query.q === 'string'
    ? route.query.q
    : '',
)

const searchInput =
  ref<HTMLInputElement | null>(null)

watch(
  () => route.query.q,
  (value) => {
    query.value =
      typeof value === 'string'
        ? value
        : ''
  },
)

function focusSearch(
  event: KeyboardEvent,
): void {
  const shortcut =
    (
      event.ctrlKey
      || event.metaKey
    )
    && !event.altKey
    && !event.shiftKey
    && event.key.toLowerCase() === 'k'

  if (!shortcut) {
    return
  }

  event.preventDefault()

  searchInput.value?.focus()
  searchInput.value?.select()
}

onMounted(() => {
  window.addEventListener(
    'keydown',
    focusSearch,
  )
})

onUnmounted(() => {
  window.removeEventListener(
    'keydown',
    focusSearch,
  )
})

async function search(): Promise<void> {
  const value = query.value.trim()

  await router.push({
    path: '/search',
    query: value
      ? { q: value }
      : {},
  })
}

async function signOut(): Promise<void> {
  await logout()
  await router.push('/')
}
</script>

<template>
  <header class="topbar">
    <div class="topbar-leading">
      <button
        class="topbar-icon-button"
        type="button"
        :aria-label="
          sidebarCollapsed
            ? 'Afficher le menu'
            : 'Réduire le menu'
        "
        @click="emit('toggle-sidebar')"
      >
        <AppIcon name="menu" :size="22" />
      </button>

      <RouterLink
        class="topbar-brand"
        to="/notes"
        aria-label="Accueil"
      >
        <img
          class="homeen-mark"
          src="/favicon.svg"
          alt=""
        />
      </RouterLink>
    </div>

    <form
      class="search-box"
      role="search"
      @submit.prevent="search"
    >
      <AppIcon
        name="search"
        :size="21"
      />

      <input
        ref="searchInput"
        v-model="query"
        type="search"
        placeholder="Rechercher notes, tâches, tags"
        aria-label="Recherche globale"
      />
    </form>

    <button
      class="topbar-logout"
      type="button"
      title="Se déconnecter"
      aria-label="Se déconnecter"
      @click="signOut"
    >
      <AppIcon
        name="logout"
        :size="19"
      />

      <span>
        Déconnexion
      </span>
    </button>
  </header>
</template>
