<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from './AppIcon.vue'
import { ApiError } from '../services/api'
import { usePomodoro } from '../composables/usePomodoro'

defineProps<{ collapsed: boolean }>()

const router = useRouter()
const { store, quickStart } = usePomodoro()

const quickLoading = ref(false)

const quickLabel = computed(() => {
  if (store.active) return 'Focus running'

  const latest = store.presets[0]

  return latest
    ? `Start ${latest.workMinutes}m`
    : 'New session'
})

async function quickFocus(): Promise<void> {
  if (store.active) {
    await router.push('/pomodoro')
    return
  }

  quickLoading.value = true

  try {
    await quickStart()
    await router.push('/pomodoro')
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      await router.push('/pomodoro')
    } else {
      throw error
    }
  } finally {
    quickLoading.value = false
  }
}
</script>

<template>
  <aside class="sidebar" :class="{ collapsed }">
    <button
      class="sidebar-entry focus-quick"
      :title="collapsed ? quickLabel : undefined"
      :disabled="quickLoading"
      @click="quickFocus"
    >
      <span class="sidebar-icon-slot">
        <AppIcon name="timer" />
        <span
          class="pulse-dot"
          :class="{ active: store.active }"
        />
      </span>

      <span class="sidebar-label">
        {{ quickLoading ? 'Starting…' : quickLabel }}
      </span>
    </button>

    <nav class="nav-list">
      <RouterLink class="sidebar-entry" to="/notes">
        <span class="sidebar-icon-slot">
          <AppIcon name="note" />
        </span>
        <span class="sidebar-label">Notes</span>
      </RouterLink>

      <RouterLink class="sidebar-entry" to="/labels">
        <span class="sidebar-icon-slot">
          <AppIcon name="tag" />
        </span>
        <span class="sidebar-label">Labels</span>
      </RouterLink>

      <RouterLink class="sidebar-entry" to="/pomodoro">
        <span class="sidebar-icon-slot">
          <AppIcon name="timer" />
        </span>
        <span class="sidebar-label">Pomodoro</span>
      </RouterLink>

      <RouterLink class="sidebar-entry" to="/statistics">
        <span class="sidebar-icon-slot">
          <AppIcon name="chart" />
        </span>
        <span class="sidebar-label">Statistics</span>
      </RouterLink>

      <RouterLink class="sidebar-entry" to="/archived">
        <span class="sidebar-icon-slot">
          <AppIcon name="archive" />
        </span>
        <span class="sidebar-label">Archived</span>
      </RouterLink>

      <RouterLink class="sidebar-entry" to="/trash">
        <span class="sidebar-icon-slot">
          <AppIcon name="trash" />
        </span>
        <span class="sidebar-label">Trash</span>
      </RouterLink>
      
      <RouterLink
        class="sidebar-entry"
        to="/profile"
      >
        <span class="sidebar-icon-slot">
          <AppIcon name="user" />
        </span>

        <span class="sidebar-label">
          Profile
        </span>
      </RouterLink>
    </nav>
  </aside>
</template>
