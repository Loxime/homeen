<script setup lang="ts">
import {
  watchErrorToast,
} from '../composables/useErrorToast'

import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from 'vue'

import Chart from 'chart.js/auto'

import { api } from '../services/api'

import {
  useTheme,
} from '../composables/useTheme'

import {
  formatDuration,
} from '../services/format'

import {
  buildStatisticsSeries,
} from '../services/statisticsSeries'

import type {
  StatisticsResponse,
} from '../types/domain'

function inputDate(
  date: Date,
): string {
  const local =
    new Date(
      date.getTime()
      - date.getTimezoneOffset()
      * 60_000,
    )

  return local
    .toISOString()
    .slice(0, 10)
}

function addDays(
  value: string,
  days: number,
): string {
  const date =
    new Date(
      `${value}T12:00:00`,
    )

  date.setDate(
    date.getDate()
    + days,
  )

  return inputDate(date)
}

const {
  theme,
} = useTheme()

const today =
  inputDate(
    new Date(),
  )

const monthStart =
  `${today.slice(0, 8)}01`

const periodStart =
  ref(monthStart)

const periodEnd =
  ref(today)

const initialPeriodDays =
  Math.max(
    1,
    Math.round(
      (
        new Date(
          `${today}T12:00:00`,
        ).getTime()
        - new Date(
          `${monthStart}T12:00:00`,
        ).getTime()
      ) / 86_400_000,
    ) + 1,
  )

const initialCompareEnd =
  addDays(
    monthStart,
    -1,
  )

const initialCompareStart =
  addDays(
    initialCompareEnd,
    -(initialPeriodDays - 1),
  )

const compareStart =
  ref(initialCompareStart)

const compareEnd =
  ref(initialCompareEnd)

const data =
  ref<StatisticsResponse | null>(
    null,
  )

const loading = ref(false)
const error = ref('')

watchErrorToast(error)

const comparisonCanvas =
  ref<HTMLCanvasElement | null>(
    null,
  )

let comparisonChart:
  Chart | null = null

const series =
  computed(
    () =>
      data.value
        ? buildStatisticsSeries(
            data.value.days,
          )
        : [],
  )

const maxFocus =
  computed(
    () =>
      Math.max(
        1,
        ...series.value.map(
          point =>
            point.focusSeconds,
        ),
      ),
  )

const seriesGranularity =
  computed(
    () =>
      (
        data.value?.days.length
        ?? 0
      ) > 90
        ? 'mensuelle'
        : 'quotidienne',
  )

const comparisonSeries =
  computed(
    () =>
      data.value
        ? buildStatisticsSeries(
            data.value.comparisonDays,
          )
        : [],
  )

function renderComparisonChart():
void {
  comparisonChart?.destroy()
  comparisonChart = null

  if (
    !comparisonCanvas.value
    || !data.value
  ) {
    return
  }

  const current =
    series.value

  const comparison =
    comparisonSeries.value

  const pointCount =
    Math.max(
      current.length,
      comparison.length,
    )

  const rootStyles =
    getComputedStyle(
      document.documentElement,
    )

  const chartTextColor =
    rootStyles
      .getPropertyValue(
        '--g-muted',
      )
      .trim()
    || '#5f6368'

  const chartGridColor =
    rootStyles
      .getPropertyValue(
        '--g-border',
      )
      .trim()
    || '#e0e3e7'

  comparisonChart =
    new Chart(
      comparisonCanvas.value,
      {
        type: 'line',

        data: {
          labels:
            Array.from(
              {
                length: pointCount,
              },
              (_, index) =>
                `${index + 1}`,
            ),

          datasets: [
            {
              label: 'Période A',

              data:
                current.map(
                  point =>
                    Math.round(
                      point.focusSeconds
                      / 60,
                    ),
                ),

              borderWidth: 2,
              tension: 0.28,
              pointRadius: 2,
              pointHoverRadius: 5,
            },

            {
              label: 'Période B',

              data:
                comparison.map(
                  point =>
                    Math.round(
                      point.focusSeconds
                      / 60,
                    ),
                ),

              borderWidth: 2,
              tension: 0.28,
              pointRadius: 2,
              pointHoverRadius: 5,
            },
          ],
        },

        options: {
          responsive: true,
          maintainAspectRatio: false,

          interaction: {
            intersect: false,
            mode: 'index',
          },

          plugins: {
            legend: {
              position: 'bottom',

              labels: {
                color:
                  chartTextColor,
              },
            },

            tooltip: {
              callbacks: {
                title: items => {
                  const index =
                    items[0]?.dataIndex
                    ?? 0

                  const currentPoint =
                    current[index]

                  const comparisonPoint =
                    comparison[index]

                  return [
                    currentPoint
                      ? `A · ${currentPoint.label}`
                      : null,

                    comparisonPoint
                      ? `B · ${comparisonPoint.label}`
                      : null,
                  ].filter(
                    (
                      value,
                    ): value is string =>
                      value !== null,
                  )
                },

                label: context =>
                  `${
                    context.dataset.label
                    ?? ''
                  } : ${
                    context.parsed.y
                    ?? 0
                  } min`,
              },
            },
          },

          scales: {
            x: {
              ticks: {
                color:
                  chartTextColor,
              },

              title: {
                display: true,

                color:
                  chartTextColor,

                text:
                  seriesGranularity.value
                  === 'mensuelle'
                    ? 'Mois relatifs'
                    : 'Jours relatifs',
              },

              grid: {
                display: false,
              },
            },

            y: {
              beginAtZero: true,

              ticks: {
                color:
                  chartTextColor,
              },

              grid: {
                color:
                  chartGridColor,
              },

              title: {
                display: true,

                color:
                  chartTextColor,

                text:
                  'Concentration (minutes)',
              },
            },
          },
        },
      },
    )
}

async function load():
Promise<void> {
  loading.value = true
  error.value = ''

  try {
    if (
      !periodStart.value
      || !periodEnd.value
      || !compareStart.value
      || !compareEnd.value
    ) {
      throw new Error(
        'Renseignez les deux périodes à comparer.',
      )
    }

    const params =
      new URLSearchParams({
        start:
          periodStart.value,

        end:
          periodEnd.value,

        compareStart:
          compareStart.value,

        compareEnd:
          compareEnd.value,
      })

    data.value =
      await api<StatisticsResponse>(
        `/api/statistics?${params}`,
      )

    await nextTick()
    renderComparisonChart()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les statistiques.'
  } finally {
    loading.value = false
  }
}

async function setPreset(
  days: number,
): Promise<void> {
  periodEnd.value = today

  periodStart.value =
    addDays(
      today,
      -(days - 1),
    )

  compareEnd.value =
    addDays(
      periodStart.value,
      -1,
    )

  compareStart.value =
    addDays(
      compareEnd.value,
      -(days - 1),
    )

  await load()
}

function changeLabel(
  key: string,
): string {
  const value =
    data.value?.changes[key]

  if (value === null) {
    return 'Nouveau par rapport à la période comparée'
  }

  if (value === undefined) {
    return ''
  }

  if (value === 0) {
    return 'Stable'
  }

  return `${value > 0 ? '+' : ''}${value}% vs période comparée`
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

function readableRange(
  start: string,
  end: string,
): string {
  const formatter =
    new Intl.DateTimeFormat(
      'fr-FR',
      {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
      },
    )

  return `${
    formatter.format(
      new Date(
        `${start}T12:00:00`,
      ),
    )
  } → ${
    formatter.format(
      new Date(
        `${end}T12:00:00`,
      ),
    )
  }`
}

watch(
  theme,
  async () => {
    await nextTick()

    renderComparisonChart()
  },
)

onMounted(
  () => void load(),
)

onBeforeUnmount(() => {
  comparisonChart?.destroy()
  comparisonChart = null
})
</script>

<template>
  <section class="page stats-page">
    <header class="page-header stats-header">
      <div>
        <h1>
          Statistiques
        </h1>

        <p class="muted">
          Analysez n’importe quelle période
          et comparez-la à une autre :
          mois, trimestre, année ou dates libres.
        </p>
      </div>
    </header>

    <section class="panel stats-period-controls">
      <div class="stats-preset-row">
        <strong>
          Raccourcis
        </strong>

        <div>
          <button
            class="ui-button ui-button--secondary ui-button--compact"
            type="button"
            @click="setPreset(30)"
          >
            30 jours
          </button>

          <button
            class="ui-button ui-button--secondary ui-button--compact"
            type="button"
            @click="setPreset(90)"
          >
            3 mois
          </button>

          <button
            class="ui-button ui-button--secondary ui-button--compact"
            type="button"
            @click="setPreset(365)"
          >
            1 an
          </button>
        </div>
      </div>

      <form
        class="period-compare-grid"
        @submit.prevent="load"
      >
        <fieldset>
          <legend>
            Période A
          </legend>

          <label>
            Du

            <input
              v-model="periodStart"
              type="date"
              required
            />
          </label>

          <label>
            Au

            <input
              v-model="periodEnd"
              type="date"
              required
            />
          </label>
        </fieldset>

        <div
          class="period-versus"
          aria-hidden="true"
        >
          VS
        </div>

        <fieldset>
          <legend>
            Période B
          </legend>

          <label>
            Du

            <input
              v-model="compareStart"
              type="date"
              required
            />
          </label>

          <label>
            Au

            <input
              v-model="compareEnd"
              type="date"
              required
            />
          </label>
        </fieldset>

        <button
          class="ui-button ui-button--primary period-load"
          :disabled="loading"
        >
          {{
            loading
              ? 'Calcul…'
              : 'Comparer'
          }}
        </button>
      </form>

      <p class="muted period-help">
        Vous pouvez par exemple comparer
        2026 à 2024 en choisissant
        manuellement les deux années.
      </p>
    </section>

    <p
      v-if="error"
      class="form-error"
    >
      {{ error }}
    </p>

    <div
      v-if="
        loading
        && !data
      "
      class="empty-state"
    >
      Chargement des statistiques…
    </div>

    <template v-else-if="data">
      <div class="quality-gate">
        <div>
          <span class="quality-dot" />

          <div>
            <strong>
              {{
                readableRange(
                  data.range.start,
                  data.range.end,
                )
              }}
            </strong>

            <p class="muted">
              Comparé à
              {{
                readableRange(
                  data.comparisonRange.start,
                  data.comparisonRange.end,
                )
              }}
            </p>
          </div>
        </div>
      </div>

      <div class="metric-grid">
        <article class="metric-card">
          <span>
            Temps de concentration
          </span>

          <strong>
            {{
              formatDuration(
                data.summary
                  .focusSeconds,
              )
            }}
          </strong>

          <small
            :class="
              cardClass(
                'focusSeconds',
              )
            "
          >
            {{
              changeLabel(
                'focusSeconds',
              )
            }}
          </small>
        </article>

        <article class="metric-card">
          <span>
            Sessions Pomodoro
          </span>

          <strong>
            {{
              data.summary
                .pomodoroSessions
            }}
          </strong>

          <small
            :class="
              cardClass(
                'pomodoroSessions',
              )
            "
          >
            {{
              changeLabel(
                'pomodoroSessions',
              )
            }}
          </small>
        </article>

        <article class="metric-card">
          <span>
            Tâches terminées
          </span>

          <strong>
            {{
              data.summary
                .tasksCompleted
            }}
          </strong>

          <small
            :class="
              cardClass(
                'tasksCompleted',
              )
            "
          >
            {{
              changeLabel(
                'tasksCompleted',
              )
            }}
          </small>
        </article>

        <article class="metric-card">
          <span>
            Notes créées
          </span>

          <strong>
            {{
              data.summary
                .notesCreated
            }}
          </strong>

          <small
            :class="
              cardClass(
                'notesCreated',
              )
            "
          >
            {{
              changeLabel(
                'notesCreated',
              )
            }}
          </small>
        </article>

        <article class="metric-card">
          <span>
            Temps dans l’application
          </span>

          <strong>
            {{
              formatDuration(
                data.summary
                  .activeAppSeconds,
              )
            }}
          </strong>

          <small
            :class="
              cardClass(
                'activeAppSeconds',
              )
            "
          >
            {{
              changeLabel(
                'activeAppSeconds',
              )
            }}
          </small>
        </article>

        <article class="metric-card">
          <span>
            Taux de travail
          </span>

          <strong>
            {{
              data.summary
                .focusEfficiency
            }}%
          </strong>

          <small
            :class="
              cardClass(
                'focusEfficiency',
              )
            "
          >
            {{
              changeLabel(
                'focusEfficiency',
              )
            }}
          </small>
        </article>
      </div>

      <div class="stats-split">
        <section class="panel evolution-panel">
          <div class="panel-heading">
            <div>
              <h2>
                Évolution
                {{ seriesGranularity }}
              </h2>

              <p class="muted">
                Les périodes longues
                sont regroupées par mois
                pour rester lisibles.
              </p>
            </div>

            <span>
              Total
              {{
                formatDuration(
                  data.summary
                    .focusSeconds,
                )
              }}
            </span>
          </div>

          <div
            class="daily-chart period-chart"
            aria-label="Temps de concentration sur la période"
          >
            <div
              v-for="point in series"
              :key="point.key"
              class="day-column"
              :title="
                `${point.label}: ${formatDuration(point.focusSeconds)}`
              "
            >
              <div class="bar-track">
                <div
                  class="bar-fill"
                  :style="{
                    height:
                      `${Math.max(
                        point.focusSeconds > 0
                          ? 4
                          : 0,
                        (
                          point.focusSeconds
                          / maxFocus
                        ) * 100,
                      )}%`,
                  }"
                />
              </div>

              <span>
                {{ point.label }}
              </span>
            </div>
          </div>
        </section>

        <section
          class="
            panel
            insight-panel
            comparison-chart-panel
          "
        >
          <div class="panel-heading">
            <div>
              <h2>
                Comparaison
              </h2>

              <p class="muted">
                Concentration de la période A
                face à la période B.
              </p>
            </div>
          </div>

          <div class="comparison-chart-wrap">
            <canvas
              ref="comparisonCanvas"
              aria-label="Comparaison des périodes A et B"
            />
          </div>

          <div class="comparison-summary">
            <div>
              <span>
                Concentration A
              </span>

              <strong>
                {{
                  formatDuration(
                    data.summary
                      .focusSeconds,
                  )
                }}
              </strong>
            </div>

            <div>
              <span>
                Concentration B
              </span>

              <strong>
                {{
                  formatDuration(
                    data.comparison
                      .focusSeconds,
                  )
                }}
              </strong>
            </div>

            <div>
              <span>
                Tâches A / B
              </span>

              <strong>
                {{
                  data.summary
                    .tasksCompleted
                }}
                /
                {{
                  data.comparison
                    .tasksCompleted
                }}
              </strong>
            </div>

            <div>
              <span>
                Tag le plus terminé
              </span>

              <strong>
                {{
                  data.mostCompletedTag
                    ? `${data.mostCompletedTag.tagName} (${data.mostCompletedTag.count})`
                    : '—'
                }}
              </strong>
            </div>
          </div>
        </section>
      </div>

      <section class="panel daily-table-panel">
        <div class="panel-heading">
          <div>
            <h2>
              Détail
              {{ seriesGranularity }}
            </h2>
          </div>
        </div>

        <div class="daily-table">
          <div class="daily-row daily-head">
            <span>Période</span>
            <span>Sessions</span>
            <span>Concentration</span>
            <span>Tâches</span>
            <span>Notes</span>
            <span>
              Temps d’utilisation
            </span>
          </div>

          <div
            v-for="point in series"
            :key="point.key"
            class="daily-row"
          >
            <strong>
              {{ point.label }}
            </strong>

            <span>
              {{
                point
                  .pomodoroSessions
              }}
            </span>

            <span>
              {{
                formatDuration(
                  point.focusSeconds,
                )
              }}
            </span>

            <span>
              {{
                point.tasksCompleted
              }}
            </span>

            <span>
              {{
                point.notesCreated
              }}
            </span>

            <span>
              {{
                formatDuration(
                  point.activeAppSeconds,
                )
              }}
            </span>
          </div>
        </div>
      </section>
    </template>
  </section>
</template>
