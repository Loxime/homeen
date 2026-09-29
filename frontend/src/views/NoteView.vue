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

import LabelSelector from '../components/LabelSelector.vue'
import NoteEditor from '../components/NoteEditor.vue'

import {
  getNote,
  updateNote,
} from '../services/noteApi'

import {
  getProjects,
} from '../services/projectApi'

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
const labelSaving = ref(false)

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

const currentProject =
  computed(
    () => {
      const id =
        note.value?.projectId

      if (!id) {
        return null
      }

      return (
        projects.value.find(
          project =>
            project.id === id,
        )
        ?? null
      )
    },
  )

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
      getNote(
        noteId.value,
      ),

      getProjects(),

      loadTags(),
    ])

    note.value =
      loadedNote

    projects.value =
      projectResponse
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
      await getNote(
        noteId.value,
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

async function updateLabels(
  tagIds: number[],
): Promise<void> {
  if (
    !note.value
    || labelSaving.value
  ) {
    return
  }

  labelSaving.value = true

  try {
    const current = note.value

    note.value =
      await updateNote(
        current.id,
        {
          title:
            current.title,

          content:
            current.noteType === 'list'
              ? ''
              : current.content,

          isPinned:
            current.isPinned,

          color:
            current.color,

          tagIds,

          projectId:
            current.projectId,
        },
      )
  } catch (exception) {
    showError(
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier les labels.',
    )
  } finally {
    labelSaving.value = false
  }
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
        <template v-if="currentProject">
          <RouterLink
            class="note-breadcrumb-link"
            to="/projects"
          >
            Projets
          </RouterLink>

          <span aria-hidden="true">
            ›
          </span>

          <RouterLink
            class="note-breadcrumb-link"
            :to="
              `/projects/${currentProject.id}`
            "
          >
            {{ currentProject.name }}
          </RouterLink>

          <span aria-hidden="true">
            ›
          </span>
        </template>

        <template v-else>
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
        </template>

        <strong>
          {{ pageTitle }}
        </strong>
      </nav>

      <div class="note-full-page-layout">
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

        <aside class="note-full-page-labels">
          <LabelSelector
            :tags="tags"
            :model-value="
              note.tags.map(
                tag => tag.id,
              )
            "
            :disabled="labelSaving"
            @update:model-value="
              updateLabels
            "
          />
        </aside>
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

.note-full-page-layout {
  min-width: 0;

  display: grid;
  grid-template-columns:
    minmax(0, 1fr)
    minmax(240px, 290px);
  align-items: start;
  gap: 1rem;
}

.note-full-page-labels {
  min-width: 0;
  padding: 1rem;

  border: 1px solid var(--g-border);
  border-radius: 18px;

  background: var(--g-surface);
}

@media (max-width: 860px) {
  .note-full-page-layout {
    grid-template-columns: 1fr;
  }

  .note-full-page-labels {
    order: 2;
  }
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
