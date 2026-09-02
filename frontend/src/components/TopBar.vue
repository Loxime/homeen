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

      <div class="topbar-brand">
        <span class="homeen-mark">
          H
        </span>

        <strong>
          Homeen
        </strong>
      </div>
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
      class="topbar-lock"
      type="button"
      title="Lock Homeen"
      aria-label="Lock Homeen"
      @click="signOut"
    >
      <AppIcon
        name="lock"
        :size="19"
      />

      <span>
        Lock
      </span>
    </button>
  </header>
</template>
