<script setup lang="ts">
import {
  computed,
  ref,
  watch,
} from 'vue'

import AppIcon from './AppIcon.vue'

import {
  ApiError,
  api,
} from '../services/api'

import type {
  ChannelNote,
} from '../types/channel'

import type {
  Task,
} from '../types/domain'

const props = defineProps<{
  channelCode: string
  note: ChannelNote | null
}>()

const emit = defineEmits<{
  saved: [note: ChannelNote]
  closed: []
  changed: []
}>()

const localNote =
  ref<ChannelNote | null>(null)

const title = ref('')
const content = ref('')

const saving = ref(false)
const error = ref('')
const conflict = ref(false)
const reloading = ref(false)

const taskText = ref('')
const taskCreating = ref(false)
const taskBusyId = ref<number | null>(null)

const isNew = computed(
  () => localNote.value === null,
)

const isTrash = computed(
  () =>
    localNote.value?.deletedAt
      !== null
    && localNote.value?.deletedAt
      !== undefined,
)

const isArchived = computed(
  () =>
    localNote.value?.archivedAt
      !== null
    && localNote.value?.archivedAt
      !== undefined,
)

function cloneNote(
  note: ChannelNote,
): ChannelNote {
  return {
    ...note,

    tasks: note.tasks.map(
      task => ({
        ...task,
      }),
    ),
  }
}

function applyNote(
  note: ChannelNote | null,
): void {
  localNote.value =
    note
      ? cloneNote(note)
      : null

  title.value =
    note?.title ?? ''

  content.value =
    note?.content ?? ''

  error.value = ''
  conflict.value = false
  taskText.value = ''
}

watch(
  () => props.note,
  value => {
    applyNote(value)
  },
  {
    immediate: true,
  },
)

async function save(): Promise<ChannelNote | null> {
  saving.value = true
  error.value = ''
  conflict.value = false

  try {
    const note =
      isNew.value
        ? await api<ChannelNote>(
            `/api/channels/${props.channelCode}/notes`,
            {
              method: 'POST',

              body: JSON.stringify({
                title: title.value,
                content: content.value,
              }),
            },
          )
        : await api<ChannelNote>(
            `/api/channels/${props.channelCode}/notes/${localNote.value!.id}`,
            {
              method: 'PUT',

              body: JSON.stringify({
                title: title.value,
                content: content.value,
                version:
                  localNote.value!.version,
              }),
            },
          )

    applyNote(note)

    emit(
      'saved',
      note,
    )

    return note
  } catch (exception) {
    if (
      exception instanceof ApiError
      && exception.code
        === 'NOTE_VERSION_CONFLICT'
    ) {
      conflict.value = true

      error.value =
        'Cette note a été modifiée par un autre membre depuis son ouverture.'

      return null
    }

    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’enregistrer la note.'

    return null
  } finally {
    saving.value = false
  }
}

async function done(): Promise<void> {
  const note =
    await save()

  if (note) {
    emit('closed')
  }
}

async function reloadLatest(): Promise<void> {
  if (!localNote.value) {
    return
  }

  reloading.value = true
  error.value = ''

  try {
    const note =
      await api<ChannelNote>(
        `/api/channels/${props.channelCode}/notes/${localNote.value.id}`,
      )

    applyNote(note)
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de recharger la note.'
  } finally {
    reloading.value = false
  }
}

async function addTask(): Promise<void> {
  if (
    !localNote.value
    || !taskText.value.trim()
  ) {
    return
  }

  taskCreating.value = true
  error.value = ''

  try {
    const task =
      await api<Task>(
        `/api/channels/${props.channelCode}/notes/${localNote.value.id}/tasks`,
        {
          method: 'POST',

          body: JSON.stringify({
            content:
              taskText.value.trim(),
          }),
        },
      )

    localNote.value.tasks.push(
      task,
    )

    taskText.value = ''

    emit('changed')
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de créer la tâche.'
  } finally {
    taskCreating.value = false
  }
}

async function toggleTask(
  task: Task,
): Promise<void> {
  if (!localNote.value) {
    return
  }

  taskBusyId.value = task.id
  error.value = ''

  try {
    const updated =
      await api<Task>(
        `/api/channels/${props.channelCode}/notes/${localNote.value.id}/tasks/${task.id}/completed`,
        {
          method: 'PUT',

          body: JSON.stringify({
            completed:
              !task.isCompleted,
          }),
        },
      )

    Object.assign(
      task,
      updated,
    )

    emit('changed')
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier la tâche.'
  } finally {
    taskBusyId.value = null
  }
}

async function deleteTask(
  task: Task,
): Promise<void> {
  if (!localNote.value) {
    return
  }

  taskBusyId.value = task.id
  error.value = ''

  try {
    await api(
      `/api/channels/${props.channelCode}/notes/${localNote.value.id}/tasks/${task.id}`,
      {
        method: 'DELETE',
      },
    )

    localNote.value.tasks =
      localNote.value.tasks.filter(
        candidate =>
          candidate.id !== task.id,
      )

    emit('changed')
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de supprimer la tâche.'
  } finally {
    taskBusyId.value = null
  }
}

async function duplicate(): Promise<void> {
  if (!localNote.value) {
    return
  }

  try {
    const duplicated =
      await api<ChannelNote>(
        `/api/channels/${props.channelCode}/notes/${localNote.value.id}/duplicate`,
        {
          method: 'POST',
        },
      )

    emit(
      'saved',
      duplicated,
    )

    emit('closed')
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de dupliquer la note.'
  }
}

async function setArchive(
  archive: boolean,
): Promise<void> {
  if (!localNote.value) {
    return
  }

  try {
    await api<ChannelNote>(
      `/api/channels/${props.channelCode}/notes/${localNote.value.id}/${
        archive
          ? 'archive'
          : 'unarchive'
      }`,
      {
        method: 'POST',
      },
    )

    emit('changed')
    emit('closed')
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier l’archivage.'
  }
}

async function trash(): Promise<void> {
  if (!localNote.value) {
    return
  }

  try {
    await api(
      `/api/channels/${props.channelCode}/notes/${localNote.value.id}`,
      {
        method: 'DELETE',
      },
    )

    emit('changed')
    emit('closed')
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de placer la note dans la corbeille.'
  }
}

async function restore(): Promise<void> {
  if (!localNote.value) {
    return
  }

  try {
    const note =
      await api<ChannelNote>(
        `/api/channels/${props.channelCode}/notes/${localNote.value.id}/restore`,
        {
          method: 'POST',
        },
      )

    applyNote(note)

    emit('changed')
    emit('closed')
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de restaurer la note.'
  }
}
</script>

<template>
  <div class="note-editor keep-note-editor">
    <template v-if="isTrash">
      <div class="trash-message">
        <strong>
          Cette note est dans la corbeille.
        </strong>

        <p>
          Elle appartient au canal
          {{ channelCode }}.
        </p>
      </div>

      <p
        v-if="error"
        class="form-error"
      >
        {{ error }}
      </p>

      <div class="keep-editor-footer">
        <button
          class="secondary"
          type="button"
          @click="restore"
        >
          Restaurer
        </button>

        <button
          class="keep-done"
          type="button"
          @click="emit('closed')"
        >
          Fermer
        </button>
      </div>
    </template>

    <template v-else>
      <div class="keep-note-main">
        <input
          v-model="title"
          class="keep-title-input"
          maxlength="255"
          placeholder="Titre"
        />

        <textarea
          v-model="content"
          class="keep-content-input"
          placeholder="Écrire une note partagée…"
        />

        <div
          v-if="localNote"
          class="channel-note-editor-meta"
        >
          <span>
            Version
            {{ localNote.version }}
          </span>

          <span
            v-if="localNote.createdByEmail"
          >
            Créée par
            {{ localNote.createdByEmail }}
          </span>
        </div>

        <p
          v-if="error"
          class="form-error"
        >
          {{ error }}
        </p>

        <div
          v-if="conflict"
          class="channel-note-conflict"
        >
          <p>
            Rechargez la dernière version
            avant de continuer.
          </p>

          <button
            class="secondary"
            type="button"
            :disabled="reloading"
            @click="reloadLatest"
          >
            {{
              reloading
                ? 'Rechargement…'
                : 'Charger la dernière version'
            }}
          </button>
        </div>

        <section
          v-if="localNote"
          class="tasks-panel keep-tasks-panel"
        >
          <div class="section-heading keep-task-heading">
            <div>
              <span class="keep-section-label">
                Tâches
              </span>

              <small>
                {{
                  localNote.tasks.filter(
                    task =>
                      task.isCompleted,
                  ).length
                }}
                /
                {{ localNote.tasks.length }}
                terminées
              </small>
            </div>
          </div>

          <form
            class="task-entry keep-task-entry"
            @submit.prevent="addTask"
          >
            <AppIcon
              name="plus"
              :size="19"
            />

            <input
              v-model="taskText"
              maxlength="255"
              placeholder="Ajouter une tâche"
              :disabled="taskCreating"
            />

            <span class="char-count">
              {{ taskText.length }}/255
            </span>
          </form>

          <div class="task-list">
            <div
              v-for="task in localNote.tasks"
              :key="task.id"
              class="task-row keep-task-row"
              :class="{
                complete:
                  task.isCompleted,
              }"
            >
              <input
                type="checkbox"
                :checked="task.isCompleted"
                :disabled="
                  taskBusyId === task.id
                "
                @change="
                  toggleTask(task)
                "
              />

              <span>
                {{ task.content }}
              </span>

              <button
                class="icon-button small"
                type="button"
                title="Supprimer la tâche"
                aria-label="Supprimer la tâche"
                :disabled="
                  taskBusyId === task.id
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
            </div>
          </div>
        </section>

        <p
          v-else
          class="keep-task-hint"
        >
          Enregistrez d’abord la note
          pour ajouter des tâches.
        </p>
      </div>

      <footer class="keep-editor-footer">
        <div class="keep-editor-tools">
          <button
            v-if="!isNew"
            class="keep-tool-button"
            type="button"
            title="Dupliquer"
            @click="duplicate"
          >
            <AppIcon
              name="copy"
              :size="19"
            />
          </button>

          <button
            v-if="!isNew"
            class="keep-tool-button"
            type="button"
            :title="
              isArchived
                ? 'Désarchiver'
                : 'Archiver'
            "
            @click="
              setArchive(
                !isArchived,
              )
            "
          >
            <AppIcon
              name="archive"
              :size="19"
            />
          </button>

          <button
            v-if="!isNew"
            class="keep-tool-button danger"
            type="button"
            title="Placer dans la corbeille"
            @click="trash"
          >
            <AppIcon
              name="trash"
              :size="19"
            />
          </button>
        </div>

        <button
          class="keep-done"
          type="button"
          :disabled="
            saving
            || conflict
          "
          @click="done"
        >
          {{
            saving
              ? 'Enregistrement…'
              : 'Terminé'
          }}
        </button>
      </footer>
    </template>
  </div>
</template>
