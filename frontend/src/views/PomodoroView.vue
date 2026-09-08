<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
} from 'vue'

import { usePomodoro } from '../composables/usePomodoro'
import { api } from '../services/api'

import {
  formatClock,
  formatDate,
  formatDuration,
} from '../services/format'

import type {
  PomodoroSession,
} from '../types/domain'

const {
  store,
  loadActive,
  loadPresets,
  start,
  stop,
} = usePomodoro()

const workMinutes = ref(25)
const error = ref('')
const history = ref<PomodoroSession[]>([])
const starting = ref(false)

const phaseLabel =
  computed(
    () =>
      store.live?.phase === 'break'
        ? 'Pause'
        : 'Travail',
  )

async function loadHistory(): Promise<void> {
  const response =
    await api<{
      sessions: PomodoroSession[]
    }>(
      '/api/pomodoro/history?limit=30',
    )

  history.value =
    response.sessions
}

async function begin(
  minutes = workMinutes.value,
): Promise<void> {
  if (
    !Number.isInteger(minutes)
    || minutes < 5
  ) {
    error.value =
      'La durée de travail doit être un nombre entier d’au moins 5 minutes.'

    return
  }

  starting.value = true
  error.value = ''

  try {
    await start(minutes)

    workMinutes.value = minutes

    await loadHistory()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de démarrer la session.'
  } finally {
    starting.value = false
  }
}

async function end(): Promise<void> {
  await stop()
  await loadHistory()
}

onMounted(async () => {
  await Promise.all([
    loadActive(),
    loadPresets(),
    loadHistory(),
  ])
})
</script>

<template>
  <section class="page pomodoro-page">
    <header class="page-header">
      <div>
        <h1>
          Pomodoro
        </h1>

        <p class="muted">
          Alternez automatiquement travail
          et pauses de 5 minutes jusqu’à
          la fin de votre session.
        </p>
      </div>
    </header>

    <div
      v-if="store.active && store.live"
      class="focus-stage"
      :class="store.live.phase"
    >
      <div class="focus-status">
        <span class="pulse-dot active" />
        {{ phaseLabel }}
      </div>

      <div class="focus-clock">
        {{
          formatClock(
            store.live.remainingSeconds,
          )
        }}
      </div>

      <p>
        {{ store.active.workMinutes }}
        min de travail · 5 min de pause
      </p>

      <div class="focus-metrics">
        <div>
          <strong>
            {{ store.live.completedWorkCycles }}
          </strong>
          <span>cycles terminés</span>
        </div>

        <div>
          <strong>
            {{
              formatDuration(
                store.live.focusSeconds,
              )
            }}
          </strong>
          <span>concentration</span>
        </div>

        <div>
          <strong>
            {{
              formatDuration(
                store.live.breakSeconds,
              )
            }}
          </strong>
          <span>pause</span>
        </div>
      </div>

      <button
        class="stop-button"
        @click="end"
      >
        Arrêter la session
      </button>
    </div>

    <div
      v-else
      class="pomodoro-start-grid"
    >
      <section class="panel session-builder">
        <h2>
          Durée de travail
        </h2>

        <p class="muted">
          Minimum 5 minutes.
          Aucune durée maximale n’est imposée.
        </p>

        <form @submit.prevent="begin()">
          <div class="duration-input">
            <input
              v-model.number="workMinutes"
              type="number"
              min="5"
              step="1"
              required
            />

            <span>minutes</span>
          </div>

          <p
            v-if="error"
            class="form-error"
          >
            {{ error }}
          </p>

          <button
            class="primary wide"
            :disabled="starting"
          >
            {{
              starting
                ? 'Démarrage…'
                : 'Démarrer la session'
            }}
          </button>
        </form>
      </section>

      <section class="panel presets-panel">
        <h2>
          Durées récentes
        </h2>

        <p class="muted">
          Relancez rapidement une durée
          déjà utilisée.
        </p>

        <div class="preset-grid">
          <button
            v-for="preset in store.presets"
            :key="preset.id"
            class="preset-button"
            @click="begin(preset.workMinutes)"
          >
            <strong>
              {{ preset.workMinutes }}
            </strong>

            <span>minutes</span>
          </button>

          <p
            v-if="store.presets.length === 0"
            class="empty-inline"
          >
            Une durée sera enregistrée
            automatiquement après sa première utilisation.
          </p>
        </div>
      </section>
    </div>

    <section class="panel history-panel">
      <div>
        <h2>
          Sessions récentes
        </h2>
      </div>

      <div class="session-table">
        <div class="session-row session-head">
          <span>Début</span>
          <span>Fin</span>
          <span>Durée</span>
          <span>Concentration</span>
        </div>

        <div
          v-for="session in history"
          :key="session.id"
          class="session-row"
        >
          <span>
            {{ formatDate(session.startedAt) }}
          </span>

          <span>
            {{
              session.stoppedAt
                ? formatDate(session.stoppedAt)
                : 'En cours'
            }}
          </span>

          <span>
            {{ session.workMinutes }} min
          </span>

          <strong>
            {{
              formatDuration(
                session.focusSeconds,
              )
            }}
          </strong>
        </div>

        <div
          v-if="history.length === 0"
          class="empty-state compact"
        >
          Aucune session Pomodoro pour le moment.
        </div>
      </div>
    </section>
  </section>
</template>
