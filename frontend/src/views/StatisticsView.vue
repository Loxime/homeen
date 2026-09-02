<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '../services/api'
import { formatDuration } from '../services/format'
import type { DailyStatistic, StatisticsResponse } from '../types/domain'

const month = ref(new Date().toISOString().slice(0, 7))
const data = ref<StatisticsResponse | null>(null)
const loading = ref(false)
const error = ref('')

const maxDailyFocus = computed(() => Math.max(1, ...(data.value?.days.map((day) => day.focusSeconds) ?? [1])))

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    data.value = await api<StatisticsResponse>(`/api/statistics?month=${encodeURIComponent(month.value)}`)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Unable to load statistics.'
  } finally {
    loading.value = false
  }
}

function changeLabel(key: string): string {
  const value = data.value?.changes[key]
  if (value === null) return 'New vs previous month'
  if (value === undefined) return ''
  if (value === 0) return 'No change'
  return `${value > 0 ? '+' : ''}${value}% vs previous month`
}

function cardClass(key: string): string {
  const value = data.value?.changes[key]
  if (value === null || (value !== undefined && value > 0)) return 'positive'
  if (value !== undefined && value < 0) return 'negative'
  return 'neutral'
}

function dayLabel(day: DailyStatistic): string {
  return new Intl.DateTimeFormat(undefined, { day: '2-digit' }).format(new Date(`${day.date}T12:00:00`))
}

watch(month, () => void load())
onMounted(() => void load())
</script>

<template>
  <section class="page stats-page">
    <header class="page-header stats-header">
      <div><p class="eyebrow">PROGRESSION</p><h1>Statistics</h1><p class="muted">Daily detail with month-over-month progression.</p></div>
      <label class="month-picker"><span>Month</span><input v-model="month" type="month" /></label>
    </header>

    <p v-if="error" class="form-error">{{ error }}</p>
    <div v-if="loading || !data" class="empty-state">Loading progression…</div>

    <template v-else>
      <div class="quality-gate">
        <div><span class="quality-dot" /><div><p class="eyebrow">MONTH STATUS</p><strong>{{ data.summary.pomodoroSessions > 0 || data.summary.tasksCompleted > 0 ? 'ACTIVITY RECORDED' : 'NO ACTIVITY YET' }}</strong></div></div>
        <span>{{ data.month }} · {{ data.timezone }}</span>
      </div>

      <div class="metric-grid">
        <article class="metric-card">
          <span>Focus time</span><strong>{{ formatDuration(data.summary.focusSeconds) }}</strong>
          <small :class="cardClass('focusSeconds')">{{ changeLabel('focusSeconds') }}</small>
        </article>
        <article class="metric-card">
          <span>Pomodoro sessions</span><strong>{{ data.summary.pomodoroSessions }}</strong>
          <small :class="cardClass('pomodoroSessions')">{{ changeLabel('pomodoroSessions') }}</small>
        </article>
        <article class="metric-card">
          <span>Tasks checked</span><strong>{{ data.summary.tasksCompleted }}</strong>
          <small :class="cardClass('tasksCompleted')">{{ changeLabel('tasksCompleted') }}</small>
        </article>
        <article class="metric-card">
          <span>Notes created</span><strong>{{ data.summary.notesCreated }}</strong>
          <small :class="cardClass('notesCreated')">{{ changeLabel('notesCreated') }}</small>
        </article>
        <article class="metric-card">
          <span>Application time</span><strong>{{ formatDuration(data.summary.activeAppSeconds) }}</strong>
          <small :class="cardClass('activeAppSeconds')">{{ changeLabel('activeAppSeconds') }}</small>
        </article>
        <article class="metric-card">
          <span>Work rate</span><strong>{{ data.summary.focusEfficiency }}%</strong>
          <small :class="cardClass('focusEfficiency')">{{ changeLabel('focusEfficiency') }}</small>
        </article>
      </div>

      <div class="stats-split">
        <section class="panel evolution-panel">
          <div class="panel-heading"><div><p class="eyebrow">DAILY EVOLUTION</p><h2>Focused work</h2></div><span>Total {{ formatDuration(data.summary.focusSeconds) }}</span></div>
          <div class="daily-chart" aria-label="Daily focus time chart">
            <div v-for="day in data.days" :key="day.date" class="day-column" :title="`${day.date}: ${formatDuration(day.focusSeconds)}`">
              <div class="bar-track"><div class="bar-fill" :style="{ height: `${Math.max(day.focusSeconds > 0 ? 4 : 0, (day.focusSeconds / maxDailyFocus) * 100)}%` }" /></div>
              <span>{{ dayLabel(day) }}</span>
            </div>
          </div>
        </section>

        <section class="panel insight-panel">
          <p class="eyebrow">MONTH INSIGHTS</p>
          <div class="insight-row"><span>Current notes</span><strong>{{ data.summary.noteCount }}</strong></div>
          <div class="insight-row"><span>Fixed break time</span><strong>{{ formatDuration(data.summary.breakSeconds) }}</strong></div>
          <div class="insight-row"><span>Most resolved label</span><strong>{{ data.mostCompletedLabel ? `${data.mostCompletedLabel.labelName} (${data.mostCompletedLabel.count})` : '—' }}</strong></div>
          <div class="insight-row"><span>Focus efficiency</span><strong>{{ data.summary.focusEfficiency }}%</strong></div>
          <p class="metric-note">Work rate = focused Pomodoro time ÷ (focused time + fixed breaks). This keeps the score bounded and independent from whether the app tab is visible.</p>
        </section>
      </div>

      <section class="panel daily-table-panel">
        <div class="panel-heading"><div><p class="eyebrow">DAILY LOG</p><h2>Every day of {{ data.month }}</h2></div></div>
        <div class="daily-table">
          <div class="daily-row daily-head"><span>Date</span><span>Sessions</span><span>Focus</span><span>Tasks checked</span><span>Notes</span><span>App time</span></div>
          <div v-for="day in data.days" :key="day.date" class="daily-row">
            <strong>{{ day.date }}</strong><span>{{ day.pomodoroSessions }}</span><span>{{ formatDuration(day.focusSeconds) }}</span><span>{{ day.tasksCompleted }}</span><span>{{ day.notesCreated }}</span><span>{{ formatDuration(day.activeAppSeconds) }}</span>
          </div>
        </div>
      </section>
    </template>
  </section>
</template>
