<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError } from '../services/api'
import { usePomodoro } from '../composables/usePomodoro'

const router = useRouter()
const { store, quickStart } = usePomodoro()
const quickLoading = ref(false)
const quickLabel = computed(() => {
  if (store.active) return 'Focus running'
  const latest = store.presets[0]
  return latest ? `Start ${latest.workMinutes}m` : 'New session'
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
    if (error instanceof ApiError && error.status === 404) await router.push('/pomodoro')
    else throw error
  } finally {
    quickLoading.value = false
  }
}
</script>

<template>
  <aside class="sidebar">
    <div class="sidebar-brand"><span>H</span><strong>Homeen</strong></div>
    <button class="focus-quick" :disabled="quickLoading" @click="quickFocus">
      <span class="pulse-dot" :class="{ active: store.active }" />
      {{ quickLoading ? 'Starting…' : quickLabel }}
    </button>

    <nav class="nav-list" aria-label="Main navigation">
      <RouterLink to="/notes"><span>▤</span> Notes</RouterLink>
      <RouterLink to="/labels"><span>◈</span> Labels</RouterLink>
      <RouterLink to="/pomodoro"><span>◷</span> Pomodoro</RouterLink>
      <RouterLink to="/statistics"><span>⌁</span> Statistics</RouterLink>
    </nav>

    <nav class="nav-list secondary" aria-label="Note states">
      <RouterLink to="/archived"><span>□</span> Archived</RouterLink>
      <RouterLink to="/trash"><span>⌫</span> Trash</RouterLink>
    </nav>

    <div class="sidebar-footer">LOCAL workspace</div>
  </aside>
</template>
