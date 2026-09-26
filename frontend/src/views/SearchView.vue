<script setup lang="ts">
import {
  computed,
  ref,
  watch,
} from 'vue'

import {
  useRoute,
} from 'vue-router'

import BaseModal from '../components/BaseModal.vue'
import AppIcon from '../components/AppIcon.vue'
import NoteEditor from '../components/NoteEditor.vue'

import {
  api,
} from '../services/api'

import {
  useTags,
} from '../composables/useTags'

import type {
  Note,
  NoteCollection,
} from '../types/domain'

interface SearchNoteResult {
  id: number
  title: string
  content: string
  collectionName: string | null
  archivedAt: string | null
  updatedAt: string
}

interface SearchTaskResult {
  id: number
  noteId: number
  noteTitle: string
  content: string
  priority:
    | 'low'
    | 'normal'
    | 'high'
    | 'urgent'
  status:
    | 'todo'
    | 'in_progress'
    | 'done'
  startDate: string | null
  dueDate: string | null
  noteArchivedAt: string | null
  updatedAt: string
}

interface SearchResponse {
  notes: SearchNoteResult[]
  tasks: SearchTaskResult[]
}

const route = useRoute()

const {
  tags,
  load: loadTags,
} = useTags()

const notes =
  ref<SearchNoteResult[]>([])

const tasks =
  ref<SearchTaskResult[]>([])

const collections =
  ref<NoteCollection[]>([])

const selectedNote =
  ref<Note | null>(null)

const modalOpen = ref(false)
const loading = ref(false)
const error = ref('')

const query = computed(
  () =>
    typeof route.query.q === 'string'
      ? route.query.q.trim()
      : '',
)

const resultCount = computed(
  () =>
    notes.value.length
    + tasks.value.length,
)

function excerpt(
  value: string,
): string {
  const normalized =
    value.trim()

  if (!normalized) {
    return 'Aucun contenu'
  }

  return normalized.length > 220
    ? `${normalized.slice(0, 220)}…`
    : normalized
}

function statusLabel(
  status: SearchTaskResult['status'],
): string {
  return {
    todo: 'À faire',
    in_progress: 'En cours',
    done: 'Terminée',
  }[status]
}

function priorityLabel(
  priority: SearchTaskResult['priority'],
): string {
  return {
    low: 'Basse',
    normal: 'Normale',
    high: 'Haute',
    urgent: 'Urgente',
  }[priority]
}

async function loadSupportData():
Promise<void> {
  const [
    collectionResponse,
  ] = await Promise.all([
    api<{
      collections:
        NoteCollection[]
    }>(
      '/api/collections',
    ),
    loadTags(),
  ])

  collections.value =
    collectionResponse.collections
}

async function runSearch():
Promise<void> {
  notes.value = []
  tasks.value = []
  error.value = ''

  if (!query.value) {
    return
  }

  loading.value = true

  try {
    const params =
      new URLSearchParams({
        q: query.value,
      })

    const response =
      await api<SearchResponse>(
        `/api/search?${params}`,
      )

    notes.value =
      response.notes

    tasks.value =
      response.tasks
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’effectuer la recherche.'
  } finally {
    loading.value = false
  }
}

async function openNote(
  result: SearchNoteResult,
): Promise<void> {
  error.value = ''

  try {
    const [
      note,
    ] = await Promise.all([
      api<Note>(
        `/api/notes/${result.id}`,
      ),
      loadSupportData(),
    ])

    selectedNote.value = note
    modalOpen.value = true
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’ouvrir la note.'
  }
}

function closeNote(): void {
  modalOpen.value = false
}

watch(
  () => route.query.q,
  () => {
    void runSearch()
  },
  {
    immediate: true,
  },
)
</script>

<template>
  <section class="page search-page">
    <header class="page-header">
      <div>
        <h1>
          Recherche
        </h1>

        <p
          v-if="query"
          class="muted"
        >
          {{
            loading
              ? 'Recherche en cours…'
              : `${resultCount} résultat${resultCount > 1 ? 's' : ''} pour « ${query} »`
          }}
        </p>

        <p
          v-else
          class="muted"
        >
          Recherchez dans vos notes,
          tâches et tags.
        </p>
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
      Recherche en cours…
    </div>

    <div
      v-else-if="
        query
        && resultCount === 0
      "
      class="empty-state"
    >
      <strong>
        Aucun résultat.
      </strong>

      <p>
        Aucun contenu ne correspond à
        « {{ query }} ».
      </p>
    </div>

    <template v-else-if="query">
      <section class="search-section">
        <div class="search-section-heading">
          <div>
            <h2>
              Notes
            </h2>

            <span>
              {{ notes.length }}
            </span>
          </div>
        </div>

        <div
          v-if="notes.length === 0"
          class="search-empty-group"
        >
          Aucune note correspondante.
        </div>

        <div
          v-else
          class="search-result-list"
        >
          <button
            v-for="note in notes"
            :key="note.id"
            class="search-result-card"
            type="button"
            @click="openNote(note)"
          >
            <span class="search-result-icon">
              <AppIcon
                name="note"
                :size="20"
              />
            </span>

            <span class="search-result-body">
              <span class="search-result-title">
                {{
                  note.title
                  || 'Sans titre'
                }}
              </span>

              <span class="search-result-excerpt">
                {{ excerpt(note.content) }}
              </span>

              <span class="search-result-meta">
                <span
                  v-if="note.collectionName"
                >
                  {{
                    note.collectionName
                  }}
                </span>

                <span
                  v-if="note.archivedAt"
                  class="search-archived-badge"
                >
                  Archivée
                </span>
              </span>
            </span>
          </button>
        </div>
      </section>

      <section class="search-section">
        <div class="search-section-heading">
          <div>
            <h2>
              Tâches
            </h2>

            <span>
              {{ tasks.length }}
            </span>
          </div>
        </div>

        <div
          v-if="tasks.length === 0"
          class="search-empty-group"
        >
          Aucune tâche correspondante.
        </div>

        <div
          v-else
          class="search-result-list"
        >
          <RouterLink
            v-for="task in tasks"
            :key="task.id"
            class="search-result-card"
            :to="`/tasks/${task.id}`"
          >
            <span class="search-result-icon">
              <AppIcon
                name="check"
                :size="20"
              />
            </span>

            <span class="search-result-body">
              <span class="search-result-title">
                {{ task.content }}
              </span>

              <span class="search-result-excerpt">
                Note :
                {{
                  task.noteTitle
                  || 'Sans titre'
                }}
              </span>

              <span class="search-result-meta">
                <span>
                  {{
                    statusLabel(
                      task.status,
                    )
                  }}
                </span>

                <span>
                  Priorité
                  {{
                    priorityLabel(
                      task.priority,
                    )
                  }}
                </span>

                <span
                  v-if="task.dueDate"
                >
                  Échéance
                  {{ task.dueDate }}
                </span>

                <span
                  v-if="task.noteArchivedAt"
                  class="search-archived-badge"
                >
                  Note archivée
                </span>
              </span>
            </span>
          </RouterLink>
        </div>
      </section>
    </template>

    <BaseModal
      :open="modalOpen"
      :title="
        selectedNote
          ? (
              selectedNote.title
              || 'Note sans titre'
            )
          : 'Note'
      "
      @close="closeNote"
    >
      <NoteEditor
        v-if="selectedNote"
        :key="selectedNote.id"
        :note="selectedNote"
        :tags="tags"
        :collections="collections"
        @saved="
          (note) => {
            selectedNote = note
            void runSearch()
          }
        "
        @changed="runSearch"
        @closed="closeNote"
      />
    </BaseModal>
  </section>
</template>
