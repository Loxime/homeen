<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import BaseModal from '../components/BaseModal.vue'
import AppIcon from '../components/AppIcon.vue'
import NoteEditor from '../components/NoteEditor.vue'
import { api } from '../services/api'
import { formatDate } from '../services/format'
import type { Label, Note, NoteSummary } from '../types/domain'

const props = defineProps<{ scope: 'active' | 'archived' | 'trash' }>()
const route = useRoute()
const notes = ref<NoteSummary[]>([])
const labels = ref<Label[]>([])
const loading = ref(true)
const savedDisplay = localStorage.getItem('homeen-note-display')
const display = ref<'grid' | 'list' | 'whiteboard'>(savedDisplay === 'list' || savedDisplay === 'whiteboard' ? savedDisplay : 'grid')
const modalOpen = ref(false)
const selected = ref<Note | null>(null)
const boardSeed = ref(Math.floor(Math.random() * 1_000_000))
const error = ref('')

const title = computed(() => props.scope === 'active' ? 'Notes' : props.scope === 'archived' ? 'Archived notes' : 'Trash')
const query = computed(() => typeof route.query.q === 'string' ? route.query.q : '')

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams({ scope: props.scope })
    if (query.value.trim()) params.set('q', query.value.trim())
    const [noteResponse, labelResponse] = await Promise.all([
      api<{ notes: NoteSummary[] }>(`/api/notes?${params}`),
      api<{ labels: Label[] }>('/api/labels'),
    ])
    notes.value = noteResponse.notes
    labels.value = labelResponse.labels
    boardSeed.value = Math.floor(Math.random() * 1_000_000)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Unable to load notes.'
  } finally {
    loading.value = false
  }
}

async function openNote(summary: NoteSummary): Promise<void> {
  selected.value = await api<Note>(`/api/notes/${summary.id}`)
  modalOpen.value = true
}

function newNote(): void {
  selected.value = null
  modalOpen.value = true
}

function boardPosition(note: NoteSummary, index: number): Record<string, string> {
  const hash = Math.abs((note.id * 2654435761 + boardSeed.value) | 0)
  const columns = 4
  const col = index % columns
  const row = Math.floor(index / columns)
  const jitterX = (hash % 41) - 20
  const jitterY = (Math.floor(hash / 41) % 41) - 20
  const rotation = ((Math.floor(hash / 1681) % 7) - 3) * 0.5
  return {
    left: `calc(${col * 25}% + ${jitterX + 12}px)`,
    top: `${row * 190 + jitterY + 28}px`,
    transform: `rotate(${rotation}deg)`,
  }
}

function noteExcerpt(note: NoteSummary): string {
  return note.content.trim() || 'Empty note'
}

function noteCardDate(note: NoteSummary): string {
  return props.scope === 'trash' && note.deletedAt ? note.deletedAt : note.updatedAt
}

watch(display, (value) => localStorage.setItem('homeen-note-display', value))
watch(() => [props.scope, route.query.q], () => void load())
onMounted(() => void load())
</script>

<template>
  <section class="page notes-page">
    <header class="page-header">
      <div>
        <p class="eyebrow">WORKSPACE</p>
        <h1>{{ title }}</h1>
        <p v-if="query" class="muted">Search results for “{{ query }}”</p>
      </div>
      <div class="page-actions">
        <div
          v-if="scope !== 'trash'"
          class="segmented keep-view-switcher"
          aria-label="Note display"
        >
          <button
            :class="{ active: display === 'grid' }"
            title="Grid"
            @click="display = 'grid'"
          >
            <AppIcon name="grid" :size="19" />
          </button>

          <button
            :class="{ active: display === 'list' }"
            title="List"
            @click="display = 'list'"
          >
            <AppIcon name="list" :size="19" />
          </button>

          <button
            :class="{ active: display === 'whiteboard' }"
            title="Whiteboard"
            @click="display = 'whiteboard'"
          >
            <AppIcon name="whiteboard" :size="19" />
          </button>
        </div>
      </div>
    </header>
    <button
      v-if="scope === 'active'"
      class="keep-note-composer"
      @click="newNote"
    >
      <AppIcon name="note" :size="21" />

      <span>Take a note…</span>

      <span class="keep-composer-actions">
        <AppIcon name="check" :size="19" />
        <AppIcon name="plus" :size="19" />
      </span>
    </button>
    <p v-if="error" class="form-error">{{ error }}</p>
    <div v-if="loading" class="empty-state">Loading notes…</div>
    <div v-else-if="notes.length === 0" class="empty-state">
      <strong>{{ query ? 'No result found.' : scope === 'trash' ? 'Trash is empty.' : 'No notes here yet.' }}</strong>
      <p v-if="scope === 'active' && !query">Create your first note to start building your workspace.</p>
    </div>

    <div v-else-if="display === 'grid' || scope === 'trash'" class="notes-grid">
      <article v-for="note in notes" :key="note.id" class="note-card" @click="openNote(note)">
        <div class="note-card-top">
          <span v-if="note.labelName" class="label-chip" :style="{ '--label': note.labelColor ?? '#64748B' }">{{ note.labelName }}</span>
          <span class="task-ratio">{{ note.completedTaskCount }}/{{ note.taskCount }}</span>
        </div>
        <h2>{{ note.title || 'Untitled' }}</h2>
        <p class="note-excerpt">{{ noteExcerpt(note) }}</p>
        <footer><span>{{ scope === 'trash' ? 'Deleted' : 'Updated' }}</span>{{ formatDate(noteCardDate(note)) }}</footer>
      </article>
    </div>

    <div v-else-if="display === 'list'" class="notes-list">
      <button v-for="note in notes" :key="note.id" class="note-list-row" @click="openNote(note)">
        <div class="list-title"><strong>{{ note.title || 'Untitled' }}</strong><span>{{ noteExcerpt(note) }}</span></div>
        <span v-if="note.labelName" class="label-chip" :style="{ '--label': note.labelColor ?? '#64748B' }">{{ note.labelName }}</span>
        <span>{{ note.completedTaskCount }}/{{ note.taskCount }} tasks</span>
        <span>{{ formatDate(note.updatedAt) }}</span>
      </button>
    </div>

    <div v-else class="whiteboard-wrap">
      <div class="whiteboard-toolbar"><span>Random board layout</span><button class="ghost" @click="boardSeed = Math.floor(Math.random() * 1_000_000)">Shuffle</button></div>
      <div class="whiteboard" :style="{ minHeight: `${Math.ceil(notes.length / 4) * 190 + 80}px` }">
        <button v-for="(note, index) in notes" :key="note.id" class="board-note" :style="boardPosition(note, index)" @click="openNote(note)">
          <span v-if="note.labelName" class="board-label" :style="{ background: note.labelColor ?? '#64748B' }" />
          <strong>{{ note.title || 'Untitled' }}</strong>
          <span>{{ noteExcerpt(note).slice(0, 150) }}</span>
          <small>{{ note.completedTaskCount }}/{{ note.taskCount }} tasks</small>
        </button>
      </div>
    </div>

    <BaseModal :open="modalOpen" :title="selected ? (selected.title || 'Untitled note') : 'New note'" @close="modalOpen = false">
      <NoteEditor
        :key="selected?.id ?? 'new-note'"
        :note="selected"
        :labels="labels"
        @saved="(note) => { selected = note; void load() }"
        @changed="load"
        @closed="modalOpen = false"
      />
    </BaseModal>
  </section>
</template>
