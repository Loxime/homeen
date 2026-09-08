<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
  watch,
} from 'vue'

import { api } from '../services/api'
import { formatDuration } from '../services/format'

import type {
  DailyStatistic,
  StatisticsResponse,
} from '../types/domain'

const month =
  ref(
    new Date()
      .toISOString()
      .slice(0, 7),
  )

const data =
  ref<StatisticsResponse | null>(null)

const loading = ref(false)
const error = ref('')

const maxDailyFocus =
  computed(
    () =>
      Math.max(
        1,
        ...(
          data.value?.days.map(
            day => day.focusSeconds,
          )
          ?? [1]
        ),
      ),
  )

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    data.value =
      await api<StatisticsResponse>(
        `/api/statistics?month=${encodeURIComponent(month.value)}`,
      )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les statistiques.'
  } finally {
    loading.value = false
  }
}

function changeLabel(
  key: string,
): string {
  const value =
    data.value?.changes[key]

  if (value === null) {
    return 'Nouveau par rapport au mois précédent'
  }

  if (value === undefined) {
    return ''
  }

  if (value === 0) {
    return 'Stable'
  }

  return `${value > 0 ? '+' : ''}${value}% par rapport au mois précédent`
}

function cardClass(
  key: string,
): string {
  const value =
    data.value?.changes[key]

  if (
    value === null
    || (
      value !== undefined
      && value > 0
    )
  ) {
    return 'positive'
  }

  if (
    value !== undefined
    && value < 0
  ) {
    return 'negative'
  }

  return 'neutral'
}

function dayLabel(
  day: DailyStatistic,
): string {
  return new Intl.DateTimeFormat(
    'fr-FR',
    {
      day: '2-digit',
    },
  ).format(
    new Date(
      `${day.date}T12:00:00`,
    ),
  )
}

watch(
  month,
  () => void load(),
)

onMounted(
  () => void load(),
)
</script>

<template>
  <section class="page stats-page">
    <header class="page-header stats-header">
      <div>
        <h1>
          Statistiques
        </h1>

        <p class="muted">
          Suivez votre concentration,
          vos tâches et votre activité
          au fil du mois.
        </p>
      </div>

      <label class="month-picker">
        <span>Mois</span>

        <input
          v-model="month"
          type="month"
        />
      </label>
    </header>

    <p
      v-if="error"
      class="form-error"
    >
      {{ error }}
    </p>

    <div
      v-if="loading || !data"
      class="empty-state"
    >
      Chargement des statistiques…
    </div>

    <template v-else>
      <div class="quality-gate">
        <div>
          <span class="quality-dot" />

          <div>
            <strong>
              {{
                data.summary.pomodoroSessions > 0
                || data.summary.tasksCompleted > 0
                  ? 'Activité enregistrée'
                  : 'Aucune activité pour le moment'
              }}
            </strong>

            <p class="muted">
              {{ data.month }}
              ·
              {{ data.timezone }}
            </p>
          </div>
        </div>
      </div>

      <div class="metric-grid">
        <article class="metric-card">
          <span>Temps de concentration</span>
          <strong>
            {{
              formatDuration(
                data.summary.focusSeconds,
              )
            }}
          </strong>
          <small :class="cardClass('focusSeconds')">
            {{ changeLabel('focusSeconds') }}
          </small>
        </article>

        <article class="metric-card">
          <span>Sessions Pomodoro</span>
          <strong>
            {{ data.summary.pomodoroSessions }}
          </strong>
          <small :class="cardClass('pomodoroSessions')">
            {{ changeLabel('pomodoroSessions') }}
          </small>
        </article>

        <article class="metric-card">
          <span>Tâches terminées</span>
          <strong>
            {{ data.summary.tasksCompleted }}
          </strong>
          <small :class="cardClass('tasksCompleted')">
            {{ changeLabel('tasksCompleted') }}
          </small>
        </article>

        <article class="metric-card">
          <span>Notes créées</span>
          <strong>
            {{ data.summary.notesCreated }}
          </strong>
          <small :class="cardClass('notesCreated')">
            {{ changeLabel('notesCreated') }}
          </small>
        </article>

        <article class="metric-card">
          <span>Temps dans l’application</span>
          <strong>
            {{
              formatDuration(
                data.summary.activeAppSeconds,
              )
            }}
          </strong>
          <small :class="cardClass('activeAppSeconds')">
            {{ changeLabel('activeAppSeconds') }}
          </small>
        </article>

        <article class="metric-card">
          <span>Taux de travail</span>
          <strong>
            {{ data.summary.focusEfficiency }}%
          </strong>
          <small :class="cardClass('focusEfficiency')">
            {{ changeLabel('focusEfficiency') }}
          </small>
        </article>
      </div>

      <div class="stats-split">
        <section class="panel evolution-panel">
          <div class="panel-heading">
            <h2>
              Concentration quotidienne
            </h2>

            <span>
              Total
              {{
                formatDuration(
                  data.summary.focusSeconds,
                )
              }}
            </span>
          </div>

          <div
            class="daily-chart"
            aria-label="Temps de concentration quotidien"
          >
            <div
              v-for="day in data.days"
              :key="day.date"
              class="day-column"
              :title="
                `${day.date}: ${formatDuration(day.focusSeconds)}`
              "
            >
              <div class="bar-track">
                <div
                  class="bar-fill"
                  :style="{
                    height:
                      `${Math.max(
                        day.focusSeconds > 0
                          ? 4
                          : 0,
                        (
                          day.focusSeconds
                          / maxDailyFocus
                        ) * 100,
                      )}%`,
                  }"
                />
              </div>

              <span>
                {{ dayLabel(day) }}
              </span>
            </div>
          </div>
        </section>

        <section class="panel insight-panel">
          <h2>
            Ce mois-ci
          </h2>

          <div class="insight-row">
            <span>Notes actuelles</span>
            <strong>
              {{ data.summary.noteCount }}
            </strong>
          </div>

          <div class="insight-row">
            <span>Temps de pause</span>
            <strong>
              {{
                formatDuration(
                  data.summary.breakSeconds,
                )
              }}
            </strong>
          </div>

          <div class="insight-row">
            <span>Libellé le plus terminé</span>
            <strong>
              {{
                data.mostCompletedLabel
                  ? `${data.mostCompletedLabel.labelName} (${data.mostCompletedLabel.count})`
                  : '—'
              }}
            </strong>
          </div>

          <div class="insight-row">
            <span>Efficacité de concentration</span>
            <strong>
              {{ data.summary.focusEfficiency }}%
            </strong>
          </div>
        </section>
      </div>

      <section class="panel daily-table-panel">
        <div class="panel-heading">
          <h2>
            Détail quotidien
          </h2>
        </div>

        <div class="daily-table">
          <div class="daily-row daily-head">
            <span>Date</span>
            <span>Sessions</span>
            <span>Concentration</span>
            <span>Tâches</span>
            <span>Notes</span>
            <span>Temps d’utilisation</span>
          </div>

          <div
            v-for="day in data.days"
            :key="day.date"
            class="daily-row"
          >
            <strong>
              {{ day.date }}
            </strong>

            <span>
              {{ day.pomodoroSessions }}
            </span>

            <span>
              {{
                formatDuration(
                  day.focusSeconds,
                )
              }}
            </span>

            <span>
              {{ day.tasksCompleted }}
            </span>

            <span>
              {{ day.notesCreated }}
            </span>

            <span>
              {{
                formatDuration(
                  day.activeAppSeconds,
                )
              }}
            </span>
          </div>
        </div>
      </section>
    </template>
  </section>
</template>
