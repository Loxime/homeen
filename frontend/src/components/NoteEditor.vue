<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api } from '../services/api'
import type { Label, Note, Task } from '../types/domain'

const props = defineProps<{
  note: Note | null
  labels: Label[]
}>()
const emit = defineEmits<{ saved: [note: Note]; closed: []; changed: [] }>()

const title = ref('')
const content = ref('')
const labelId = ref<number | null>(null)
const taskText = ref('')
const saving = ref(false)
const error = ref('')
const localNote = ref<Note | null>(null)

const isNew = computed(() => localNote.value === null)
const isTrash = computed(() => localNote.value?.deletedAt !== null && localNote.value?.deletedAt !== undefined)
const isArchived = computed(() => localNote.value?.archivedAt !== null && localNote.value?.archivedAt !== undefined)

watch(
  () => props.note,
  (value) => {
    localNote.value = value ? structuredClone(value) : null
    title.value = value?.title ?? ''
    content.value = value?.content ?? ''
    labelId.value = value?.labelId ?? null
    taskText.value = ''
    error.value = ''
  },
  { immediate: true },
)

async function save(): Promise<void> {
  saving.value = true
  error.value = ''
  try {
    const payload = JSON.stringify({ title: title.value, content: content.value, labelId: labelId.value })
    const note = isNew.value
      ? await api<Note>('/api/notes', { method: 'POST', body: payload })
      : await api<Note>(`/api/notes/${localNote.value!.id}`, { method: 'PUT', body: payload })
    localNote.value = note
    emit('saved', note)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Unable to save note.'
  } finally {
    saving.value = false
  }
}

async function addTask(): Promise<void> {
  if (!localNote.value || !taskText.value.trim()) return
  error.value = ''
  try {
    const task = await api<Task>(`/api/notes/${localNote.value.id}/tasks`, {
      method: 'POST',
      body: JSON.stringify({ content: taskText.value.trim() }),
    })
    localNote.value.tasks.push(task)
    taskText.value = ''
    emit('changed')
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Unable to create task.'
  }
}

async function toggleTask(task: Task): Promise<void> {
  const updated = await api<Task>(`/api/tasks/${task.id}/completed`, {
    method: 'PUT',
    body: JSON.stringify({ completed: !task.isCompleted }),
  })
  Object.assign(task, updated)
  emit('changed')
}

async function deleteTask(task: Task): Promise<void> {
  if (!localNote.value) return
  await api(`/api/tasks/${task.id}`, { method: 'DELETE' })
  localNote.value.tasks = localNote.value.tasks.filter((item) => item.id !== task.id)
  emit('changed')
}

async function duplicate(): Promise<void> {
  if (!localNote.value) return
  const duplicated = await api<Note>(`/api/notes/${localNote.value.id}/duplicate`, { method: 'POST' })
  emit('saved', duplicated)
  emit('closed')
}

async function setArchive(archive: boolean): Promise<void> {
  if (!localNote.value) return
  localNote.value = await api<Note>(`/api/notes/${localNote.value.id}/${archive ? 'archive' : 'unarchive'}`, { method: 'POST' })
  emit('changed')
  emit('closed')
}

async function trash(): Promise<void> {
  if (!localNote.value) return
  await api(`/api/notes/${localNote.value.id}`, { method: 'DELETE' })
  emit('changed')
  emit('closed')
}

async function restore(): Promise<void> {
  if (!localNote.value) return
  localNote.value = await api<Note>(`/api/notes/${localNote.value.id}/restore`, { method: 'POST' })
  emit('changed')
  emit('closed')
}
</script>

<template>
  <div class="note-editor">
    <template v-if="isTrash">
      <div class="trash-message">
        <strong>This note is in trash.</strong>
        <p>It will be permanently deleted automatically 30 days after it was trashed.</p>
      </div>
      <button class="primary" @click="restore">Restore note</button>
    </template>

    <template v-else>
      <div class="field-row">
        <label>
          <span>Title</span>
          <input v-model="title" maxlength="255" placeholder="Untitled note" />
        </label>
        <label class="label-select">
          <span>Label</span>
          <select v-model="labelId">
            <option :value="null">No label</option>
            <option v-for="label in labels" :key="label.id" :value="label.id">{{ label.name }}</option>
          </select>
        </label>
      </div>

      <label>
        <span>Note</span>
        <textarea v-model="content" class="note-content" placeholder="Write here…" />
      </label>

      <p v-if="error" class="form-error">{{ error }}</p>
      <div class="editor-actions">
        <button class="primary" :disabled="saving" @click="save">{{ saving ? 'Saving…' : isNew ? 'Create note' : 'Save changes' }}</button>
        <template v-if="!isNew">
          <button class="secondary" @click="duplicate">Duplicate</button>
          <button class="secondary" @click="setArchive(!isArchived)">{{ isArchived ? 'Unarchive' : 'Archive' }}</button>
          <button class="danger-ghost" @click="trash">Move to trash</button>
        </template>
      </div>

      <section v-if="localNote" class="tasks-panel">
        <div class="section-heading">
          <div><p class="eyebrow">TASKS</p><h3>{{ localNote.tasks.filter((task) => task.isCompleted).length }} / {{ localNote.tasks.length }} completed</h3></div>
        </div>
        <form class="task-entry" @submit.prevent="addTask">
          <input v-model="taskText" maxlength="255" placeholder="Add a task…" />
          <span class="char-count">{{ taskText.length }}/255</span>
          <button class="secondary" :disabled="!taskText.trim()">Add</button>
        </form>
        <div class="task-list">
          <div v-for="task in localNote.tasks" :key="task.id" class="task-row" :class="{ complete: task.isCompleted }">
            <input type="checkbox" :checked="task.isCompleted" @change="toggleTask(task)" />
            <span>{{ task.content }}</span>
            <button class="icon-button small" aria-label="Delete task" @click="deleteTask(task)">×</button>
          </div>
          <p v-if="localNote.tasks.length === 0" class="empty-inline">No tasks yet.</p>
        </div>
      </section>
    </template>
  </div>
</template>
