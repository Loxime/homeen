export interface ImageAsset {
  id: number
  originalName: string
  mimeType: string
  sizeBytes: number
  width: number | null
  height: number | null
  createdAt: string
  noteCount: number
  contentUrl: string
}

export type ProjectRole =
  | 'owner'
  | 'admin'
  | 'member'

export interface Project {
  id: number
  name: string
  description: string
  color: string
  imageUrl: string | null
  role: ProjectRole
  memberCount: number
  noteCount: number
  createdAt: string
  updatedAt: string
  archivedAt: string | null
}

export interface ProjectWorkflowStage {
  id: number
  projectId: number
  name: string
  position: number
  createdAt: string
  updatedAt: string
}

export interface ProjectTask {
  id: number
  noteId: null
  projectId: number
  workflowStageId: number
  workflowStageName: string
  workflowStagePosition: number
  content: string
  priority: TaskPriority
  status: TaskStatus
  position: number
  startDate: string | null
  dueDate: string | null
  isCompleted: boolean
  completedAt: string | null
  createdAt: string
  updatedAt: string
  tags: Tag[]
}

export interface NoteCollection {
  id: number
  name: string
  color: string
  createdAt: string
  updatedAt: string
  noteCount: number
}

export type TaskPriority =
  | 'low'
  | 'normal'
  | 'high'
  | 'urgent'

export type TaskStatus =
  | 'todo'
  | 'in_progress'
  | 'done'

export interface Tag {
  id: number
  name: string
  color: string
  createdAt?: string
  updatedAt?: string
  taskCount?: number
  noteCount?: number
}

export interface Task {
  id: number
  noteId: number
  content: string
  priority: TaskPriority
  status: TaskStatus
  position: number
  startDate: string | null
  dueDate: string | null
  isCompleted: boolean
  completedAt: string | null
  createdAt: string
  updatedAt: string
  tags: Tag[]
}

export interface NoteSummary {
  id: number
  title: string
  content: string
  isPinned: boolean
  color: string
  previewImageUrl: string | null
  tags: Tag[]
  collectionId: number | null
  collectionName: string | null
  collectionColor: string | null
  projectId: number | null
  projectName: string | null
  projectColor: string | null
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
  focusRating?: number | null
  ratedAt?: string | null
  phase?: 'work' | 'break' | null
  remainingSeconds?: number
  completedWorkCycles?: number
  completedBreakCycles?: number
  isActive?: boolean
}

export interface PomodoroInsights {
  totalFocusSeconds: number
  totalFocusMinutes: number
  stage:
    | 'roots'
    | 'sprout'
    | 'sapling'
    | 'tree'
  stageLabel: string
  progressPercent: number
  nextStageMinutes: number | null
  recommendedMinutes: number | null
  ratingCount: number
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

export interface StatisticRange {
  start: string
  end: string
}

export interface StatisticsResponse {
  range: StatisticRange
  comparisonRange: StatisticRange
  timezone: string
  summary: StatisticSummary
  comparison: StatisticSummary
  changes: Record<string, number | null>
  days: DailyStatistic[]
  mostCompletedTag: {
    tagName: string
    count: number
  } | null
}
