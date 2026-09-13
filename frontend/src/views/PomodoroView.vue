<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
} from 'vue'

import {
  usePomodoro,
} from '../composables/usePomodoro'

import { api } from '../services/api'

import PomodoroGardenVisual from '../components/PomodoroGardenVisual.vue'

import {
  formatClock,
  formatDate,
  formatDuration,
} from '../services/format'

import type {
  PomodoroInsights,
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

const history =
  ref<PomodoroSession[]>([])

const insights =
  ref<PomodoroInsights | null>(
    null,
  )

const completedSession =
  ref<PomodoroSession | null>(
    null,
  )

const starting = ref(false)
const ratingSaving = ref(false)

const phaseLabel =
  computed(
    () =>
      store.live?.phase === 'break'
        ? 'Pause'
        : 'Travail',
  )

const ratingsNeeded =
  computed(
    () =>
      Math.max(
        0,
        3
        - (
          insights.value
            ?.ratingCount
          ?? 0
        ),
      ),
  )

function ratingSymbol(
  rating:
    | number
    | null
    | undefined,
): string {
  if (rating === 1) {
    return '😕'
  }

  if (rating === 2) {
    return '🙂'
  }

  if (rating === 3) {
    return '😄'
  }

  return '—'
}

async function loadHistory():
Promise<void> {
  const response =
    await api<{
      sessions:
        PomodoroSession[]
    }>(
      '/api/pomodoro/history?limit=30',
    )

  history.value =
    response.sessions
}

async function loadInsights():
Promise<void> {
  insights.value =
    await api<PomodoroInsights>(
      '/api/pomodoro/insights',
    )
}

async function begin(
  minutes =
    workMinutes.value,
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
  completedSession.value = null

  try {
    await start(minutes)

    workMinutes.value =
      minutes

    await Promise.all([
      loadHistory(),
      loadInsights(),
    ])
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
  const completed =
    await stop()

  if (completed) {
    completedSession.value =
      completed
  }

  await Promise.all([
    loadHistory(),
    loadInsights(),
  ])
}

async function rateSession(
  rating: 1 | 2 | 3,
): Promise<void> {
  if (
    !completedSession.value
    || ratingSaving.value
  ) {
    return
  }

  ratingSaving.value = true
  error.value = ''

  try {
    await api<PomodoroSession>(
      `/api/pomodoro/sessions/${completedSession.value.id}/rating`,
      {
        method: 'POST',

        body: JSON.stringify({
          rating,
        }),
      },
    )

    completedSession.value = null

    await Promise.all([
      loadHistory(),
      loadInsights(),
    ])
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’enregistrer votre ressenti.'
  } finally {
    ratingSaving.value = false
  }
}

function useRecommendation(): void {
  const recommended =
    insights.value
      ?.recommendedMinutes

  if (recommended !== null
    && recommended !== undefined
  ) {
    workMinutes.value =
      recommended
  }
}

onMounted(async () => {
  await loadActive()

  await Promise.all([
    loadPresets(),
    loadHistory(),
    loadInsights(),
  ])

  if (
    insights.value
      ?.recommendedMinutes
  ) {
    workMinutes.value =
      insights.value
        .recommendedMinutes
  }
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
          Votre temps de concentration
          fait grandir votre espace,
          session après session.
        </p>
      </div>
    </header>

    <p
      v-if="error"
      class="form-error"
    >
      {{ error }}
    </p>

    <section
      v-if="insights"
      class="focus-garden panel"
    >
      <div class="focus-garden-visual">
        <PomodoroGardenVisual
          :stage="insights.stage"
        />

        <div>
          <span class="focus-garden-kicker">
            Votre progression
          </span>

          <h2>
            {{ insights.stageLabel }}
          </h2>

          <p class="muted">
            {{
              formatDuration(
                insights.totalFocusSeconds,
              )
            }}
            de concentration cumulée
          </p>
        </div>
      </div>

      <div
        v-if="
          insights.nextStageMinutes
          !== null
        "
        class="focus-garden-progress"
      >
        <div class="focus-garden-track">
          <span
            :style="{
              width:
                `${insights.progressPercent}%`,
            }"
          />
        </div>

        <small>
          Prochaine étape à
          {{
            insights.nextStageMinutes
          }}
          min de concentration cumulée
        </small>
      </div>

      <div
        v-else
        class="focus-garden-complete"
      >
        Votre forêt est installée.
        Continuez à la faire grandir.
      </div>

      <div class="ideal-duration">
        <div>
          <span class="focus-garden-kicker">
            Durée idéale estimée
          </span>

          <strong
            v-if="
              insights.recommendedMinutes
              !== null
            "
          >
            {{
              insights.recommendedMinutes
            }}
            min
          </strong>

          <strong v-else>
            En apprentissage
          </strong>

          <p class="muted">
            <template
              v-if="
                insights.recommendedMinutes
                !== null
              "
            >
              Calculée à partir de vos
              derniers ressentis.
            </template>

            <template v-else>
              Notez encore
              {{ ratingsNeeded }}
              session{{
                ratingsNeeded > 1
                  ? 's'
                  : ''
              }}
              pour obtenir une recommandation.
            </template>
          </p>
        </div>

        <button
          v-if="
            insights.recommendedMinutes
            !== null
            && !store.active
          "
          class="ui-button ui-button--secondary"
          type="button"
          @click="useRecommendation"
        >
          Utiliser cette durée
        </button>
      </div>
    </section>

    <section
      v-if="completedSession"
      class="session-feedback panel"
    >
      <div>
        <h2>
          Comment était cette session ?
        </h2>

        <p class="muted">
          Votre réponse ajuste
          progressivement la durée
          qui vous convient le mieux.
        </p>
      </div>

      <div class="session-feedback-options">
        <button
          type="button"
          :disabled="ratingSaving"
          @click="rateSession(1)"
        >
          <span>😕</span>
          <strong>Trop longue</strong>
          <small>
            Je préfère plus court
          </small>
        </button>

        <button
          type="button"
          :disabled="ratingSaving"
          @click="rateSession(2)"
        >
          <span>🙂</span>
          <strong>Bien</strong>
          <small>
            Cette durée me convient
          </small>
        </button>

        <button
          type="button"
          :disabled="ratingSaving"
          @click="rateSession(3)"
        >
          <span>😄</span>
          <strong>Je pouvais continuer</strong>
          <small>
            Je peux viser plus long
          </small>
        </button>
      </div>
    </section>

    <div
      v-if="
        store.active
        && store.live
      "
      class="focus-stage"
      :class="
        store.live.phase
      "
    >
      <div class="focus-status">
        <span
          class="pulse-dot active"
        />

        {{ phaseLabel }}
      </div>

      <div class="focus-clock">
        {{
          formatClock(
            store.live
              .remainingSeconds,
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
            {{
              store.live
                .completedWorkCycles
            }}
          </strong>

          <span>
            cycles terminés
          </span>
        </div>

        <div>
          <strong>
            {{
              formatDuration(
                store.live
                  .focusSeconds,
              )
            }}
          </strong>

          <span>
            concentration
          </span>
        </div>

        <div>
          <strong>
            {{
              formatDuration(
                store.live
                  .breakSeconds,
              )
            }}
          </strong>

          <span>
            pause
          </span>
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
          Ajustez la durée ou utilisez
          la recommandation calculée.
        </p>

        <form
          @submit.prevent="begin()"
        >
          <div class="duration-input">
            <input
              v-model.number="
                workMinutes
              "
              type="number"
              min="5"
              step="1"
              required
            />

            <span>
              minutes
            </span>
          </div>

          <button
            class="ui-button ui-button--primary ui-button--block"
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
            v-for="
              preset
              in store.presets
            "
            :key="preset.id"
            class="preset-button"
            @click="
              begin(
                preset.workMinutes,
              )
            "
          >
            <strong>
              {{
                preset.workMinutes
              }}
            </strong>

            <span>
              minutes
            </span>
          </button>

          <p
            v-if="
              store.presets.length
              === 0
            "
            class="empty-inline"
          >
            Une durée sera enregistrée
            automatiquement après sa
            première utilisation.
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
        <div
          class="
            session-row
            session-head
            pomodoro-history-row
          "
        >
          <span>Début</span>
          <span>Fin</span>
          <span>Durée</span>
          <span>Concentration</span>
          <span>Ressenti</span>
        </div>

        <div
          v-for="session in history"
          :key="session.id"
          class="
            session-row
            pomodoro-history-row
          "
        >
          <span>
            {{
              formatDate(
                session.startedAt,
              )
            }}
          </span>

          <span>
            {{
              session.stoppedAt
                ? formatDate(
                    session.stoppedAt,
                  )
                : 'En cours'
            }}
          </span>

          <span>
            {{
              session.workMinutes
            }}
            min
          </span>

          <strong>
            {{
              formatDuration(
                session.focusSeconds,
              )
            }}
          </strong>

          <span
            class="history-rating"
            :title="
              session.focusRating
                ? `Ressenti ${session.focusRating}/3`
                : 'Session non notée'
            "
          >
            {{
              ratingSymbol(
                session.focusRating,
              )
            }}
          </span>
        </div>

        <div
          v-if="
            history.length === 0
          "
          class="empty-state compact"
        >
          Aucune session Pomodoro
          pour le moment.
        </div>
      </div>
    </section>
  </section>
</template>
