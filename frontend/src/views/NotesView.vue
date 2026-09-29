<script setup lang="ts">
import {
  watchErrorToast,
} from '../composables/useErrorToast'

import {
  computed,
  onMounted,
  ref,
  watch,
} from 'vue'

import {
  useRoute,
  useRouter,
} from 'vue-router'

import Swal from 'sweetalert2'

import {
  isPinnedNoteLimitError,
  showPinnedNoteLimitAlert,
} from '../services/noteAlerts'

import AppIcon from '../components/AppIcon.vue'

import {
  archiveNote,
  createNote,
  getNotes,
  updateNote,
} from '../services/noteApi'

import {
  createNoteTask,
} from '../services/taskApi'

import {
  getProjects,
} from '../services/projectApi'
import { formatDate } from '../services/format'

import {
  useToast,
} from '../composables/useToast'

import type {
  NoteSummary,
  Project,
} from '../types/domain'

const props =
  defineProps<{
    scope:
      | 'active'
      | 'archived'
      | 'trash'
  }>()

const route = useRoute()
const router = useRouter()

const {
  success:
    showSuccess,

  error:
    showError,
} = useToast()

const notes =
  ref<NoteSummary[]>([])

const projects =
  ref<Project[]>([])

const loading = ref(true)
const error = ref('')

watchErrorToast(error)

const quickActionBusyId =
  ref<number | null>(null)

const savedDisplay =
  localStorage.getItem(
    'homeen-note-display',
  )

const display =
  ref<
    | 'grid'
    | 'list'
    | 'whiteboard'
  >(
    savedDisplay === 'list'
    || savedDisplay === 'whiteboard'
      ? savedDisplay
      : 'grid',
  )

const boardSeed =
  ref(
    Math.floor(
      Math.random()
      * 1_000_000,
    ),
  )

const title =
  computed(
    () =>
      props.scope === 'active'
        ? 'Notes'
        : props.scope === 'archived'
          ? 'Notes archivées'
          : 'Corbeille',
  )

const query =
  computed(
    () =>
      typeof route.query.q === 'string'
        ? route.query.q
        : '',
  )

const projectId =
  computed<number | null>(
    () => {
      const raw =
        route.query.projectId

      if (
        typeof raw !== 'string'
        || !/^\d+$/.test(raw)
      ) {
        return null
      }

      const id =
        Number(raw)

      return id > 0
        ? id
        : null
    },
  )

const activeProject =
  computed(
    () =>
      projects.value.find(
        project =>
          project.id
          === projectId.value,
      ) ?? null,
  )

const gridSections =
  computed(
    () => {
      if (
        props.scope
        !== 'active'
      ) {
        return [
          {
            key: 'all',
            title: '',
            notes:
              notes.value,
          },
        ]
      }

      const pinned =
        notes.value.filter(
          note =>
            note.isPinned,
        )

      const other =
        notes.value.filter(
          note =>
            !note.isPinned,
        )

      const sections: Array<{
        key: string
        title: string
        notes: NoteSummary[]
      }> = []

      if (pinned.length > 0) {
        sections.push({
          key: 'pinned',
          title: 'Épinglées',
          notes: pinned,
        })
      }

      if (other.length > 0) {
        sections.push({
          key: 'other',
          title:
            pinned.length > 0
              ? 'Autres'
              : '',
          notes: other,
        })
      }

      return sections
    },
  )

async function load():
Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const [
      noteResponse,
      projectResponse,
    ] = await Promise.all([
      getNotes({
        scope: props.scope,
        q: query.value,
        projectId:
          projectId.value,
      }),

      getProjects(),
    ])

    notes.value =
      noteResponse

    projects.value =
      projectResponse

    boardSeed.value =
      Math.floor(
        Math.random()
        * 1_000_000,
      )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les notes.'
  } finally {
    loading.value = false
  }
}

function openNote(
  summary: NoteSummary,
): void {
  void router.push(
    `/notes/${summary.id}`,
  )
}

interface QuickCreationInput {
  title: string
  content: string
  tasks: string[]
}

function quickCreationMarkup(
  mode: 'note' | 'list',
): string {
  if (mode === 'list') {
    return `
      <div class="swal-quick-note-form">
        <label for="swal-note-title">
          Titre
        </label>

        <input
          id="swal-note-title"
          class="swal2-input"
          maxlength="255"
          placeholder="Titre de la liste"
          autocomplete="off"
        />

        <label for="swal-note-tasks">
          Tâches
        </label>

        <textarea
          id="swal-note-tasks"
          class="swal2-textarea"
          rows="7"
          placeholder="Une tâche par ligne"
        ></textarea>

        <small>
          Une ligne correspond à une tâche.
        </small>
      </div>
    `
  }

  return `
    <div class="swal-quick-note-form">
      <label for="swal-note-title">
        Titre
      </label>

      <input
        id="swal-note-title"
        class="swal2-input"
        maxlength="255"
        placeholder="Titre"
        autocomplete="off"
      />

      <label for="swal-note-content">
        Contenu
      </label>

      <textarea
        id="swal-note-content"
        class="swal2-textarea"
        rows="7"
        placeholder="Contenu de la note…"
      ></textarea>
    </div>
  `
}

function readQuickCreation(
  mode: 'note' | 'list',
): QuickCreationInput | false {
  const popup =
    Swal.getPopup()

  const titleInput =
    popup?.querySelector<HTMLInputElement>(
      '#swal-note-title',
    )

  const title =
    titleInput?.value.trim() ?? ''

  if (mode === 'list') {
    const taskInput =
      popup?.querySelector<HTMLTextAreaElement>(
        '#swal-note-tasks',
      )

    const tasks =
      (taskInput?.value ?? '')
        .split(/\r?\n/)
        .map(
          task => task.trim(),
        )
        .filter(
          task => task !== '',
        )

    if (
      title === ''
      && tasks.length === 0
    ) {
      Swal.showValidationMessage(
        'Ajoutez un titre ou au moins une tâche.',
      )

      return false
    }

    return {
      title,
      content: '',
      tasks,
    }
  }

  const contentInput =
    popup?.querySelector<HTMLTextAreaElement>(
      '#swal-note-content',
    )

  const content =
    contentInput?.value.trim() ?? ''

  if (
    title === ''
    && content === ''
  ) {
    Swal.showValidationMessage(
      'Ajoutez un titre ou du contenu.',
    )

    return false
  }

  return {
    title,
    content,
    tasks: [],
  }
}

async function createQuickNote(
  mode: 'note' | 'list',
): Promise<void> {
  const result =
    await Swal.fire<
      QuickCreationInput
    >({
      title:
        mode === 'list'
          ? 'Créer une liste'
          : 'Créer une note',

      html:
        quickCreationMarkup(mode),

      showCancelButton: true,
      confirmButtonText: 'Créer',
      cancelButtonText: 'Annuler',
      focusConfirm: false,

      didOpen: () => {
        Swal
          .getPopup()
          ?.querySelector<HTMLInputElement>(
            '#swal-note-title',
          )
          ?.focus()
      },

      preConfirm: () =>
        readQuickCreation(mode),
    })

  if (
    !result.isConfirmed
    || !result.value
  ) {
    return
  }

  error.value = ''

  try {
    const note =
      await createNote({
        title:
          result.value.title,

        content:
          result.value.content,

        noteType:
          mode === 'list'
            ? 'list'
            : 'text',

        tagIds: [],

        projectId:
          projectId.value,

        isPinned: false,

        color: '#FFFFFF',
      })

    if (mode === 'list') {
      for (
        const task
        of result.value.tasks
      ) {
        await createNoteTask(
          note.id,
          {
            title: task,
            description: '',
          },
        )
      }
    }

    await load()

    showSuccess(
      mode === 'list'
        ? 'Liste créée.'
        : 'Note créée.',
    )
  } catch (exception) {
    showError(
      exception instanceof Error
        ? exception.message
        : mode === 'list'
          ? 'Impossible de créer la liste.'
          : 'Impossible de créer la note.',
    )

    /*
     * Si la note est créée mais qu'une tâche
     * échoue ensuite, on recharge l'état réel
     * renvoyé par le serveur.
     */
    await load()
  }
}

async function openQuickComposer():
Promise<void> {
  await createQuickNote('note')
}

async function openQuickListComposer():
Promise<void> {
  await createQuickNote('list')
}

async function updateQuickAppearance(
  note: NoteSummary,
  changes: {
    isPinned?: boolean
    color?: string
  },
): Promise<void> {
  if (
    quickActionBusyId.value
    !== null
  ) {
    return
  }

  quickActionBusyId.value =
    note.id

  error.value = ''

  try {
    const updated =
      await updateNote(
        note.id,
        {
          title:
            note.title,
          content:
            note.content,
          tagIds:
            note.tags.map(
              tag => tag.id,
            ),
          isPinned:
            changes.isPinned
            ?? note.isPinned,
          color:
            changes.color
            ?? note.color,
        },
      )

    notes.value =
      notes.value
        .map(
          candidate =>
            candidate.id
            === note.id
              ? {
                  ...candidate,
                  isPinned:
                    updated.isPinned,
                  color:
                    updated.color,
                  updatedAt:
                    updated.updatedAt,
                }
              : candidate,
        )
        .sort(
          (first, second) => {
            if (
              first.isPinned
              !== second.isPinned
            ) {
              return first.isPinned
                ? -1
                : 1
            }

            return (
              Date.parse(
                second.updatedAt,
              )
              - Date.parse(
                first.updatedAt,
              )
            )
            || second.id
              - first.id
          },
        )
  } catch (exception) {
    if (
      isPinnedNoteLimitError(
        exception,
      )
    ) {
      await showPinnedNoteLimitAlert()
      return
    }

    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier la note.'
  } finally {
    quickActionBusyId.value =
      null
  }
}

async function toggleQuickPin(
  note: NoteSummary,
): Promise<void> {
  await updateQuickAppearance(
    note,
    {
      isPinned:
        !note.isPinned,
    },
  )
}

async function changeQuickColor(
  note: NoteSummary,
  event: Event,
): Promise<void> {
  const input =
    event.target as HTMLInputElement

  await updateQuickAppearance(
    note,
    {
      color:
        input.value,
    },
  )
}

async function quickArchive(
  note: NoteSummary,
): Promise<void> {
  if (
    quickActionBusyId.value
    !== null
  ) {
    return
  }

  quickActionBusyId.value =
    note.id

  error.value = ''

  try {
    await archiveNote(
      note.id,
    )

    await load()

    showSuccess(
      'Note archivée.',
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’archiver la note.'
  } finally {
    quickActionBusyId.value =
      null
  }
}

function boardPosition(
  note: NoteSummary,
  index: number,
): Record<string, string> {
  const hash =
    Math.abs(
      (
        note.id * 2654435761
        + boardSeed.value
      ) | 0,
    )

  const columns = 4
  const col = index % columns

  const row =
    Math.floor(
      index / columns,
    )

  const jitterX =
    (hash % 41) - 20

  const jitterY =
    (
      Math.floor(
        hash / 41,
      ) % 41
    ) - 20

  const rotation =
    (
      (
        Math.floor(
          hash / 1681,
        ) % 7
      ) - 3
    ) * 0.5

  return {
    left:
      `calc(${col * 25}% + ${jitterX + 12}px)`,

    top:
      `${row * 190 + jitterY + 28}px`,

    transform:
      `rotate(${rotation}deg)`,
  }
}

function noteExcerpt(
  note: NoteSummary,
): string {
  if (note.noteType !== 'text') {
    return ''
  }

  return note.content.trim()
    || 'Note vide'
}

function noteCardDate(
  note: NoteSummary,
): string {
  return (
    props.scope === 'trash'
    && note.deletedAt
  )
    ? note.deletedAt
    : note.updatedAt
}

watch(
  display,
  value =>
    localStorage.setItem(
      'homeen-note-display',
      value,
    ),
)

watch(
  () => [
    props.scope,
    route.query.q,
    route.query.projectId,
  ],
  () => {
    void load()
  },
)

onMounted(
  () => void load(),
)
</script>

<template>
  <section class="page notes-page">
    <header class="page-header">
      <div>
        <h1>
          {{ title }}
        </h1>

        <p
          v-if="query"
          class="muted"
        >
          Résultats pour « {{ query }} »
        </p>

        <p
          v-else-if="
            activeProject
          "
          class="muted"
        >
          Projet :
          {{ activeProject.name }}
        </p>

      </div>

      <div class="page-actions">
        <div
          v-if="scope !== 'trash'"
          class="segmented keep-view-switcher"
          aria-label="Affichage des notes"
        >
          <button
            :class="{
              active:
                display === 'grid',
            }"
            title="Grille"
            @click="
              display = 'grid'
            "
          >
            <AppIcon
              name="grid"
              :size="19"
            />
          </button>

          <button
            :class="{
              active:
                display === 'list',
            }"
            title="Liste"
            @click="
              display = 'list'
            "
          >
            <AppIcon
              name="list"
              :size="19"
            />
          </button>

          <button
            :class="{
              active:
                display === 'whiteboard',
            }"
            title="Tableau"
            @click="
              display = 'whiteboard'
            "
          >
            <AppIcon
              name="whiteboard"
              :size="19"
            />
          </button>
        </div>
      </div>
    </header>

    <div
      v-if="
        scope === 'active'
      "
      class="keep-note-composer-shell"
    >
      <button
        class="
          keep-note-composer
          keep-note-composer-main
        "
        type="button"
        @click="openQuickComposer"
      >
        <AppIcon
          name="note"
          :size="21"
        />

        <span>
          Créer une note…
        </span>
      </button>

      <button
        class="keep-note-composer-shortcut"
        type="button"
        title="Créer une liste"
        aria-label="Créer une liste"
        @click="openQuickListComposer"
      >
        <AppIcon
          name="list"
          :size="20"
        />
      </button>
    </div>



    <div
      v-if="loading"
      class="empty-state"
    >
      Chargement des notes…
    </div>

    <div
      v-else-if="notes.length === 0"
      class="empty-state"
    >
      <strong>
        {{
          query
            ? 'Aucun résultat.'
            : scope === 'trash'
              ? 'La corbeille est vide.'
              : 'Aucune note pour le moment.'
        }}
      </strong>

      <p
        v-if="
          scope === 'active'
          && !query
        "
      >
        Créez votre première note
        pour commencer.
      </p>
    </div>

    <div
      v-else-if="
        display === 'grid'
        || scope === 'trash'
      "
      class="keep-note-sections"
    >
      <section
        v-for="section in gridSections"
        :key="section.key"
        class="keep-note-section"
      >
        <h2
          v-if="section.title"
          class="keep-note-section-title"
        >
          {{ section.title }}
        </h2>

        <div
          class="notes-grid"
          :class="{
            'notes-grid--pinned':
              section.key === 'pinned',
          }"
        >
          <article
            v-for="note in section.notes"
            :key="note.id"
            class="note-card"
            :class="{
              pinned:
                note.isPinned,
            }"
            :style="{
              background:
                note.color,
            }"
            @click="openNote(note)"
          >
            <div
              v-if="
                !note.archivedAt
                && !note.deletedAt
              "
              class="note-card-quick-actions"
              @click.stop
            >
              <button
                type="button"
                class="note-card-action"
                :class="{
                  active:
                    note.isPinned,
                }"
                :disabled="
                  quickActionBusyId
                  !== null
                "
                :title="
                  note.isPinned
                    ? 'Désépingler'
                    : 'Épingler'
                "
                :aria-label="
                  note.isPinned
                    ? 'Désépingler la note'
                    : 'Épingler la note'
                "
                @click.stop="
                  toggleQuickPin(note)
                "
              >
                <AppIcon
                  name="pin"
                  :size="17"
                />
              </button>

              <label
                class="
                  note-card-action
                  note-card-color-action
                "
                title="Changer la couleur"
              >
                <span
                  class="note-card-color-dot"
                  :style="{
                    background:
                      note.color,
                  }"
                />

                <input
                  type="color"
                  :value="note.color"
                  :disabled="
                    quickActionBusyId
                    !== null
                  "
                  :aria-label="
                    `Changer la couleur de ${note.title || 'la note'}`
                  "
                  @change="
                    changeQuickColor(
                      note,
                      $event,
                    )
                  "
                />
              </label>

              <button
                type="button"
                class="note-card-action"
                :disabled="
                  quickActionBusyId
                  !== null
                "
                title="Archiver"
                aria-label="Archiver la note"
                @click.stop="
                  quickArchive(note)
                "
              >
                <AppIcon
                  name="archive"
                  :size="17"
                />
              </button>
            </div>

            <h2>
              {{
                note.title
                || 'Sans titre'
              }}
            </h2>

            <p
              v-if="
                note.noteType === 'text'
              "
              class="note-excerpt"
            >
              {{ noteExcerpt(note) }}
            </p>

            <div class="note-card-meta">
              <div
                v-if="
                  note.projectName
                  || note.tags.length > 0
                "
                class="note-card-tags"
              >
                <span
                  v-if="
                    note.projectName
                  "
                  class="
                    label-chip
                    project-chip
                  "
                  :style="{
                    '--label':
                      note.projectColor
                      ?? '#1A73E8',
                  }"
                >
                  <AppIcon
                    name="users"
                    :size="12"
                  />

                  {{
                    note.projectName
                  }}
                </span>

                <span
                  v-for="tag in note.tags"
                  :key="tag.id"
                  class="label-chip"
                  :style="{
                    '--label':
                      tag.color,
                  }"
                >
                  {{ tag.name }}
                </span>
              </div>

              <div class="note-card-status">
                <AppIcon
                  v-if="
                    note.isPinned
                  "
                  name="pin"
                  :size="16"
                />

                <span
                  v-if="
                    note.noteType === 'list'
                  "
                  class="task-ratio"
                >
                  {{
                    note.completedTaskCount
                  }}
                  /
                  {{ note.taskCount }}
                </span>
              </div>
            </div>

            <footer>
              <span>
                {{
                  scope === 'trash'
                    ? 'Supprimée'
                    : 'Modifiée'
                }}
              </span>

              {{
                formatDate(
                  noteCardDate(note),
                )
              }}
            </footer>
          </article>
        </div>
      </section>
    </div>

    <div
      v-else-if="
        display === 'list'
      "
      class="notes-list"
    >
      <button
        v-for="note in notes"
        :key="note.id"
        class="note-list-row"
        :class="{
          pinned:
            note.isPinned,
        }"
        :style="{
          background:
            note.color,
        }"
        @click="openNote(note)"
      >
        <div class="list-title">
          <strong>
            <AppIcon
              v-if="note.isPinned"
              name="pin"
              :size="14"
            />

            {{
              note.title
              || 'Sans titre'
            }}
          </strong>

          <span
            v-if="
              note.noteType === 'text'
            "
          >
            {{ noteExcerpt(note) }}
          </span>
        </div>

        <span
          v-if="note.projectName"
          class="label-chip"
          :style="{
            '--label':
              note.projectColor
              ?? '#1A73E8',
          }"
        >
          {{ note.projectName }}
        </span>

        <span
          v-if="note.noteType === 'list'"
        >
          {{ note.completedTaskCount }}
          /
          {{ note.taskCount }}
          tâches
        </span>

        <span>
          {{
            formatDate(
              note.updatedAt,
            )
          }}
        </span>
      </button>
    </div>

    <div
      v-else
      class="whiteboard-wrap"
    >
      <div class="whiteboard-toolbar">
        <span>
          Disposition libre
        </span>

        <button
          class="ghost"
          @click="
            boardSeed =
              Math.floor(
                Math.random()
                * 1_000_000,
              )
          "
        >
          Réorganiser
        </button>
      </div>

      <div
        class="whiteboard"
        :style="{
          minHeight:
            `${Math.ceil(notes.length / 4) * 190 + 80}px`,
        }"
      >
        <button
          v-for="(note, index) in notes"
          :key="note.id"
          class="board-note"
          :style="{
            ...boardPosition(
              note,
              index,
            ),
            background:
              note.color,
          }"
          @click="openNote(note)"
        >
          <strong>
            <AppIcon
              v-if="note.isPinned"
              name="pin"
              :size="14"
            />

            {{
              note.title
              || 'Sans titre'
            }}
          </strong>

          <span
            v-if="
              note.noteType === 'text'
            "
          >
            {{
              noteExcerpt(note)
                .slice(0, 150)
            }}
          </span>

          <small
            v-if="note.noteType === 'list'"
          >
            {{ note.completedTaskCount }}
            /
            {{ note.taskCount }}
            tâches
          </small>
        </button>
      </div>
    </div>



  </section>
</template>
