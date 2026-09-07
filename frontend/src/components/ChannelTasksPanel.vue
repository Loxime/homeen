<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
  watch,
} from 'vue'

import AppIcon from './AppIcon.vue'

import {
  api,
} from '../services/api'

import {
  formatDate,
} from '../services/format'

interface ChannelTask {
  id: number
  noteId: number
  noteTitle: string
  content: string
  isCompleted: boolean
  completedAt: string | null
  createdAt: string
  updatedAt: string
}

const props = defineProps<{
  channelCode: string
}>()

const tasks =
  ref<ChannelTask[]>([])

const loading = ref(true)
const error = ref('')

const busyTaskId =
  ref<number | null>(null)

const pendingTasks = computed(
  () =>
    tasks.value.filter(
      task =>
        !task.isCompleted,
    ),
)

const completedTasks = computed(
  () =>
    tasks.value.filter(
      task =>
        task.isCompleted,
    ),
)

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const response =
      await api<{
        tasks: ChannelTask[]
      }>(
        `/api/channels/${props.channelCode}/tasks`,
      )

    tasks.value =
      response.tasks
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les tâches du canal.'
  } finally {
    loading.value = false
  }
}

async function toggleTask(
  task: ChannelTask,
): Promise<void> {
  busyTaskId.value = task.id
  error.value = ''

  try {
    await api(
      `/api/channels/${props.channelCode}/notes/${task.noteId}/tasks/${task.id}/completed`,
      {
        method: 'PUT',

        body: JSON.stringify({
          completed:
            !task.isCompleted,
        }),
      },
    )

    await load()
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
  task: ChannelTask,
): Promise<void> {
  busyTaskId.value = task.id
  error.value = ''

  try {
    await api(
      `/api/channels/${props.channelCode}/notes/${task.noteId}/tasks/${task.id}`,
      {
        method: 'DELETE',
      },
    )

    await load()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de supprimer la tâche.'
  } finally {
    busyTaskId.value = null
  }
}

watch(
  () => props.channelCode,
  () => {
    void load()
  },
)

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="channel-tasks-panel">
    <header class="channel-tasks-header">
      <div>
        <h2>
          Tâches
        </h2>

        <p class="muted">
          Toutes les tâches des notes
          partagées de ce canal.
        </p>
      </div>

      <div class="channel-task-summary">
        <span>
          {{ pendingTasks.length }}
          à faire
        </span>

        <span>
          {{ completedTasks.length }}
          terminées
        </span>
      </div>
    </header>

    <p
      v-if="error"
      class="form-error"
    >
      {{ error }}
    </p>

    <div
      v-if="loading"
      class="empty-state"
    >
      Chargement des tâches…
    </div>

    <div
      v-else-if="tasks.length === 0"
      class="empty-state"
    >
      <strong>
        Aucune tâche dans ce canal.
      </strong>

      <p>
        Ajoutez des tâches depuis
        les notes collaboratives.
      </p>
    </div>

    <div
      v-else
      class="channel-task-sections"
    >
      <section
        v-if="pendingTasks.length"
        class="channel-task-group"
      >
        <h3>
          À faire
        </h3>

        <div class="channel-task-list">
          <article
            v-for="task in pendingTasks"
            :key="task.id"
            class="channel-task-row"
          >
            <input
              type="checkbox"
              :checked="task.isCompleted"
              :disabled="
                busyTaskId === task.id
              "
              @change="
                toggleTask(task)
              "
            />

            <div class="channel-task-content">
              <strong>
                {{ task.content }}
              </strong>

              <small>
                {{
                  task.noteTitle
                  || 'Note sans titre'
                }}
                ·
                {{
                  formatDate(
                    task.updatedAt,
                  )
                }}
              </small>
            </div>

            <button
              class="icon-button small"
              type="button"
              title="Supprimer la tâche"
              aria-label="Supprimer la tâche"
              :disabled="
                busyTaskId === task.id
              "
              @click="
                deleteTask(task)
              "
            >
              <AppIcon
                name="close"
                :size="16"
              />
            </button>
          </article>
        </div>
      </section>

      <section
        v-if="completedTasks.length"
        class="channel-task-group"
      >
        <h3>
          Terminées
        </h3>

        <div class="channel-task-list">
          <article
            v-for="task in completedTasks"
            :key="task.id"
            class="
              channel-task-row
              complete
            "
          >
            <input
              type="checkbox"
              :checked="task.isCompleted"
              :disabled="
                busyTaskId === task.id
              "
              @change="
                toggleTask(task)
              "
            />

            <div class="channel-task-content">
              <strong>
                {{ task.content }}
              </strong>

              <small>
                {{
                  task.noteTitle
                  || 'Note sans titre'
                }}
                ·
                {{
                  formatDate(
                    task.completedAt
                    || task.updatedAt,
                  )
                }}
              </small>
            </div>

            <button
              class="icon-button small"
              type="button"
              title="Supprimer la tâche"
              aria-label="Supprimer la tâche"
              :disabled="
                busyTaskId === task.id
              "
              @click="
                deleteTask(task)
              "
            >
              <AppIcon
                name="close"
                :size="16"
              />
            </button>
          </article>
        </div>
      </section>
    </div>
  </section>
</template>
