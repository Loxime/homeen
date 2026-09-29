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
  useRouter,
} from 'vue-router'

import Swal from 'sweetalert2'

import AppIcon from '../components/AppIcon.vue'
import LabelSelector from '../components/LabelSelector.vue'
import MarkdownPreview from '../components/MarkdownPreview.vue'

import {
  deleteProjectTask,
  getProject,
  getProjectTask,
  getProjectWorkflow,
  setProjectTaskCompleted,
  updateProjectTask,
} from '../services/projectApi'

import {
  deleteTask,
  getTask,
  setTaskCompleted,
  updateTask,
} from '../services/taskApi'

import {
  getNote,
} from '../services/noteApi'

import {
  useTags,
} from '../composables/useTags'

import type {
  Note,
  Project,
  ProjectTask,
  ProjectWorkflowStage,
  Task,
  TaskPriority,
  TaskStatus,
} from '../types/domain'

const route = useRoute()
const router = useRouter()

const {
  tags,
  load: loadTags,
} = useTags()

const task =
  ref<Task | ProjectTask | null>(
    null,
  )

const note = ref<Note | null>(null)
const project =
  ref<Project | null>(null)

const stages =
  ref<ProjectWorkflowStage[]>([])

const selectedWorkflowStageId =
  ref<number | null>(null)

const title = ref('')
const description = ref('')

const priority =
  ref<TaskPriority>('normal')

const status =
  ref<TaskStatus>('todo')

const startDate =
  ref<string | null>(null)

const dueDate =
  ref<string | null>(null)

const selectedTagIds =
  ref<number[]>([])

const loading = ref(true)
const saving = ref(false)
const deleting = ref(false)
const error = ref('')

watchErrorToast(error)

const taskId = computed(
  () => Number(route.params.id),
)

const projectId = computed(
  () =>
    Number(
      route.params.projectId
      ?? 0,
    ),
)

const isProjectTask = computed(
  () =>
    Number.isInteger(
      projectId.value,
    )
    && projectId.value > 0,
)

const backPath =
  computed(
    () => {
      if (isProjectTask.value) {
        return `/projects/${projectId.value}`
      }

      if (
        task.value
        && 'noteId' in task.value
      ) {
        return `/notes/${task.value.noteId}`
      }

      return '/notes'
    },
  )

const projectTaskNumber =
  computed(
    () => {
      if (
        task.value
        && 'projectTaskNumber'
          in task.value
      ) {
        return task.value
          .projectTaskNumber
      }

      return null
    },
  )

const completed = computed(
  () => status.value === 'done',
)

function applyTask(
  value: Task | ProjectTask,
): void {
  task.value = value

  title.value =
    value.title
    || value.content

  description.value =
    value.description
    ?? ''

  priority.value = value.priority
  status.value = value.status
  startDate.value = value.startDate
  dueDate.value = value.dueDate

  selectedTagIds.value =
    value.tags.map(
      tag => tag.id,
    )

  selectedWorkflowStageId.value =
    'workflowStageId' in value
      ? value.workflowStageId
      : null
}

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  if (
    !Number.isInteger(taskId.value)
    || taskId.value <= 0
    || (
      route.params.projectId
      !== undefined
      && !isProjectTask.value
    )
  ) {
    error.value =
      'Identifiant de tâche invalide.'

    loading.value = false
    return
  }

  try {
    if (isProjectTask.value) {
      const [
        loadedTask,
        loadedProject,
        workflow,
      ] = await Promise.all([
        getProjectTask(
          projectId.value,
          taskId.value,
        ),

        getProject(
          projectId.value,
        ),

        getProjectWorkflow(
          projectId.value,
        ),

        loadTags(),
      ])

      applyTask(
        loadedTask,
      )

      project.value =
        loadedProject

      stages.value =
        workflow

      note.value = null
    } else {
      const loadedTask =
        await getTask(
          taskId.value,
        )

      applyTask(
        loadedTask,
      )

      note.value =
        await getNote(
          loadedTask.noteId,
        )

      project.value = null
      stages.value = []
    }
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger la tâche.'
  } finally {
    loading.value = false
  }
}

async function save(): Promise<boolean> {
  if (
    !task.value
    || saving.value
    || (
      isProjectTask.value
      && selectedWorkflowStageId.value
        === null
    )
  ) {
    return false
  }

  saving.value = true
  error.value = ''

  try {
    const input = {
      title:
        title.value.trim(),

      description:
        description.value,

      priority:
        priority.value,

      status:
        status.value,

      startDate:
        startDate.value || null,

      dueDate:
        dueDate.value || null,
    }

    const updated =
      isProjectTask.value
      && selectedWorkflowStageId.value
        !== null
        ? await updateProjectTask(
            projectId.value,
            taskId.value,
            {
              ...input,

              tagIds:
                selectedTagIds.value,

              workflowStageId:
                selectedWorkflowStageId.value,
            },
          )
        : await updateTask(
            taskId.value,
            input,
          )

    applyTask(updated)

    return true
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’enregistrer la tâche.'

    return false
  } finally {
    saving.value = false
  }
}

async function updateLabels(
  tagIds: number[],
): Promise<void> {
  if (
    !isProjectTask.value
    || saving.value
  ) {
    return
  }

  const previous =
    [...selectedTagIds.value]

  selectedTagIds.value =
    [...tagIds]

  const saved =
    await save()

  if (!saved) {
    selectedTagIds.value =
      previous
  }
}

async function toggleCompleted():
Promise<void> {
  if (!task.value) {
    return
  }

  error.value = ''

  try {
    const updated =
      isProjectTask.value
        ? await setProjectTaskCompleted(
            projectId.value,
            taskId.value,
            !completed.value,
          )
        : await setTaskCompleted(
            taskId.value,
            !completed.value,
          )

    applyTask(updated)
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier la tâche.'
  }
}

async function removeTask():
Promise<void> {
  if (
    !task.value
    || deleting.value
  ) {
    return
  }

  const result =
    await Swal.fire({
      icon: 'warning',
      title: 'Supprimer cette tâche ?',
      text:
        'Cette suppression est définitive.',
      showCancelButton: true,
      confirmButtonText: 'Supprimer',
      cancelButtonText: 'Annuler',
      focusCancel: true,
    })

  if (!result.isConfirmed) {
    return
  }

  deleting.value = true
  error.value = ''

  try {
    if (isProjectTask.value) {
      await deleteProjectTask(
        projectId.value,
        taskId.value,
      )
    } else {
      await deleteTask(
        taskId.value,
      )
    }

    await router.push(
      backPath.value,
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de supprimer la tâche.'
  } finally {
    deleting.value = false
  }
}

async function back(): Promise<void> {
  await router.push(
    backPath.value,
  )
}

onMounted(
  () => void load(),
)
</script>

<template>
  <section class="page task-page">
    <div
      v-if="loading"
      class="empty-state"
    >
      Chargement de la tâche…
    </div>

    <div
      v-else-if="!task"
      class="task-page-error"
    >
      <p class="muted">
        Tâche introuvable.
      </p>

      <button
        class="ui-button ui-button--secondary"
        type="button"
        @click="back"
      >
        {{
          isProjectTask
            ? 'Retour au projet'
            : 'Retour aux notes'
        }}
      </button>
    </div>

    <template v-else>
      <header class="task-page-header">
        <div>
          <button
            class="task-back-button"
            type="button"
            @click="back"
          >
            <AppIcon
              name="arrow-left"
              :size="13"
            />

            {{
              isProjectTask
                ? 'Retour au projet'
                : 'Retour aux notes'
            }}
          </button>

          <p
            v-if="
              isProjectTask
              && project
            "
            class="task-note-context"
          >
            Projet :
            <strong>
              {{ project.name }}
            </strong>

            <span
              v-if="
                projectTaskNumber
                !== null
              "
            >
              · #{{ projectTaskNumber }}
            </span>
          </p>

          <p
            v-else-if="note"
            class="task-note-context"
          >
            Note :
            <strong>
              {{
                note.title
                || 'Sans titre'
              }}
            </strong>
          </p>
        </div>

        <div class="task-page-actions">
          <button
            class="ui-button ui-button--secondary"
            type="button"
            :disabled="deleting"
            @click="removeTask"
          >
            <AppIcon
              name="trash"
              :size="17"
            />

            Supprimer
          </button>

          <button
            class="ui-button ui-button--primary"
            type="button"
            :disabled="
              saving
              || !title.trim()
            "
            @click="save"
          >
            {{
              saving
                ? 'Enregistrement…'
                : 'Enregistrer'
            }}
          </button>
        </div>
      </header>



      <div class="task-detail-layout">
        <main class="task-detail-main">
          <label class="task-detail-title">
            <span>
              Titre
            </span>

            <input
              v-model="title"
              maxlength="255"
              placeholder="Titre de l’issue"
            />
          </label>

          <section class="task-markdown-editor">
            <div class="task-markdown-heading">
              <div>
                <strong>
                  Description
                </strong>

                <small class="muted">
                  Markdown
                </small>
              </div>

              <span class="muted">
                {{ description.length }}/20000
              </span>
            </div>

            <textarea
              v-model="description"
              maxlength="20000"
              rows="12"
              placeholder="Décrivez l’issue en Markdown…"
            />

            <div class="task-markdown-preview">
              <strong class="task-markdown-preview-title">
                Aperçu
              </strong>

              <MarkdownPreview
                v-if="description.trim()"
                :source="description"
              />

              <p
                v-else
                class="muted"
              >
                Aucune description.
              </p>
            </div>
          </section>

          <div class="task-content-footer">
            <span>
              {{ title.length }}/255
            </span>

            <button
              class="task-completion-button"
              :class="{
                complete: completed,
              }"
              type="button"
              @click="toggleCompleted"
            >
              <AppIcon
                name="check"
                :size="17"
              />

              {{
                completed
                  ? 'Terminée'
                  : 'Marquer comme terminée'
              }}
            </button>
          </div>
        </main>

        <aside class="task-detail-sidebar">
          <section class="task-detail-card">
            <h2>
              Organisation
            </h2>

            <label class="task-detail-field">
              <span>
                Priorité
              </span>

              <select v-model="priority">
                <option value="low">
                  Basse
                </option>

                <option value="normal">
                  Normale
                </option>

                <option value="high">
                  Haute
                </option>

                <option value="urgent">
                  Urgente
                </option>
              </select>
            </label>

            <label
              v-if="isProjectTask"
              class="task-detail-field"
            >
              <span>
                Colonne
              </span>

              <select
                v-model.number="
                  selectedWorkflowStageId
                "
              >
                <option
                  v-for="
                    stage in stages
                  "
                  :key="stage.id"
                  :value="stage.id"
                >
                  {{ stage.name }}
                </option>
              </select>
            </label>

            <label class="task-detail-field">
              <span>
                État
              </span>

              <select v-model="status">
                <option value="todo">
                  À faire
                </option>

                <option value="in_progress">
                  En cours
                </option>

                <option value="done">
                  Terminée
                </option>
              </select>
            </label>
          </section>

          <section class="task-detail-card">
            <h2>
              Dates
            </h2>

            <label class="task-detail-field">
              <span>
                Date de début
              </span>

              <input
                v-model="startDate"
                type="date"
              />
            </label>

            <label class="task-detail-field">
              <span>
                Échéance
              </span>

              <input
                v-model="dueDate"
                type="date"
              />
            </label>
          </section>

          <section
            v-if="isProjectTask"
            class="task-detail-card"
          >
            <LabelSelector
              :tags="tags"
              :model-value="
                selectedTagIds
              "
              :disabled="saving"
              @update:model-value="
                updateLabels
              "
            />
          </section>

          <section class="task-detail-card task-detail-meta">
            <h2>
              Informations
            </h2>

            <span
              v-if="
                projectTaskNumber
                !== null
              "
            >
              Numéro projet :
              #{{ projectTaskNumber }}
            </span>

            <span>
              Position :
              {{ task.position }}
            </span>

            <span>
              Créée :
              {{ task.createdAt }}
            </span>

            <span>
              Modifiée :
              {{ task.updatedAt }}
            </span>
          </section>
        </aside>
      </div>
    </template>
  </section>
</template>


<style scoped>
.task-detail-title {
  display: grid;
  gap: 0.5rem;
}

.task-detail-title > span {
  font-size: 0.82rem;
  font-weight: 700;
}

.task-detail-title input {
  width: 100%;
  box-sizing: border-box;
  font-size: 1.15rem;
  font-weight: 700;
}

.task-markdown-editor {
  display: grid;
  gap: 0.75rem;
}

.task-markdown-heading {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-end;
}

.task-markdown-heading > div {
  display: grid;
  gap: 0.15rem;
}

.task-markdown-editor textarea {
  width: 100%;
  min-height: 260px;
  box-sizing: border-box;
  resize: vertical;
  font-family:
    ui-monospace,
    SFMono-Regular,
    Menlo,
    Monaco,
    Consolas,
    monospace;
  line-height: 1.55;
}

.task-markdown-preview {
  min-height: 100px;
  padding: 1rem;
  border: 1px solid
    var(--g-border);
  border-radius: 12px;
  background:
    var(--g-surface-alt);
}

.task-markdown-preview-title {
  display: block;
  margin-bottom: 0.75rem;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
</style>
