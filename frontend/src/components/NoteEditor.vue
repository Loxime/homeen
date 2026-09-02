<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AppIcon from './AppIcon.vue'
import { api } from '../services/api'
import type { Label, Note, Task } from '../types/domain'

const props = defineProps<{
  note: Note | null
  labels: Label[]
}>()

const emit = defineEmits<{
  saved: [note: Note]
  closed: []
  changed: []
}>()

const title = ref('')
const content = ref('')
const labelId = ref<number | null>(null)
const taskText = ref('')
const saving = ref(false)
const error = ref('')
const localNote = ref<Note | null>(null)

const isNew = computed(() => localNote.value === null)

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

function cloneNote(note: Note): Note {
  return {
    ...note,
    tasks: note.tasks.map((task) => ({
      ...task,
    })),
  }
}

watch(
  () => props.note,
  (value) => {
    localNote.value = value ? cloneNote(value) : null

    title.value = value?.title ?? ''
    content.value = value?.content ?? ''
    labelId.value = value?.labelId ?? null
    taskText.value = ''
    error.value = ''
  },
  { immediate: true },
)

async function save(): Promise<Note | null> {
  saving.value = true
  error.value = ''

  try {
    const payload = JSON.stringify({
      title: title.value,
      content: content.value,
      labelId: labelId.value,
    })

    const note = isNew.value
      ? await api<Note>('/api/notes', {
          method: 'POST',
          body: payload,
        })
      : await api<Note>(
          `/api/notes/${localNote.value!.id}`,
          {
            method: 'PUT',
            body: payload,
          },
        )

    localNote.value = note
    emit('saved', note)

    return note
  } catch (e) {
    error.value =
      e instanceof Error
        ? e.message
        : 'Unable to save note.'

    return null
  } finally {
    saving.value = false
  }
}

async function done(): Promise<void> {
  const note = await save()

  if (note) {
    emit('closed')
  }
}

async function addTask(): Promise<void> {
  if (!localNote.value || !taskText.value.trim()) {
    return
  }

  const task = await api<Task>(
    `/api/notes/${localNote.value.id}/tasks`,
    {
      method: 'POST',
      body: JSON.stringify({
        content: taskText.value.trim(),
      }),
    },
  )

  localNote.value.tasks.push(task)
  taskText.value = ''

  emit('changed')
}

async function toggleTask(task: Task): Promise<void> {
  const updated = await api<Task>(
    `/api/tasks/${task.id}/completed`,
    {
      method: 'PUT',
      body: JSON.stringify({
        completed: !task.isCompleted,
      }),
    },
  )

  Object.assign(task, updated)

  emit('changed')
}

async function deleteTask(task: Task): Promise<void> {
  if (!localNote.value) {
    return
  }

  await api(`/api/tasks/${task.id}`, {
    method: 'DELETE',
  })

  localNote.value.tasks =
    localNote.value.tasks.filter(
      (item) => item.id !== task.id,
    )

  emit('changed')
}

async function duplicate(): Promise<void> {
  if (!localNote.value) return

  const duplicated = await api<Note>(
    `/api/notes/${localNote.value.id}/duplicate`,
    { method: 'POST' },
  )

  emit('saved', duplicated)
  emit('closed')
}

async function setArchive(archive: boolean): Promise<void> {
  if (!localNote.value) return

  await api<Note>(
    `/api/notes/${localNote.value.id}/${
      archive ? 'archive' : 'unarchive'
    }`,
    { method: 'POST' },
  )

  emit('changed')
  emit('closed')
}

async function trash(): Promise<void> {
  if (!localNote.value) return

  await api(
    `/api/notes/${localNote.value.id}`,
    { method: 'DELETE' },
  )

  emit('changed')
  emit('closed')
}

async function restore(): Promise<void> {
  if (!localNote.value) return

  await api<Note>(
    `/api/notes/${localNote.value.id}/restore`,
    { method: 'POST' },
  )

  emit('changed')
  emit('closed')
}
</script>

<template>
  <div class="note-editor keep-note-editor">
    <template v-if="isTrash">
      <div class="trash-message">
        <strong>This note is in trash.</strong>

        <p>
          It will be permanently deleted automatically
          30 days after it was trashed.
        </p>
      </div>

      <div class="keep-editor-footer">
        <button class="secondary" @click="restore">
          Restore note
        </button>

        <button
          class="keep-done"
          @click="emit('closed')"
        >
          Close
        </button>
      </div>
    </template>

    <template v-else>
      <div class="keep-note-main">
        <input
          v-model="title"
          class="keep-title-input"
          maxlength="255"
          placeholder="Title"
        />

        <textarea
          v-model="content"
          class="keep-content-input"
          placeholder="Take a note…"
        />

        <div class="keep-label-row">
          <span class="keep-label-caption">
            Label
          </span>

          <select
            v-model="labelId"
            class="keep-label-select"
          >
            <option :value="null">
              No label
            </option>

            <option
              v-for="label in labels"
              :key="label.id"
              :value="label.id"
            >
              {{ label.name }}
            </option>
          </select>
        </div>

        <p v-if="error" class="form-error">
          {{ error }}
        </p>

        <section
          v-if="localNote"
          class="tasks-panel keep-tasks-panel"
        >
          <div class="section-heading keep-task-heading">
            <div>
              <span class="keep-section-label">
                Tasks
              </span>

              <small>
                {{
                  localNote.tasks.filter(
                    (task) => task.isCompleted,
                  ).length
                }}
                /
                {{ localNote.tasks.length }}
                completed
              </small>
            </div>
          </div>

          <form
            class="task-entry keep-task-entry"
            @submit.prevent="addTask"
          >
            <AppIcon name="plus" :size="19" />

            <input
              v-model="taskText"
              maxlength="255"
              placeholder="Add a task"
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
              :class="{ complete: task.isCompleted }"
            >
              <input
                type="checkbox"
                :checked="task.isCompleted"
                @change="toggleTask(task)"
              />

              <span>{{ task.content }}</span>

              <button
                class="icon-button small"
                title="Delete task"
                aria-label="Delete task"
                @click="deleteTask(task)"
              >
                <AppIcon name="close" :size="16" />
              </button>
            </div>
          </div>
        </section>

        <p v-else class="keep-task-hint">
          Save the note once to start adding tasks.
        </p>
      </div>

      <footer class="keep-editor-footer">
        <div class="keep-editor-tools">
          <button
            v-if="!isNew"
            class="keep-tool-button"
            title="Duplicate"
            @click="duplicate"
          >
            <AppIcon name="copy" :size="19" />
          </button>

          <button
            v-if="!isNew"
            class="keep-tool-button"
            :title="isArchived ? 'Unarchive' : 'Archive'"
            @click="setArchive(!isArchived)"
          >
            <AppIcon name="archive" :size="19" />
          </button>

          <button
            v-if="!isNew"
            class="keep-tool-button danger"
            title="Move to trash"
            @click="trash"
          >
            <AppIcon name="trash" :size="19" />
          </button>
        </div>

        <button
          class="keep-done"
          :disabled="saving"
          @click="done"
        >
          {{ saving ? 'Saving…' : 'Done' }}
        </button>
      </footer>
    </template>
  </div>
</template>
