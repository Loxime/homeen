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
  useRoute,
} from 'vue-router'

import Swal from 'sweetalert2'

import AppIcon from '../components/AppIcon.vue'

import {
  createProjectTask,
  createWorkflowStage,
  deleteProjectTask,
  deleteWorkflowStage,
  getProject,
  getProjectTasks,
  getProjectWorkflow,
  renameWorkflowStage,
  reorderProjectTasks,
  reorderProjectWorkflow,
  setProjectTaskCompleted,
  updateProject,
  updateProjectTask,
} from '../services/projectApi'

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

watchErrorToast(error)

const creatingTaskStageId =
  ref<number | null>(null)

const busyTaskId =
  ref<number | null>(null)

const draggedTaskId =
  ref<number | null>(null)

const dragOverStageId =
  ref<number | null>(null)

const dragOverTaskId =
  ref<number | null>(null)

const draggedStageId =
  ref<number | null>(null)

const dragOverStageOrderId =
  ref<number | null>(null)

const stageOrderBeforeDrag =
  ref<number[] | null>(null)

const stageDropInFlight =
  ref(false)

const stageName = ref('')
const creatingStage = ref(false)

const busyStageId =
  ref<number | null>(null)

const editingProject =
  ref(false)

const savingProject =
  ref(false)

const projectName =
  ref('')

const projectDescription =
  ref('')

const projectColor =
  ref('#1A73E8')

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

function syncProjectDraft(
  source: Project,
): void {
  projectName.value =
    source.name

  projectDescription.value =
    source.description

  projectColor.value =
    source.color
}

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
      getProject(
        projectId.value,
      ),
      getProjectWorkflow(
        projectId.value,
      ),
      getProjectTasks(
        projectId.value,
      ),
    ])

    project.value =
      projectResponse

    syncProjectDraft(
      projectResponse,
    )

    stages.value =
      workflowResponse

    tasks.value =
      taskResponse
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger le projet.'
  } finally {
    loading.value = false
  }
}

function startProjectEdit():
void {
  if (
    !project.value
    || !canManageWorkflow.value
  ) {
    return
  }

  syncProjectDraft(
    project.value,
  )

  editingProject.value = true
}

function cancelProjectEdit():
void {
  if (project.value) {
    syncProjectDraft(
      project.value,
    )
  }

  editingProject.value = false
}

async function saveProject():
Promise<void> {
  if (
    !project.value
    || !canManageWorkflow.value
    || savingProject.value
  ) {
    return
  }

  const name =
    projectName.value.trim()

  if (name === '') {
    error.value =
      'Le nom du projet est obligatoire.'

    return
  }

  savingProject.value = true
  error.value = ''

  try {
    const updated =
      await updateProject(
        projectId.value,
        {
          name,
          description:
            projectDescription.value,
          color:
            projectColor.value,
        },
      )

    project.value =
      updated

    syncProjectDraft(
      updated,
    )

    editingProject.value =
      false
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier le projet.'
  } finally {
    savingProject.value =
      false
  }
}

async function reloadWorkflow():
Promise<void> {
  stages.value =
    await getProjectWorkflow(
      projectId.value,
    )
}

function isCompletedStage(
  stage: ProjectWorkflowStage,
): boolean {
  const name =
    stage.name
      .trim()
      .toLocaleLowerCase()
      .normalize('NFD')
      .replace(
        /[\u0300-\u036f]/g,
        '',
      )

  return name === 'termine'
}

async function createTask(
  stage: ProjectWorkflowStage,
): Promise<void> {
  if (
    isCompletedStage(stage)
    || creatingTaskStageId.value
      !== null
  ) {
    return
  }

  const result =
    await Swal.fire({
      title: 'Créer une issue',
      input: 'text',
      inputLabel: 'Titre',
      inputPlaceholder:
        'Titre de l’issue',
      inputAttributes: {
        maxlength: '255',
      },
      showCancelButton: true,
      confirmButtonText: 'Créer',
      cancelButtonText: 'Annuler',
      focusCancel: false,
      preConfirm: value => {
        const title =
          typeof value === 'string'
            ? value.trim()
            : ''

        if (title === '') {
          Swal.showValidationMessage(
            'Le titre est obligatoire.',
          )

          return false
        }

        return title
      },
    })

  if (
    !result.isConfirmed
    || typeof result.value
      !== 'string'
  ) {
    return
  }

  const title =
    result.value.trim()

  if (title === '') {
    return
  }

  creatingTaskStageId.value =
    stage.id

  error.value = ''

  try {
    const created =
      await createProjectTask(
        projectId.value,
        {
          title,
          description: '',
          workflowStageId:
            stage.id,
        },
      )

    tasks.value = [
      ...tasks.value,
      created,
    ]
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de créer l’issue.'
  } finally {
    creatingTaskStageId.value =
      null
  }
}

async function changeTaskStage(
  task: ProjectTask,
  event: Event,
): Promise<void> {
  const select =
    event.target as HTMLSelectElement

  const workflowStageId =
    Number(select.value)

  if (
    !Number.isInteger(
      workflowStageId,
    )
    || workflowStageId <= 0
    || workflowStageId
      === task.workflowStageId
    || busyTaskId.value !== null
  ) {
    return
  }

  busyTaskId.value =
    task.id

  error.value = ''

  try {
    const updated =
      await updateProjectTask(
        projectId.value,
        task.id,
        {
          workflowStageId,
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
    select.value =
      String(
        task.workflowStageId,
      )

    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de déplacer la tâche.'
  } finally {
    busyTaskId.value = null
  }
}

function startTaskDrag(
  task: ProjectTask,
  event: DragEvent,
): void {
  if (busyTaskId.value !== null) {
    event.preventDefault()
    return
  }

  draggedStageId.value = null
  dragOverStageOrderId.value = null

  draggedTaskId.value =
    task.id

  event.dataTransfer?.setData(
    'text/plain',
    `task:${task.id}`,
  )

  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed =
      'move'
  }
}

function endTaskDrag(): void {
  draggedTaskId.value = null
  dragOverStageId.value = null
  dragOverTaskId.value = null
}

function markTaskDropTarget(
  stageId: number,
  taskId: number | null,
  event: DragEvent,
): void {
  if (
    draggedTaskId.value === null
    || busyTaskId.value !== null
  ) {
    return
  }

  event.preventDefault()
  event.stopPropagation()

  if (event.dataTransfer) {
    event.dataTransfer.dropEffect =
      'move'
  }

  dragOverStageId.value =
    stageId

  dragOverTaskId.value =
    taskId
}

async function dropTask(
  stage: ProjectWorkflowStage,
  beforeTask: ProjectTask | null,
  event: DragEvent,
): Promise<void> {
  const taskId =
    draggedTaskId.value

  if (
    taskId === null
    || busyTaskId.value !== null
  ) {
    return
  }

  event.preventDefault()
  event.stopPropagation()

  if (
    beforeTask !== null
    && beforeTask.id === taskId
  ) {
    endTaskDrag()
    return
  }

  const taskExists =
    tasks.value.some(
      candidate =>
        candidate.id === taskId,
    )

  if (!taskExists) {
    endTaskDrag()
    return
  }

  const previousTasks =
    [...tasks.value]

  const columns =
    stages.value.map(
      candidateStage => ({
        workflowStageId:
          candidateStage.id,

        taskIds:
          tasksForStage(
            candidateStage.id,
          )
            .filter(
              candidate =>
                candidate.id !== taskId,
            )
            .map(
              candidate =>
                candidate.id,
            ),
      }),
    )

  const target =
    columns.find(
      column =>
        column.workflowStageId
        === stage.id,
    )

  if (target === undefined) {
    endTaskDrag()
    return
  }

  if (beforeTask === null) {
    target.taskIds.push(taskId)
  } else {
    const index =
      target.taskIds.indexOf(
        beforeTask.id,
      )

    if (index < 0) {
      target.taskIds.push(taskId)
    } else {
      target.taskIds.splice(
        index,
        0,
        taskId,
      )
    }
  }

  const byId =
    new Map(
      tasks.value.map(
        task => [
          task.id,
          task,
        ] as const,
      ),
    )

  const optimistic:
    ProjectTask[] = []

  for (const column of columns) {
    column.taskIds.forEach(
      (id, index) => {
        const task =
          byId.get(id)

        if (!task) {
          return
        }

        optimistic.push({
          ...task,
          workflowStageId:
            column.workflowStageId,
          position:
            index + 1,
        })
      },
    )
  }

  if (
    optimistic.length
    === tasks.value.length
  ) {
    tasks.value = optimistic
  }

  busyTaskId.value = taskId
  error.value = ''

  endTaskDrag()

  try {
    tasks.value =
      await reorderProjectTasks(
        projectId.value,
        columns,
      )
  } catch (exception) {
    tasks.value =
      previousTasks

    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de déplacer l’issue.'
  } finally {
    busyTaskId.value = null
  }
}

function restoreStageOrder(
  ids: number[],
): void {
  const byId =
    new Map(
      stages.value.map(
        stage => [
          stage.id,
          stage,
        ] as const,
      ),
    )

  const ordered =
    ids
      .map(id => byId.get(id))
      .filter(
        (
          stage,
        ): stage is ProjectWorkflowStage =>
          stage !== undefined,
      )

  if (
    ordered.length
    === stages.value.length
  ) {
    stages.value = ordered
  }
}

function startStageDrag(
  stage: ProjectWorkflowStage,
  event: DragEvent,
): void {
  if (
    !canManageWorkflow.value
    || busyStageId.value
      !== null
  ) {
    event.preventDefault()
    return
  }

  draggedTaskId.value = null
  dragOverStageId.value = null
  dragOverTaskId.value = null

  stageOrderBeforeDrag.value =
    stages.value.map(
      candidate =>
        candidate.id,
    )

  stageDropInFlight.value = false

  draggedStageId.value =
    stage.id

  dragOverStageOrderId.value =
    null

  event.dataTransfer?.setData(
    'text/plain',
    `stage:${stage.id}`,
  )

  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed =
      'move'
  }
}

function markStageDropTarget(
  stageId: number,
  event: DragEvent,
): void {
  const sourceId =
    draggedStageId.value

  if (
    sourceId === null
    || sourceId === stageId
    || busyStageId.value !== null
  ) {
    return
  }

  event.preventDefault()

  if (event.dataTransfer) {
    event.dataTransfer.dropEffect =
      'move'
  }

  dragOverStageOrderId.value =
    stageId

  const source =
    stages.value.find(
      stage =>
        stage.id === sourceId,
    )

  if (!source) {
    return
  }

  const targetElement =
    event.currentTarget as HTMLElement

  const rectangle =
    targetElement
      .getBoundingClientRect()

  const placeAfter =
    event.clientX
    > rectangle.left
      + rectangle.width / 2

  const ordered =
    stages.value.filter(
      stage =>
        stage.id !== sourceId,
    )

  const targetIndex =
    ordered.findIndex(
      stage =>
        stage.id === stageId,
    )

  if (targetIndex < 0) {
    return
  }

  ordered.splice(
    targetIndex
      + (
        placeAfter
          ? 1
          : 0
      ),
    0,
    source,
  )

  const currentIds =
    stages.value.map(
      stage => stage.id,
    )

  const orderedIds =
    ordered.map(
      stage => stage.id,
    )

  if (
    !currentIds.every(
      (id, index) =>
        id === orderedIds[index],
    )
  ) {
    stages.value = ordered
  }
}

function endStageDrag(): void {
  if (
    !stageDropInFlight.value
    && stageOrderBeforeDrag.value
  ) {
    restoreStageOrder(
      stageOrderBeforeDrag.value,
    )

    stageOrderBeforeDrag.value =
      null
  }

  draggedStageId.value = null
  dragOverStageOrderId.value = null
}

async function dropStage(
  event: DragEvent,
): Promise<void> {
  const stageId =
    draggedStageId.value

  if (
    stageId === null
    || busyStageId.value
      !== null
  ) {
    return
  }

  event.preventDefault()
  event.stopPropagation()

  const originalIds =
    stageOrderBeforeDrag.value
      ? [...stageOrderBeforeDrag.value]
      : stages.value.map(
          stage => stage.id,
        )

  const orderedIds =
    stages.value.map(
      stage => stage.id,
    )

  if (
    originalIds.length
      === orderedIds.length
    && originalIds.every(
      (id, index) =>
        id === orderedIds[index],
    )
  ) {
    stageOrderBeforeDrag.value =
      null

    draggedStageId.value = null
    dragOverStageOrderId.value =
      null

    return
  }

  stageDropInFlight.value = true
  busyStageId.value = stageId

  draggedStageId.value = null
  dragOverStageOrderId.value = null

  error.value = ''

  try {
    stages.value =
      await reorderProjectWorkflow(
        projectId.value,
        orderedIds,
      )
  } catch (exception) {
    restoreStageOrder(originalIds)

    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de déplacer la colonne.'
  } finally {
    busyStageId.value = null
    stageOrderBeforeDrag.value = null
    stageDropInFlight.value = false
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
      await setProjectTaskCompleted(
        projectId.value,
        task.id,
        !task.isCompleted,
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
  if (busyTaskId.value !== null) {
    return
  }

  const result =
    await Swal.fire({
      icon: 'warning',
      title: 'Supprimer cette issue ?',
      text:
        `L’issue #${task.projectTaskNumber} sera supprimée définitivement.`,
      showCancelButton: true,
      confirmButtonText: 'Supprimer',
      cancelButtonText: 'Annuler',
      focusCancel: true,
    })

  if (!result.isConfirmed) {
    return
  }

  busyTaskId.value =
    task.id

  error.value = ''

  try {
    await deleteProjectTask(
      projectId.value,
      task.id,
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
    await createWorkflowStage(
      projectId.value,
      name,
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

  const result =
    await Swal.fire({
      title: 'Renommer la colonne',
      input: 'text',
      inputValue: stage.name,
      inputAttributes: {
        maxlength: '80',
      },
      showCancelButton: true,
      confirmButtonText: 'Renommer',
      cancelButtonText: 'Annuler',
    })

  if (
    !result.isConfirmed
    || typeof result.value !== 'string'
  ) {
    return
  }

  const name =
    result.value.trim()

  if (
    name === ''
    || name === stage.name
  ) {
    return
  }

  busyStageId.value =
    stage.id

  error.value = ''

  try {
    await renameWorkflowStage(
      projectId.value,
      stage.id,
      name,
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
  ) {
    return
  }

  const result =
    await Swal.fire({
      icon: 'warning',
      title: 'Supprimer cette colonne ?',
      text:
        `« ${stage.name} » doit être vide avant de pouvoir être supprimée.`,
      showCancelButton: true,
      confirmButtonText:
        'Supprimer la colonne',
      cancelButtonText: 'Annuler',
      focusCancel: true,
    })

  if (!result.isConfirmed) {
    return
  }

  busyStageId.value =
    stage.id

  error.value = ''

  try {
    await deleteWorkflowStage(
      projectId.value,
      stage.id,
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
    stages.value =
      await reorderProjectWorkflow(
        projectId.value,
        ordered.map(
          candidate =>
            candidate.id,
        ),
      )
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
      <nav
        class="project-breadcrumb"
        aria-label="Fil d’Ariane"
      >
        <RouterLink to="/projects">
          Projets
        </RouterLink>

        <span aria-hidden="true">
          ›
        </span>

        <strong>
          {{ project.name }}
        </strong>
      </nav>

      <header
        class="project-board-header"
        :style="{
          backgroundColor:
            `${project.color}14`,
        }"
      >
        <div class="project-board-main">
          <template v-if="!editingProject">
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
          </template>

          <form
            v-else
            class="project-edit-form"
            @submit.prevent="saveProject"
          >
            <label>
              Nom du projet

              <input
                v-model="projectName"
                maxlength="120"
                required
              />
            </label>

            <label>
              Description

              <textarea
                v-model="projectDescription"
                maxlength="4000"
                rows="2"
              />
            </label>

            <label class="project-edit-color">
              Couleur de fond

              <input
                v-model="projectColor"
                type="color"
              />
            </label>

            <div class="project-edit-actions">
              <button
                class="ui-button ui-button--primary"
                :disabled="
                  savingProject
                  || !projectName.trim()
                "
              >
                {{
                  savingProject
                    ? 'Enregistrement…'
                    : 'Enregistrer'
                }}
              </button>

              <button
                class="ui-button ui-button--secondary"
                type="button"
                :disabled="savingProject"
                @click="cancelProjectEdit"
              >
                Annuler
              </button>
            </div>
          </form>
        </div>

        <div class="project-board-header-actions">
          <button
            v-if="
              canManageWorkflow
              && !editingProject
            "
            class="ui-button ui-button--secondary"
            type="button"
            @click="startProjectEdit"
          >
            Modifier le projet
          </button>

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
        </div>
      </header>



      <form
        v-if="
          canManageWorkflow
          && stages.length < 20
        "
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

      <p
        v-if="
          canManageWorkflow
          && stages.length >= 20
        "
        class="
          muted
          workflow-limit
        "
      >
        Limite de 20 colonnes atteinte.
      </p>

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
          :class="{
            'project-column--dragging':
              draggedStageId
                === stage.id,

            'project-column--stage-over':
              dragOverStageOrderId
                === stage.id,
          }"
          @dragover="
            markStageDropTarget(
              stage.id,
              $event,
            )
          "
          @drop="
            dropStage(
              $event,
            )
          "
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
                issue{{
                  tasksForStage(
                    stage.id,
                  ).length > 1
                    ? 's'
                    : ''
                }}
              </small>
            </div>

            <div class="workflow-actions">
              <button
                v-if="
                  !isCompletedStage(stage)
                "
                type="button"
                class="project-column-add"
                title="Créer une issue"
                aria-label="Créer une issue"
                :disabled="
                  creatingTaskStageId
                    !== null
                "
                @click="
                  createTask(stage)
                "
              >
                <AppIcon
                  name="plus"
                  :size="14"
                />
              </button>

              <template
                v-if="canManageWorkflow"
              >
                <button
                  type="button"
                  class="project-column-drag-handle"
                  draggable="true"
                  title="Glisser pour déplacer la colonne"
                  aria-label="Déplacer la colonne par glisser-déposer"
                  :disabled="
                    busyStageId !== null
                  "
                  @dragstart="
                    startStageDrag(
                      stage,
                      $event,
                    )
                  "
                  @dragend="
                    endStageDrag
                  "
                >
                  <AppIcon
                    name="grip"
                    :size="14"
                  />
                </button>

                <button
                  type="button"
                  title="Déplacer à gauche"
                  aria-label="Déplacer la colonne à gauche"
                  :disabled="
                    stageIndex === 0
                    || busyStageId !== null
                  "
                  @click="
                    moveStage(stage, -1)
                  "
                >
                  <AppIcon
                    name="arrow-left"
                    :size="13"
                  />
                </button>

                <button
                  type="button"
                  title="Déplacer à droite"
                  aria-label="Déplacer la colonne à droite"
                  :disabled="
                    stageIndex
                      === stages.length - 1
                    || busyStageId !== null
                  "
                  @click="
                    moveStage(stage, 1)
                  "
                >
                  <AppIcon
                    name="arrow-right"
                    :size="13"
                  />
                </button>

                <button
                  type="button"
                  title="Renommer"
                  aria-label="Renommer la colonne"
                  :disabled="
                    busyStageId !== null
                  "
                  @click="
                    renameStage(stage)
                  "
                >
                  <AppIcon
                    name="edit"
                    :size="13"
                  />
                </button>

                <button
                  type="button"
                  title="Supprimer"
                  aria-label="Supprimer la colonne"
                  :disabled="
                    busyStageId !== null
                  "
                  @click="
                    deleteStage(stage)
                  "
                >
                  <AppIcon
                    name="trash"
                    :size="13"
                  />
                </button>
              </template>
            </div>
          </header>

          <div
            class="project-task-list"
            :class="{
              'project-task-list--over':
                dragOverStageId
                  === stage.id
                && dragOverTaskId
                  === null,
            }"
            @dragover="
              markTaskDropTarget(
                stage.id,
                null,
                $event,
              )
            "
            @drop="
              dropTask(
                stage,
                null,
                $event,
              )
            "
          >
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

                'project-task-card--dragging':
                  draggedTaskId
                    === task.id,

                'project-task-card--drop-before':
                  dragOverStageId
                    === stage.id
                  && dragOverTaskId
                    === task.id,
              }"
              draggable="true"
              @dragstart="
                startTaskDrag(
                  task,
                  $event,
                )
              "
              @dragend="
                endTaskDrag
              "
              @dragover="
                markTaskDropTarget(
                  stage.id,
                  task.id,
                  $event,
                )
              "
              @drop="
                dropTask(
                  stage,
                  task,
                  $event,
                )
              "
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
                  <AppIcon
                    :name="
                      task.isCompleted
                        ? 'check'
                        : 'circle'
                    "
                    :size="12"
                  />
                </button>

                <span class="project-task-number">
                  #{{ task.projectTaskNumber }}
                </span>

                <p>
                  <RouterLink
                    class="project-task-open"
                    :to="
                      `/projects/${projectId}/tasks/${task.id}`
                    "
                  >
                    {{ task.title }}
                  </RouterLink>
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

                <select
                  class="project-task-stage"
                  :value="
                    task.workflowStageId
                  "
                  :disabled="
                    busyTaskId !== null
                  "
                  aria-label="Colonne de la tâche"
                  @change="
                    changeTaskStage(
                      task,
                      $event,
                    )
                  "
                >
                  <option
                    v-for="
                      candidateStage
                      in stages
                    "
                    :key="
                      candidateStage.id
                    "
                    :value="
                      candidateStage.id
                    "
                  >
                    {{
                      candidateStage.name
                    }}
                  </option>
                </select>
              </div>

              <div class="project-task-actions">
                <small class="project-task-drag-hint">
                  Glisser-déposer pour réordonner
                </small>

                <button
                  type="button"
                  :disabled="
                    busyTaskId !== null
                  "
                  @click="
                    deleteTask(task)
                  "
                  title="Supprimer l’issue"
                  aria-label="Supprimer l’issue"
                >
                  <AppIcon
                    name="trash"
                    :size="13"
                  />
                </button>
              </div>
            </article>
          </div>


        </section>
      </div>
    </template>

    <div
      v-else
      class="empty-state"
    >
      <strong>
        Projet indisponible.
      </strong>

      <p class="muted">
        Revenez aux projets pour réessayer.
      </p>
    </div>
  </section>
</template>

<style scoped>
.project-board-page {
  display: grid;
  gap: 1.25rem;
}

.project-breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  color:
    var(--g-muted);
  font-size: 0.9rem;
}

.project-breadcrumb a {
  color:
    var(--g-blue-strong);
  text-decoration: none;
}

.project-breadcrumb a:hover {
  text-decoration: underline;
}

.project-breadcrumb strong {
  min-width: 0;
  overflow: hidden;
  color:
    var(--g-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.project-board-header {
  padding: 1rem;
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
  border: 1px solid
    var(--border-color, #dadce0);
  border-radius: 16px;
}

.project-board-main {
  min-width: 0;
  flex: 1;
}

.project-board-header-actions {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
  justify-content: flex-end;
}

.project-edit-form {
  display: grid;
  gap: .75rem;
  max-width: 640px;
}

.project-edit-form label {
  display: grid;
  gap: .35rem;
  font-size: .82rem;
  font-weight: 600;
}

.project-edit-form input,
.project-edit-form textarea {
  width: 100%;
  box-sizing: border-box;
}

.project-edit-color {
  max-width: 180px;
}

.project-edit-color input {
  min-height: 42px;
}

.project-edit-actions {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
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
    minmax(260px, 88vw);
  gap: .85rem;

  min-height: 64dvh;

  overflow-x: auto;
  overscroll-behavior-inline:
    contain;

  align-items: stretch;

  padding:
    .25rem
    .1rem
    .75rem;

  scroll-snap-type:
    x proximity;
}

.project-column--dragging {
  opacity: 0.55;
}

.project-column--stage-over {
  outline:
    2px dashed
    var(--g-blue);
  outline-offset: 3px;
}

.project-column-drag-handle {
  cursor: grab;
  user-select: none;
  touch-action: none;
}

.project-column-drag-handle:active {
  cursor: grabbing;
}

.workflow-limit {
  margin: 0;
  font-weight: 600;
}

.project-column {
  min-height: 64dvh;

  display: grid;
  grid-template-rows:
    auto
    minmax(0, 1fr);
  align-self: stretch;
  gap: 0.75rem;

  padding: 0.85rem;

  border: 1px solid
    var(--border-color, #dadce0);
  border-radius: 14px;

  background:
    var(--surface, #fff);

  scroll-snap-align: start;

  transition:
    border-color 140ms ease,
    opacity 140ms ease,
    transform 140ms ease;
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
  min-width: 30px;
  min-height: 30px;
  padding: 0 .4rem;

  display: inline-grid;
  place-items: center;

  border: 1px solid
    var(--border-color, #dadce0);
  border-radius: 7px;

  background: transparent;
  color: inherit;

  cursor: pointer;
}

.project-column-add {
  color: var(--g-blue-strong);
}

.workflow-actions button:disabled,
.project-task-actions button:disabled,
.project-task-check:disabled {
  cursor: default;
  opacity: 0.45;
}

.project-task-list {
  min-height: 7rem;
  min-width: 0;

  overflow-y: auto;

  display: grid;
  align-content: start;
  gap: 0.65rem;

  padding:
    2px
    3px
    1rem;
}

.project-task-card {
  display: grid;
  gap: 0.65rem;

  padding: 0.75rem;

  border: 1px solid
    var(--border-color, #dadce0);
  border-radius: 10px;

  background:
    var(--surface, #fff);

  transition:
    border-color 120ms ease,
    box-shadow 120ms ease,
    opacity 120ms ease,
    transform 120ms ease;
}

.project-task-card--done {
  opacity: 0.65;
}

.project-task-card--dragging {
  opacity: 0.35;
}

.project-task-card--drop-before {
  box-shadow:
    0 -3px 0
    var(--primary, #1a73e8);
}

.project-task-list--over {
  min-height: 3rem;
  border-radius: 10px;
  outline: 2px dashed
    var(--border-color, #dadce0);
  outline-offset: 3px;
}

.project-task-card {
  cursor: grab;
}

.project-task-card:active {
  cursor: grabbing;
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

.project-task-open {
  color: inherit;
  text-decoration: none;
}

.project-task-open:hover,
.project-task-open:focus-visible {
  text-decoration: underline;
}

.project-task-number {
  flex: 0 0 auto;
  padding-top: 2px;
  color:
    var(--text-muted, #6b7280);
  font-size: .76rem;
  font-weight: 700;
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

.project-task-stage {
  min-width: 0;
  max-width: 100%;
  padding: .18rem .45rem;
  border: 1px solid
    var(--border-color, #dadce0);
  border-radius: 999px;
  background:
    var(--surface, #fff);
  font: inherit;
}

.project-task-actions {
  justify-content: space-between;
  align-items: center;
}

.project-task-drag-hint {
  color:
    var(--text-muted, #6b7280);
  font-size: .7rem;
}

@media (max-width: 800px) {
  .project-board-header {
    flex-direction: column;
  }

  .project-board-header-actions {
    width: 100%;
    justify-content: stretch;
  }

  .project-board-header-actions
  .ui-button {
    flex: 1;
  }

  .project-edit-actions {
    flex-direction: column;
  }

  .project-edit-actions
  .ui-button {
    width: 100%;
  }

  .workflow-create-form {
    align-items: stretch;
    flex-direction: column;
  }

}

@media (min-width: 801px) {
  .project-board {
    grid-auto-columns:
      minmax(320px, 360px);

    gap: 1rem;
  }
}
</style>
