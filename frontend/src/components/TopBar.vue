<script setup lang="ts">
import { ref, watch } from 'vue'
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

watch(
  () => route.query.q,
  (value) => {
    query.value =
      typeof value === 'string'
        ? value
        : ''
  },
)

async function search(): Promise<void> {
  const value = query.value.trim()

  await router.push({
    path: '/notes',
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
            ? 'Expand sidebar'
            : 'Collapse sidebar'
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
        v-model="query"
        type="search"
        placeholder="Search your notes"
        aria-label="Search your notes"
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
