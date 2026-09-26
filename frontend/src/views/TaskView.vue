<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
} from 'vue'

import {
  useRoute,
  useRouter,
} from 'vue-router'

import AppIcon from '../components/AppIcon.vue'

import {
  api,
} from '../services/api'

import {
  useTags,
} from '../composables/useTags'

import type {
  Note,
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

const task = ref<Task | null>(null)
const note = ref<Note | null>(null)

const content = ref('')
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

const taskId = computed(
  () => Number(route.params.id),
)

const completed = computed(
  () => status.value === 'done',
)

function applyTask(
  value: Task,
): void {
  task.value = value
  content.value = value.content
  priority.value = value.priority
  status.value = value.status
  startDate.value = value.startDate
  dueDate.value = value.dueDate

  selectedTagIds.value =
    value.tags.map(
      tag => tag.id,
    )
}

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  if (
    !Number.isInteger(taskId.value)
    || taskId.value <= 0
  ) {
    error.value =
      'Identifiant de tâche invalide.'

    loading.value = false
    return
  }

  try {
    const [
      loadedTask,
    ] = await Promise.all([
      api<Task>(
        `/api/tasks/${taskId.value}`,
      ),
      loadTags(),
    ])

    applyTask(loadedTask)

    note.value =
      await api<Note>(
        `/api/notes/${loadedTask.noteId}`,
      )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger la tâche.'
  } finally {
    loading.value = false
  }
}

function toggleTagFromEvent(
  tagId: number,
  event: Event,
): void {
  const input =
    event.target as HTMLInputElement

  toggleTag(
    tagId,
    input.checked,
  )
}

function toggleTag(
  tagId: number,
  checked: boolean,
): void {
  if (checked) {
    selectedTagIds.value =
      Array.from(
        new Set([
          ...selectedTagIds.value,
          tagId,
        ]),
      )

    return
  }

  selectedTagIds.value =
    selectedTagIds.value.filter(
      id => id !== tagId,
    )
}

async function save(): Promise<void> {
  if (
    !task.value
    || saving.value
  ) {
    return
  }

  saving.value = true
  error.value = ''

  try {
    const updated =
      await api<Task>(
        `/api/tasks/${task.value.id}`,
        {
          method: 'PUT',

          body: JSON.stringify({
            content:
              content.value.trim(),

            priority:
              priority.value,

            status:
              status.value,

            startDate:
              startDate.value || null,

            dueDate:
              dueDate.value || null,

            tagIds:
              selectedTagIds.value,
          }),
        },
      )

    applyTask(updated)
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’enregistrer la tâche.'
  } finally {
    saving.value = false
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
      await api<Task>(
        `/api/tasks/${task.value.id}/completed`,
        {
          method: 'PUT',

          body: JSON.stringify({
            completed:
              !completed.value,
          }),
        },
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

  const confirmed =
    window.confirm(
      'Supprimer définitivement cette tâche ?',
    )

  if (!confirmed) {
    return
  }

  deleting.value = true
  error.value = ''

  try {
    await api(
      `/api/tasks/${task.value.id}`,
      {
        method: 'DELETE',
      },
    )

    await router.push('/notes')
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
  await router.push('/notes')
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
      <p class="form-error">
        {{
          error
          || 'Tâche introuvable.'
        }}
      </p>

      <button
        class="ui-button ui-button--secondary"
        type="button"
        @click="back"
      >
        Retour aux notes
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
            ← Retour aux notes
          </button>

          <p
            v-if="note"
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
              || !content.trim()
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

      <p
        v-if="error"
        class="form-error"
      >
        {{ error }}
      </p>

      <div class="task-detail-layout">
        <main class="task-detail-main">
          <label class="task-detail-content">
            <span>
              Tâche
            </span>

            <textarea
              v-model="content"
              maxlength="4000"
              rows="8"
              placeholder="Description de la tâche"
            />
          </label>

          <div class="task-content-footer">
            <span>
              {{ content.length }}/4000
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

          <section class="task-detail-card">
            <h2>
              Tags
            </h2>

            <p
              v-if="tags.length === 0"
              class="muted"
            >
              Aucun tag disponible.
            </p>

            <div
              v-else
              class="task-detail-tags"
            >
              <label
                v-for="tag in tags"
                :key="tag.id"
                class="task-detail-tag"
                :class="{
                  active:
                    selectedTagIds.includes(
                      tag.id,
                    ),
                }"
              >
                <input
                  type="checkbox"
                  :checked="
                    selectedTagIds.includes(
                      tag.id,
                    )
                  "
                  @change="
                    toggleTagFromEvent(
                      tag.id,
                      $event,
                    )
                  "
                />

                <span
                  class="task-tag-dot"
                  :style="{
                    background:
                      tag.color,
                  }"
                />

                <span>
                  {{ tag.name }}
                </span>
              </label>
            </div>
          </section>

          <section class="task-detail-card task-detail-meta">
            <h2>
              Informations
            </h2>

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
