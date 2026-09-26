<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
} from 'vue'
import {
  useRoute,
} from 'vue-router'

import { api } from '../services/api'

import type {
  Project,
  ProjectTask,
  ProjectWorkflowStage,
  TaskPriority,
  TaskStatus,
} from '../types/domain'

const route = useRoute()

const project =
  ref<Project | null>(null)

const stages =
  ref<ProjectWorkflowStage[]>([])

const tasks =
  ref<ProjectTask[]>([])

const loading = ref(true)
const error = ref('')

const taskContents =
  ref<Record<number, string>>({})

const creatingTaskStageId =
  ref<number | null>(null)

const busyTaskId =
  ref<number | null>(null)

const stageName = ref('')
const creatingStage = ref(false)

const busyStageId =
  ref<number | null>(null)

const projectId =
  computed(
    () => Number(
      route.params.id,
    ),
  )

const canManageWorkflow =
  computed(
    () =>
      project.value?.role === 'owner'
      || project.value?.role === 'admin',
  )

function priorityLabel(
  priority: TaskPriority,
): string {
  return {
    low: 'Basse',
    normal: 'Normale',
    high: 'Haute',
    urgent: 'Urgente',
  }[priority]
}

function statusLabel(
  status: TaskStatus,
): string {
  return {
    todo: 'À faire',
    in_progress: 'En cours',
    done: 'Terminée',
  }[status]
}

function tasksForStage(
  stageId: number,
): ProjectTask[] {
  return tasks.value
    .filter(
      task =>
        task.workflowStageId
        === stageId,
    )
    .sort(
      (first, second) =>
        first.position
        - second.position
        || first.id
        - second.id,
    )
}

async function load(): Promise<void> {
  if (
    !Number.isInteger(
      projectId.value,
    )
    || projectId.value <= 0
  ) {
    error.value =
      'Projet invalide.'

    loading.value = false

    return
  }

  loading.value = true
  error.value = ''

  try {
    const [
      projectResponse,
      workflowResponse,
      taskResponse,
    ] = await Promise.all([
      api<Project>(
        `/api/projects/${projectId.value}`,
      ),

      api<{
        stages:
          ProjectWorkflowStage[]
      }>(
        `/api/projects/${projectId.value}/workflow`,
      ),

      api<{
        tasks: ProjectTask[]
      }>(
        `/api/projects/${projectId.value}/tasks`,
      ),
    ])

    project.value =
      projectResponse

    stages.value =
      workflowResponse.stages

    tasks.value =
      taskResponse.tasks
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger le projet.'
  } finally {
    loading.value = false
  }
}

async function reloadWorkflow():
Promise<void> {
  const response =
    await api<{
      stages:
        ProjectWorkflowStage[]
    }>(
      `/api/projects/${projectId.value}/workflow`,
    )

  stages.value =
    response.stages
}

async function createTask(
  stage: ProjectWorkflowStage,
): Promise<void> {
  const content =
    (
      taskContents.value[
        stage.id
      ] ?? ''
    ).trim()

  if (
    content === ''
    || creatingTaskStageId.value
        !== null
  ) {
    return
  }

  creatingTaskStageId.value =
    stage.id

  error.value = ''

  try {
    const created =
      await api<ProjectTask>(
        `/api/projects/${projectId.value}/tasks`,
        {
          method: 'POST',

          body: JSON.stringify({
            content,
            workflowStageId:
              stage.id,
          }),
        },
      )

    tasks.value = [
      ...tasks.value,
      created,
    ]

    taskContents.value[
      stage.id
    ] = ''
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de créer la tâche.'
  } finally {
    creatingTaskStageId.value =
      null
  }
}

async function moveTask(
  task: ProjectTask,
  direction: -1 | 1,
): Promise<void> {
  const currentIndex =
    stages.value.findIndex(
      stage =>
        stage.id
        === task.workflowStageId,
    )

  const target =
    stages.value[
      currentIndex + direction
    ]

  if (
    currentIndex < 0
    || target === undefined
    || busyTaskId.value !== null
  ) {
    return
  }

  busyTaskId.value =
    task.id

  error.value = ''

  try {
    const updated =
      await api<ProjectTask>(
        `/api/projects/${projectId.value}/tasks/${task.id}`,
        {
          method: 'PUT',

          body: JSON.stringify({
            workflowStageId:
              target.id,
          }),
        },
      )

    tasks.value =
      tasks.value.map(
        candidate =>
          candidate.id === task.id
            ? updated
            : candidate,
      )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de déplacer la tâche.'
  } finally {
    busyTaskId.value = null
  }
}

async function toggleTask(
  task: ProjectTask,
): Promise<void> {
  if (busyTaskId.value !== null) {
    return
  }

  busyTaskId.value =
    task.id

  error.value = ''

  try {
    const updated =
      await api<ProjectTask>(
        `/api/projects/${projectId.value}/tasks/${task.id}/completed`,
        {
          method: 'PUT',

          body: JSON.stringify({
            completed:
              !task.isCompleted,
          }),
        },
      )

    tasks.value =
      tasks.value.map(
        candidate =>
          candidate.id === task.id
            ? updated
            : candidate,
      )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier la tâche.'
  } finally {
    busyTaskId.value = null
  }
}

async function deleteTask(
  task: ProjectTask,
): Promise<void> {
  if (
    busyTaskId.value !== null
    || !window.confirm(
      'Supprimer cette tâche ?',
    )
  ) {
    return
  }

  busyTaskId.value =
    task.id

  error.value = ''

  try {
    await api(
      `/api/projects/${projectId.value}/tasks/${task.id}`,
      {
        method: 'DELETE',
      },
    )

    tasks.value =
      tasks.value.filter(
        candidate =>
          candidate.id !== task.id,
      )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de supprimer la tâche.'
  } finally {
    busyTaskId.value = null
  }
}

async function createStage():
Promise<void> {
  const name =
    stageName.value.trim()

  if (
    name === ''
    || !canManageWorkflow.value
    || creatingStage.value
  ) {
    return
  }

  creatingStage.value = true
  error.value = ''

  try {
    await api(
      `/api/projects/${projectId.value}/workflow/stages`,
      {
        method: 'POST',

        body: JSON.stringify({
          name,
        }),
      },
    )

    stageName.value = ''

    await reloadWorkflow()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’ajouter la colonne.'
  } finally {
    creatingStage.value = false
  }
}

async function renameStage(
  stage: ProjectWorkflowStage,
): Promise<void> {
  if (
    !canManageWorkflow.value
    || busyStageId.value !== null
  ) {
    return
  }

  const name =
    window.prompt(
      'Nouveau nom de la colonne',
      stage.name,
    )?.trim()

  if (
    name === undefined
    || name === ''
    || name === stage.name
  ) {
    return
  }

  busyStageId.value =
    stage.id

  error.value = ''

  try {
    await api(
      `/api/projects/${projectId.value}/workflow/stages/${stage.id}`,
      {
        method: 'PUT',

        body: JSON.stringify({
          name,
        }),
      },
    )

    await reloadWorkflow()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de renommer la colonne.'
  } finally {
    busyStageId.value = null
  }
}

async function deleteStage(
  stage: ProjectWorkflowStage,
): Promise<void> {
  if (
    !canManageWorkflow.value
    || busyStageId.value !== null
    || !window.confirm(
      `Supprimer la colonne « ${stage.name} » ?`,
    )
  ) {
    return
  }

  busyStageId.value =
    stage.id

  error.value = ''

  try {
    await api(
      `/api/projects/${projectId.value}/workflow/stages/${stage.id}`,
      {
        method: 'DELETE',
      },
    )

    await reloadWorkflow()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de supprimer la colonne.'
  } finally {
    busyStageId.value = null
  }
}

async function moveStage(
  stage: ProjectWorkflowStage,
  direction: -1 | 1,
): Promise<void> {
  if (
    !canManageWorkflow.value
    || busyStageId.value !== null
  ) {
    return
  }

  const index =
    stages.value.findIndex(
      candidate =>
        candidate.id === stage.id,
    )

  const targetIndex =
    index + direction

  if (
    index < 0
    || targetIndex < 0
    || targetIndex
        >= stages.value.length
  ) {
    return
  }

  const ordered =
    [...stages.value]

  const [
    moved,
  ] = ordered.splice(
    index,
    1,
  )

  if (moved === undefined) {
    return
  }

  ordered.splice(
    targetIndex,
    0,
    moved,
  )

  busyStageId.value =
    stage.id

  error.value = ''

  try {
    const response =
      await api<{
        stages:
          ProjectWorkflowStage[]
      }>(
        `/api/projects/${projectId.value}/workflow/order`,
        {
          method: 'PUT',

          body: JSON.stringify({
            stageIds:
              ordered.map(
                candidate =>
                  candidate.id,
              ),
          }),
        },
      )

    stages.value =
      response.stages
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de déplacer la colonne.'
  } finally {
    busyStageId.value = null
  }
}

onMounted(
  () => void load(),
)
</script>

<template>
  <section class="page project-board-page">
    <div
      v-if="loading"
      class="empty-state"
    >
      Chargement du projet…
    </div>

    <template v-else-if="project">
      <header class="project-board-header">
        <div>
          <RouterLink
            class="project-back-link"
            to="/projects"
          >
            ← Projets
          </RouterLink>

          <div class="project-title-row">
            <span
              class="project-board-dot"
              :style="{
                background:
                  project.color,
              }"
            />

            <h1>
              {{ project.name }}
            </h1>
          </div>

          <p
            v-if="project.description"
            class="muted"
          >
            {{ project.description }}
          </p>
        </div>

        <RouterLink
          class="ui-button ui-button--secondary"
          :to="{
            path: '/notes',
            query: {
              projectId:
                String(project.id),
            },
          }"
        >
          Notes du projet
        </RouterLink>
      </header>

      <p
        v-if="error"
        class="form-error"
      >
        {{ error }}
      </p>

      <form
        v-if="canManageWorkflow"
        class="workflow-create-form"
        @submit.prevent="createStage"
      >
        <input
          v-model="stageName"
          maxlength="80"
          placeholder="Nouvelle colonne"
        />

        <button
          class="ui-button ui-button--secondary"
          :disabled="
            creatingStage
            || !stageName.trim()
          "
        >
          {{
            creatingStage
              ? 'Ajout…'
              : 'Ajouter une colonne'
          }}
        </button>

        <small class="muted">
          {{ stages.length }}/20 colonnes
        </small>
      </form>

      <div
        v-if="stages.length === 0"
        class="empty-state"
      >
        Ce projet n’a aucune colonne.
      </div>

      <div
        v-else
        class="project-board"
      >
        <section
          v-for="(
            stage,
            stageIndex
          ) in stages"
          :key="stage.id"
          class="project-column"
        >
          <header class="project-column-header">
            <div>
              <strong>
                {{ stage.name }}
              </strong>

              <small class="muted">
                {{
                  tasksForStage(
                    stage.id,
                  ).length
                }}
                tâche{{
                  tasksForStage(
                    stage.id,
                  ).length > 1
                    ? 's'
                    : ''
                }}
              </small>
            </div>

            <div
              v-if="canManageWorkflow"
              class="workflow-actions"
            >
              <button
                type="button"
                title="Déplacer à gauche"
                :disabled="
                  stageIndex === 0
                  || busyStageId !== null
                "
                @click="
                  moveStage(stage, -1)
                "
              >
                ←
              </button>

              <button
                type="button"
                title="Déplacer à droite"
                :disabled="
                  stageIndex
                    === stages.length - 1
                  || busyStageId !== null
                "
                @click="
                  moveStage(stage, 1)
                "
              >
                →
              </button>

              <button
                type="button"
                title="Renommer"
                :disabled="
                  busyStageId !== null
                "
                @click="
                  renameStage(stage)
                "
              >
                ✎
              </button>

              <button
                type="button"
                title="Supprimer"
                :disabled="
                  busyStageId !== null
                "
                @click="
                  deleteStage(stage)
                "
              >
                ×
              </button>
            </div>
          </header>

          <div class="project-task-list">
            <article
              v-for="
                task
                in tasksForStage(
                  stage.id,
                )
              "
              :key="task.id"
              class="project-task-card"
              :class="{
                'project-task-card--done':
                  task.isCompleted,
              }"
            >
              <div class="project-task-heading">
                <button
                  class="project-task-check"
                  type="button"
                  :disabled="
                    busyTaskId !== null
                  "
                  :title="
                    task.isCompleted
                      ? 'Rouvrir'
                      : 'Terminer'
                  "
                  @click="
                    toggleTask(task)
                  "
                >
                  {{
                    task.isCompleted
                      ? '✓'
                      : '○'
                  }}
                </button>

                <p>
                  {{ task.content }}
                </p>
              </div>

              <div class="project-task-meta">
                <span>
                  {{
                    priorityLabel(
                      task.priority,
                    )
                  }}
                </span>

                <span>
                  {{
                    statusLabel(
                      task.status,
                    )
                  }}
                </span>

                <span
                  v-if="task.dueDate"
                >
                  Échéance
                  {{ task.dueDate }}
                </span>
              </div>

              <div class="project-task-actions">
                <button
                  type="button"
                  :disabled="
                    stageIndex === 0
                    || busyTaskId !== null
                  "
                  @click="
                    moveTask(task, -1)
                  "
                >
                  ←
                </button>

                <button
                  type="button"
                  :disabled="
                    stageIndex
                      === stages.length - 1
                    || busyTaskId !== null
                  "
                  @click="
                    moveTask(task, 1)
                  "
                >
                  →
                </button>

                <button
                  type="button"
                  :disabled="
                    busyTaskId !== null
                  "
                  @click="
                    deleteTask(task)
                  "
                >
                  Supprimer
                </button>
              </div>
            </article>
          </div>

          <form
            class="project-task-create"
            @submit.prevent="
              createTask(stage)
            "
          >
            <textarea
              v-model="
                taskContents[
                  stage.id
                ]
              "
              maxlength="4000"
              rows="2"
              placeholder="Nouvelle tâche…"
            />

            <button
              class="ui-button ui-button--secondary"
              :disabled="
                creatingTaskStageId
                  !== null
                || !(
                  taskContents[
                    stage.id
                  ] ?? ''
                ).trim()
              "
            >
              Ajouter
            </button>
          </form>
        </section>
      </div>
    </template>

    <p
      v-else-if="error"
      class="form-error"
    >
      {{ error }}
    </p>
  </section>
</template>

<style scoped>
.project-board-page {
  display: grid;
  gap: 1.25rem;
}

.project-board-header {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
}

.project-back-link {
  display: inline-block;
  margin-bottom: 0.5rem;
  color: inherit;
  text-decoration: none;
}

.project-title-row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.project-title-row h1 {
  margin: 0;
}

.project-board-dot {
  width: 13px;
  height: 13px;
  border-radius: 50%;
  flex: 0 0 auto;
}

.workflow-create-form {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.workflow-create-form input {
  min-width: 180px;
}

.project-board {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns:
    minmax(280px, 320px);
  gap: 1rem;
  overflow-x: auto;
  align-items: start;
  padding-bottom: 0.75rem;
}

.project-column {
  display: grid;
  gap: 0.75rem;
  padding: 0.85rem;
  border: 1px solid
    var(--border-color, #dadce0);
  border-radius: 14px;
  background:
    var(--surface, #fff);
}

.project-column-header {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: flex-start;
}

.project-column-header > div:first-child {
  display: grid;
  gap: 0.2rem;
}

.workflow-actions,
.project-task-actions {
  display: flex;
  gap: 0.3rem;
}

.workflow-actions button,
.project-task-actions button,
.project-task-check {
  border: 1px solid
    var(--border-color, #dadce0);
  border-radius: 7px;
  background: transparent;
  cursor: pointer;
}

.workflow-actions button:disabled,
.project-task-actions button:disabled,
.project-task-check:disabled {
  cursor: default;
  opacity: 0.45;
}

.project-task-list {
  display: grid;
  gap: 0.65rem;
}

.project-task-card {
  display: grid;
  gap: 0.65rem;
  padding: 0.75rem;
  border: 1px solid
    var(--border-color, #dadce0);
  border-radius: 10px;
}

.project-task-card--done {
  opacity: 0.65;
}

.project-task-heading {
  display: flex;
  gap: 0.5rem;
  align-items: flex-start;
}

.project-task-heading p {
  margin: 0;
  overflow-wrap: anywhere;
}

.project-task-card--done
.project-task-heading p {
  text-decoration: line-through;
}

.project-task-check {
  flex: 0 0 auto;
}

.project-task-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  font-size: 0.78rem;
}

.project-task-meta span {
  padding: 0.15rem 0.4rem;
  border-radius: 999px;
  background:
    var(--surface-muted, #f1f3f4);
}

.project-task-actions {
  justify-content: flex-end;
}

.project-task-create {
  display: grid;
  gap: 0.5rem;
}

.project-task-create textarea {
  width: 100%;
  resize: vertical;
  box-sizing: border-box;
}

.project-task-create button {
  justify-self: start;
}

@media (max-width: 800px) {
  .project-board-header {
    flex-direction: column;
  }

  .workflow-create-form {
    align-items: stretch;
    flex-direction: column;
  }

  .project-board {
    grid-auto-columns:
      minmax(260px, 85vw);
  }
}
</style>
