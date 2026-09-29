<script setup lang="ts">
import {
  watchErrorToast,
} from '../composables/useErrorToast'

import {
  computed,
  onMounted,
  ref,
} from 'vue'

import {
  usePomodoro,
} from '../composables/usePomodoro'

import {
  getPomodoroHistory,
  getPomodoroInsights,
  ratePomodoroSession,
} from '../services/pomodoroApi'

import {
  formatClock,
  formatDate,
  formatDuration,
} from '../services/format'

import type {
  PomodoroHistoryPagination,
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

watchErrorToast(error)

const history =
  ref<PomodoroSession[]>([])

const historyPagination =
  ref<PomodoroHistoryPagination>({
    page: 1,
    limit: 20,
    total: 0,
    pageCount: 1,
    hasPrevious: false,
    hasNext: false,
  })

const historyLoading =
  ref(false)

const insights =
  ref<PomodoroInsights | null>(
    null,
  )

const completedSession =
  ref<PomodoroSession | null>(
    null,
  )

const starting = ref(false)
const stopping = ref(false)
const ratingSaving = ref(false)

const phaseLabel =
  computed(
    () =>
      store.live?.phase === 'break'
        ? 'Pause'
        : 'Travail',
  )

const phaseDurationSeconds =
  computed(() => {
    if (
      !store.active
      || !store.live
    ) {
      return 1
    }

    return store.live.phase === 'break'
      ? 5 * 60
      : store.active.workMinutes * 60
  })

const phaseProgress =
  computed(() => {
    if (!store.live) {
      return 0
    }

    const total =
      phaseDurationSeconds.value

    const remaining =
      Math.min(
        total,
        Math.max(
          0,
          store.live.remainingSeconds,
        ),
      )

    return Math.min(
      100,
      Math.max(
        0,
        Math.round(
          (
            (
              total
              - remaining
            )
            / total
          )
          * 100,
        ),
      ),
    )
  })

const phaseProgressDegrees =
  computed(
    () =>
      phaseProgress.value
      * 3.6,
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

async function loadHistory(
  page =
    historyPagination.value.page,
): Promise<void> {
  historyLoading.value = true

  try {
    const response =
      await getPomodoroHistory(
        page,
      )

    history.value =
      response.sessions

    historyPagination.value =
      response.pagination
  } finally {
    historyLoading.value = false
  }
}

async function changeHistoryPage(
  page: number,
): Promise<void> {
  if (
    historyLoading.value
    || page < 1
    || page
      > historyPagination.value
        .pageCount
  ) {
    return
  }

  error.value = ''

  try {
    await loadHistory(page)
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger cette page de sessions.'
  }
}

async function loadInsights():
Promise<void> {
  insights.value =
    await getPomodoroInsights()
}

async function refreshOverview():
Promise<void> {
  await Promise.all([
    loadHistory(),
    loadInsights(),
  ])
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

    try {
      await refreshOverview()
    } catch {
      error.value =
        'La session a démarré, mais certaines informations n’ont pas pu être rafraîchies.'
    }
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
  if (stopping.value) {
    return
  }

  stopping.value = true
  error.value = ''

  try {
    const completed =
      await stop()

    if (completed) {
      completedSession.value =
        completed
    }

    try {
      await refreshOverview()
    } catch {
      error.value =
        'La session est arrêtée, mais certaines informations n’ont pas pu être rafraîchies.'
    }
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’arrêter la session.'
  } finally {
    stopping.value = false
  }
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
    await ratePomodoroSession(
      completedSession.value.id,
      rating,
    )

    completedSession.value = null

    try {
      await refreshOverview()
    } catch {
      error.value =
        'Votre ressenti est enregistré, mais certaines informations n’ont pas pu être rafraîchies.'
    }
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
  error.value = ''

  try {
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
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les données Pomodoro.'
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
          Suivez vos sessions de concentration
          et adaptez progressivement leur durée.
        </p>
      </div>
    </header>



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
      class="
        focus-stage
        focus-stage-refresh
      "
      :class="
        store.live.phase
      "
    >
      <div class="focus-stage-main">
        <div
          class="focus-timer-ring"
          :style="{
            '--focus-progress':
              `${phaseProgressDegrees}deg`,
          }"
        >
          <div class="focus-timer-core">
            <span class="focus-timer-phase">
              {{ phaseLabel }}
            </span>

            <strong class="focus-clock">
              {{
                formatClock(
                  store.live
                    .remainingSeconds,
                )
              }}
            </strong>

            <small>
              {{ phaseProgress }} %
              du bloc
            </small>
          </div>
        </div>

        <div class="focus-stage-copy">
          <div class="focus-status">
            <span
              class="pulse-dot active"
            />

            {{ phaseLabel }}
          </div>

          <h2>
            Une seule chose à la fois.
          </h2>

          <p>
            {{
              store.active.workMinutes
            }}
            min de travail ·
            5 min de pause
          </p>

          <button
            class="stop-button"
            :disabled="stopping"
            @click="end"
          >
            {{
              stopping
                ? 'Arrêt…'
                : 'Arrêter la session'
            }}
          </button>
        </div>
      </div>

      <div class="focus-metrics">
        <div>
          <span>
            Cycles terminés
          </span>

          <strong>
            {{
              store.live
                .completedWorkCycles
            }}
          </strong>
        </div>

        <div>
          <span>
            Concentration
          </span>

          <strong>
            {{
              formatDuration(
                store.live
                  .focusSeconds,
              )
            }}
          </strong>
        </div>

        <div>
          <span>
            Pause
          </span>

          <strong>
            {{
              formatDuration(
                store.live
                  .breakSeconds,
              )
            }}
          </strong>
        </div>
      </div>
    </div>

    <div
      v-else
      class="
        pomodoro-start-grid
        pomodoro-start-grid-refresh
      "
    >
      <section
        class="
          panel
          session-builder
          pomodoro-launch-card
        "
      >
        <span class="pomodoro-kicker">
          Nouvelle session
        </span>
        <h2>
          Durée de travail
        </h2>

        <p class="muted">
          Choisissez votre durée,
          lancez le minuteur et restez
          sur un seul objectif.
        </p>

        <div
          v-if="
            insights?.recommendedMinutes
          "
          class="pomodoro-recommendation"
        >
          <div>
            <span>
              Durée suggérée
            </span>

            <strong>
              {{
                insights.recommendedMinutes
              }}
              min
            </strong>
          </div>

          <button
            type="button"
            class="
              ui-button
              ui-button--secondary
              ui-button--compact
            "
            @click="useRecommendation"
          >
            Utiliser
          </button>
        </div>

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

      <section
        class="
          panel
          presets-panel
          pomodoro-presets-card
        "
      >
        <span class="pomodoro-kicker">
          Raccourcis
        </span>
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
      <div class="history-heading">
        <div>
          <h2>
            Sessions récentes
          </h2>

          <p
            v-if="
              historyPagination.total > 0
            "
            class="muted"
          >
            {{
              historyPagination.total
            }}
            session{{
              historyPagination.total > 1
                ? 's'
                : ''
            }}
            enregistrée{{
              historyPagination.total > 1
                ? 's'
                : ''
            }}
          </p>
        </div>
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

      <div
        v-if="
          historyPagination.total > 0
        "
        class="history-pagination"
      >
        <button
          type="button"
          class="
            ui-button
            ui-button--secondary
            ui-button--compact
          "
          :disabled="
            historyLoading
            || !historyPagination
              .hasPrevious
          "
          @click="
            changeHistoryPage(
              historyPagination.page
              - 1,
            )
          "
        >
          Précédent
        </button>

        <span>
          Page
          {{ historyPagination.page }}
          sur
          {{
            historyPagination.pageCount
          }}
        </span>

        <button
          type="button"
          class="
            ui-button
            ui-button--secondary
            ui-button--compact
          "
          :disabled="
            historyLoading
            || !historyPagination
              .hasNext
          "
          @click="
            changeHistoryPage(
              historyPagination.page
              + 1,
            )
          "
        >
          Suivant
        </button>
      </div>
    </section>

    <section
      v-if="insights"
      class="focus-insights"
    >
      <header class="focus-insights-heading">
        <div>
          <span class="pomodoro-kicker">
            Concentration
          </span>

          <h2>
            Vos repères
          </h2>

          <p class="muted">
            Votre progression et la durée
            qui semble vous convenir.
          </p>
        </div>
      </header>

      <div class="focus-insight-grid">
        <article
          class="
            panel
            focus-insight-card
          "
        >
          <span class="focus-insight-label">
            Concentration cumulée
          </span>

          <strong>
            {{
              formatDuration(
                insights.totalFocusSeconds,
              )
            }}
          </strong>

          <p class="muted">
            Temps total enregistré
            dans vos sessions.
          </p>
        </article>

        <article
          class="
            panel
            focus-insight-card
          "
        >
          <span class="focus-insight-label">
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
              Encore
              {{ ratingsNeeded }}
              session{{
                ratingsNeeded > 1
                  ? 's'
                  : ''
              }}
              à noter.
            </template>
          </p>

          <button
            v-if="
              insights.recommendedMinutes
              !== null
              && !store.active
            "
            type="button"
            class="
              ui-button
              ui-button--secondary
              ui-button--compact
              focus-insight-action
            "
            @click="useRecommendation"
          >
            Utiliser cette durée
          </button>
        </article>

        <article
          class="
            panel
            focus-insight-card
          "
        >
          <span class="focus-insight-label">
            Progression
          </span>

          <strong>
            {{ insights.stageLabel }}
          </strong>

          <div
            class="focus-insight-progress"
            role="progressbar"
            aria-label="Progression de concentration"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="
              insights.progressPercent
            "
          >
            <span
              :style="{
                width:
                  `${insights.progressPercent}%`,
              }"
            />
          </div>

          <p class="muted">
            {{
              insights.progressPercent
            }}
            % vers le prochain palier.
          </p>
        </article>
      </div>
    </section>
  </section>
</template>
