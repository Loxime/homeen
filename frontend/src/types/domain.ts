export interface Label {
  id: number
  name: string
  color: string
  createdAt: string
  updatedAt: string
  noteCount: number
}

export interface Task {
  id: number
  content: string
  isCompleted: boolean
  completedAt: string | null
  createdAt: string
  updatedAt: string
}

export interface NoteSummary {
  id: number
  title: string
  content: string
  labelId: number | null
  labelName: string | null
  labelColor: string | null
  createdAt: string
  updatedAt: string
  archivedAt: string | null
  deletedAt: string | null
  taskCount: number
  completedTaskCount: number
}

export interface Note extends Omit<NoteSummary, 'taskCount' | 'completedTaskCount'> {
  tasks: Task[]
}

export interface PomodoroPreset {
  id: number
  workMinutes: number
  createdAt: string
  lastUsedAt: string
}

export interface PomodoroSession {
  id: number
  presetId?: number | null
  workMinutes: number
  startedAt: string
  stoppedAt: string | null
  focusSeconds: number
  breakSeconds: number
  phase?: 'work' | 'break' | null
  remainingSeconds?: number
  completedWorkCycles?: number
  completedBreakCycles?: number
  isActive?: boolean
}

export interface DailyStatistic {
  date: string
  pomodoroSessions: number
  focusSeconds: number
  breakSeconds: number
  tasksCompleted: number
  notesCreated: number
  activeAppSeconds: number
}

export interface StatisticSummary {
  pomodoroSessions: number
  focusSeconds: number
  breakSeconds: number
  focusEfficiency: number
  tasksCompleted: number
  notesCreated: number
  noteCount: number
  activeAppSeconds: number
}

export interface StatisticsResponse {
  month: string
  timezone: string
  summary: StatisticSummary
  previous: StatisticSummary
  changes: Record<string, number | null>
  days: DailyStatistic[]
  mostCompletedLabel: { labelName: string; count: number } | null
}
