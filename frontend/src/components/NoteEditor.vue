<script setup lang="ts">
import {
  computed,
  ref,
  watch,
} from 'vue'

import AppIcon from './AppIcon.vue'
import { api } from '../services/api'

import type {
  Label,
  Note,
  NoteCollection,
  Task,
} from '../types/domain'

const props = withDefaults(
  defineProps<{
    note: Note | null
    labels: Label[]
    collections: NoteCollection[]
    defaultCollectionId?: number | null
  }>(),
  {
    defaultCollectionId: null,
  },
)

const emit = defineEmits<{
  saved: [note: Note]
  closed: []
  changed: []
}>()

const title = ref('')
const content = ref('')
const labelId = ref<number | null>(null)
const collectionId = ref<number | null>(null)
const taskText = ref('')
const saving = ref(false)
const error = ref('')
const localNote = ref<Note | null>(null)

const isNew = computed(
  () => localNote.value === null,
)

const isTrash = computed(
  () =>
    localNote.value?.deletedAt !== null
    && localNote.value?.deletedAt !== undefined,
)

const isArchived = computed(
  () =>
    localNote.value?.archivedAt !== null
    && localNote.value?.archivedAt !== undefined,
)

function cloneNote(
  note: Note,
): Note {
  return {
    ...note,
    tasks: note.tasks.map(
      task => ({
        ...task,
      }),
    ),
  }
}

watch(
  () => [
    props.note,
    props.defaultCollectionId,
  ] as const,
  ([value]) => {
    localNote.value =
      value
        ? cloneNote(value)
        : null

    title.value =
      value?.title ?? ''

    content.value =
      value?.content ?? ''

    labelId.value =
      value?.labelId ?? null

    collectionId.value =
      value?.collectionId
      ?? props.defaultCollectionId
      ?? null

    taskText.value = ''
    error.value = ''
  },
  {
    immediate: true,
  },
)

async function save():
Promise<Note | null> {
  saving.value = true
  error.value = ''

  try {
    const payload =
      JSON.stringify({
        title: title.value,
        content: content.value,
        labelId: labelId.value,
        collectionId:
          collectionId.value,
      })

    const note =
      isNew.value
        ? await api<Note>(
            '/api/notes',
            {
              method: 'POST',
              body: payload,
            },
          )
        : await api<Note>(
            `/api/notes/${localNote.value!.id}`,
            {
              method: 'PUT',
              body: payload,
            },
          )

    localNote.value = note

    emit(
      'saved',
      note,
    )

    return note
  } catch (exception) {
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

async function addTask(): Promise<void> {
  if (
    !localNote.value
    || !taskText.value.trim()
  ) {
    return
  }

  const task =
    await api<Task>(
      `/api/notes/${localNote.value.id}/tasks`,
      {
        method: 'POST',
        body: JSON.stringify({
          content:
            taskText.value.trim(),
        }),
      },
    )

  localNote.value.tasks.push(task)
  taskText.value = ''

  emit('changed')
}

async function toggleTask(
  task: Task,
): Promise<void> {
  const updated =
    await api<Task>(
      `/api/tasks/${task.id}/completed`,
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
}

async function deleteTask(
  task: Task,
): Promise<void> {
  if (!localNote.value) {
    return
  }

  await api(
    `/api/tasks/${task.id}`,
    {
      method: 'DELETE',
    },
  )

  localNote.value.tasks =
    localNote.value.tasks.filter(
      item =>
        item.id !== task.id,
    )

  emit('changed')
}

async function duplicate(): Promise<void> {
  if (!localNote.value) {
    return
  }

  const duplicated =
    await api<Note>(
      `/api/notes/${localNote.value.id}/duplicate`,
      {
        method: 'POST',
      },
    )

  emit(
    'saved',
    duplicated,
  )

  emit('closed')
}

async function setArchive(
  archive: boolean,
): Promise<void> {
  if (!localNote.value) {
    return
  }

  await api<Note>(
    `/api/notes/${localNote.value.id}/${
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
}

async function trash(): Promise<void> {
  if (!localNote.value) {
    return
  }

  await api(
    `/api/notes/${localNote.value.id}`,
    {
      method: 'DELETE',
    },
  )

  emit('changed')
  emit('closed')
}

async function restore(): Promise<void> {
  if (!localNote.value) {
    return
  }

  await api<Note>(
    `/api/notes/${localNote.value.id}/restore`,
    {
      method: 'POST',
    },
  )

  emit('changed')
  emit('closed')
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
          Elle sera supprimée définitivement
          30 jours après sa mise à la corbeille.
        </p>
      </div>

      <div class="keep-editor-footer">
        <button
          class="secondary"
          @click="restore"
        >
          Restaurer la note
        </button>

        <button
          class="keep-done"
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
          placeholder="Écrivez votre note…"
        />

        <div class="note-organization-grid">
          <label>
            <span class="keep-label-caption">
              Collection / projet
            </span>

            <select
              v-model="collectionId"
              class="keep-label-select"
            >
              <option :value="null">
                Aucune collection
              </option>

              <option
                v-for="collection in collections"
                :key="collection.id"
                :value="collection.id"
              >
                {{ collection.name }}
              </option>
            </select>
          </label>

          <label>
            <span class="keep-label-caption">
              Libellé
            </span>

            <select
              v-model="labelId"
              class="keep-label-select"
            >
              <option :value="null">
                Aucun libellé
              </option>

              <option
                v-for="label in labels"
                :key="label.id"
                :value="label.id"
              >
                {{ label.name }}
              </option>
            </select>
          </label>
        </div>

        <p
          v-if="error"
          class="form-error"
        >
          {{ error }}
        </p>

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
                @change="toggleTask(task)"
              />

              <span>
                {{ task.content }}
              </span>

              <button
                class="icon-button small"
                title="Supprimer la tâche"
                aria-label="Supprimer la tâche"
                @click="deleteTask(task)"
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
          pour pouvoir ajouter des tâches.
        </p>
      </div>

      <footer class="keep-editor-footer">
        <div class="keep-editor-tools">
          <button
            v-if="!isNew"
            class="keep-tool-button"
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
            :title="
              isArchived
                ? 'Désarchiver'
                : 'Archiver'
            "
            @click="
              setArchive(
                !isArchived
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
            title="Mettre à la corbeille"
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
          :disabled="saving"
          @click="done"
        >
          {{
            saving
              ? 'Enregistrement…'
              : 'Terminer'
          }}
        </button>
      </footer>
    </template>
  </div>
</template>
