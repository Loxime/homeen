<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { usePomodoro } from '../composables/usePomodoro'
import { api } from '../services/api'
import { formatClock, formatDate, formatDuration } from '../services/format'
import type { PomodoroSession } from '../types/domain'

const { store, loadActive, loadPresets, start, stop } = usePomodoro()
const workMinutes = ref(25)
const error = ref('')
const history = ref<PomodoroSession[]>([])
const starting = ref(false)

const phaseLabel = computed(() => store.live?.phase === 'break' ? 'BREAK' : 'WORK')

async function loadHistory(): Promise<void> {
  const response = await api<{ sessions: PomodoroSession[] }>('/api/pomodoro/history?limit=30')
  history.value = response.sessions
}

async function begin(minutes = workMinutes.value): Promise<void> {
  if (!Number.isInteger(minutes) || minutes < 5) {
    error.value = 'Work duration must be an integer of at least 5 minutes.'
    return
  }
  starting.value = true
  error.value = ''
  try {
    await start(minutes)
    workMinutes.value = minutes
    await loadHistory()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Unable to start session.'
  } finally {
    starting.value = false
  }
}

async function end(): Promise<void> {
  await stop()
  await loadHistory()
}

onMounted(async () => {
  await Promise.all([loadActive(), loadPresets(), loadHistory()])
})
</script>

<template>
  <section class="page pomodoro-page">
    <header class="page-header">
      <div><p class="eyebrow">CONCENTRATION</p><h1>Pomodoro</h1><p class="muted">Infinite work / 5-minute break loop. Stop it only when you are finished.</p></div>
    </header>

    <div v-if="store.active && store.live" class="focus-stage" :class="store.live.phase">
      <div class="focus-status"><span class="pulse-dot active" /> {{ phaseLabel }}</div>
      <div class="focus-clock">{{ formatClock(store.live.remainingSeconds) }}</div>
      <p>{{ store.active.workMinutes }} min work · 5 min fixed break</p>
      <div class="focus-metrics">
        <div><strong>{{ store.live.completedWorkCycles }}</strong><span>work cycles</span></div>
        <div><strong>{{ formatDuration(store.live.focusSeconds) }}</strong><span>focus time</span></div>
        <div><strong>{{ formatDuration(store.live.breakSeconds) }}</strong><span>break time</span></div>
      </div>
      <button class="stop-button" @click="end">Stop session</button>
    </div>

    <div v-else class="pomodoro-start-grid">
      <section class="panel session-builder">
        <p class="eyebrow">NEW SESSION</p>
        <h2>Choose your work duration</h2>
        <p class="muted">Minimum 5 minutes. There is intentionally no maximum.</p>
        <form @submit.prevent="begin()">
          <div class="duration-input"><input v-model.number="workMinutes" type="number" min="5" step="1" required /><span>minutes</span></div>
          <p v-if="error" class="form-error">{{ error }}</p>
          <button class="primary wide" :disabled="starting">{{ starting ? 'Starting…' : 'Start infinite session' }}</button>
        </form>
      </section>

      <section class="panel presets-panel">
        <p class="eyebrow">SAVED DURATIONS</p>
        <h2>Relaunch a preset</h2>
        <div class="preset-grid">
          <button v-for="preset in store.presets" :key="preset.id" class="preset-button" @click="begin(preset.workMinutes)">
            <strong>{{ preset.workMinutes }}</strong><span>minutes</span>
          </button>
          <p v-if="store.presets.length === 0" class="empty-inline">A duration is saved automatically after its first use.</p>
        </div>
      </section>
    </div>

    <section class="panel history-panel">
      <div><p class="eyebrow">SESSION LOG</p><h2>Recent concentration sessions</h2></div>
      <div class="session-table">
        <div class="session-row session-head"><span>Started</span><span>Stopped</span><span>Work preset</span><span>Focused</span></div>
        <div v-for="session in history" :key="session.id" class="session-row">
          <span>{{ formatDate(session.startedAt) }}</span>
          <span>{{ session.stoppedAt ? formatDate(session.stoppedAt) : 'Running' }}</span>
          <span>{{ session.workMinutes }} min</span>
          <strong>{{ formatDuration(session.focusSeconds) }}</strong>
        </div>
        <div v-if="history.length === 0" class="empty-state compact">No Pomodoro session yet.</div>
      </div>
    </section>
  </section>
</template>
