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

import NoteEditor from '../components/NoteEditor.vue'

import {
  api,
} from '../services/api'

import {
  useTags,
} from '../composables/useTags'

import {
  useToast,
} from '../composables/useToast'

import type {
  Note,
  Project,
} from '../types/domain'

const route = useRoute()
const router = useRouter()

const {
  tags,
  load: loadTags,
} = useTags()

const {
  error:
    showError,
} = useToast()

const note =
  ref<Note | null>(null)

const projects =
  ref<Project[]>([])

const loading = ref(true)
const unavailable = ref(false)

const noteId =
  computed(
    () =>
      Number(
        route.params.id,
      ),
  )

const backPath =
  computed(() => {
    if (
      note.value?.deletedAt
    ) {
      return '/trash'
    }

    if (
      note.value?.archivedAt
    ) {
      return '/archived'
    }

    if (
      note.value?.projectId
    ) {
      return (
        `/notes?projectId=${note.value.projectId}`
      )
    }

    return '/notes'
  })

const pageTitle =
  computed(
    () =>
      note.value?.title.trim()
      || (
        note.value?.noteType
        === 'list'
          ? 'Liste sans titre'
          : 'Note sans titre'
      ),
  )

async function load():
Promise<void> {
  loading.value = true
  unavailable.value = false

  if (
    !Number.isInteger(
      noteId.value,
    )
    || noteId.value <= 0
  ) {
    unavailable.value = true

    showError(
      'Identifiant de note invalide.',
    )

    loading.value = false
    return
  }

  try {
    const [
      loadedNote,
      projectResponse,
    ] = await Promise.all([
      api<Note>(
        `/api/notes/${noteId.value}`,
      ),

      api<{
        projects: Project[]
      }>(
        '/api/projects',
      ),

      loadTags(),
    ])

    note.value =
      loadedNote

    projects.value =
      projectResponse.projects
  } catch (exception) {
    unavailable.value = true

    showError(
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger cette note.',
    )
  } finally {
    loading.value = false
  }
}

async function refreshNote():
Promise<void> {
  if (
    !Number.isInteger(
      noteId.value,
    )
    || noteId.value <= 0
  ) {
    return
  }

  try {
    note.value =
      await api<Note>(
        `/api/notes/${noteId.value}`,
      )
  } catch (exception) {
    showError(
      exception instanceof Error
        ? exception.message
        : 'Impossible d’actualiser cette note.',
    )
  }
}

function handleSaved(
  updated: Note,
): void {
  note.value = updated
}

async function close():
Promise<void> {
  await router.push(
    backPath.value,
  )
}

onMounted(
  () => void load(),
)
</script>

<template>
  <section
    class="
      page
      note-full-page
    "
  >
    <div
      v-if="loading"
      class="empty-state"
    >
      Chargement de la note…
    </div>

    <div
      v-else-if="
        unavailable
        || !note
      "
      class="note-full-page-unavailable"
    >
      <p class="muted">
        Cette note n’est pas disponible.
      </p>

      <button
        type="button"
        class="
          ui-button
          ui-button--secondary
        "
        @click="close"
      >
        Retour aux notes
      </button>
    </div>

    <template v-else>
      <nav
        class="note-breadcrumb"
        aria-label="Fil d’Ariane"
      >
        <button
          type="button"
          class="note-breadcrumb-link"
          @click="close"
        >
          Notes
        </button>

        <span aria-hidden="true">
          ›
        </span>

        <strong>
          {{ pageTitle }}
        </strong>
      </nav>

      <div class="note-full-page-shell">
        <NoteEditor
          :key="note.id"
          :note="note"
          :tags="tags"
          :projects="projects"
          @saved="handleSaved"
          @changed="refreshNote"
          @closed="close"
        />
      </div>
    </template>
  </section>
</template>

<style scoped>
.note-full-page {
  width: min(
    1180px,
    100%
  );
  margin: 0 auto;
}

.note-breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  margin-bottom: 1rem;
  color: var(--g-muted);
  font-size: 0.9rem;
}

.note-breadcrumb strong {
  min-width: 0;
  overflow: hidden;
  color: var(--g-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.note-breadcrumb-link {
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--g-blue-strong);
  font: inherit;
  cursor: pointer;
}

.note-breadcrumb-link:hover {
  text-decoration: underline;
}

.note-full-page-shell {
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--g-border);
  border-radius: 22px;
  background: var(--g-surface);
}

.note-full-page-unavailable {
  min-height: 260px;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 1rem;
}
</style>
