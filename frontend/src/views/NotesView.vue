<script setup lang="ts">
import {
  computed,
  nextTick,
  onMounted,
  ref,
  watch,
} from 'vue'

import { useRoute } from 'vue-router'

import BaseModal from '../components/BaseModal.vue'
import AppIcon from '../components/AppIcon.vue'
import NoteEditor from '../components/NoteEditor.vue'

import { api } from '../services/api'
import { formatDate } from '../services/format'

import {
  useTags,
} from '../composables/useTags'

import {
  useToast,
} from '../composables/useToast'

import type {
  ImageAsset,
  Note,
  NoteCollection,
  NoteSummary,
} from '../types/domain'

const props =
  defineProps<{
    scope:
      | 'active'
      | 'archived'
      | 'trash'
  }>()

const route = useRoute()

const {
  success:
    showSuccess,
} = useToast()

const notes =
  ref<NoteSummary[]>([])

const {
  tags,
  load: loadTags,
} = useTags()

const collections =
  ref<NoteCollection[]>([])

const collectionFilter =
  ref<number | null>(null)

const loading = ref(true)
const error = ref('')

const quickComposerOpen =
  ref(false)

const quickMode =
  ref<'note' | 'list'>(
    'note',
  )

const quickTitle =
  ref('')

const quickContent =
  ref('')

const quickTasks =
  ref<string[]>([
    '',
  ])

const quickSaving =
  ref(false)

const quickActionBusyId =
  ref<number | null>(null)

const quickContentInput =
  ref<HTMLTextAreaElement | null>(
    null,
  )

const quickTitleInput =
  ref<HTMLInputElement | null>(
    null,
  )

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

const modalOpen = ref(false)

const selected =
  ref<Note | null>(null)

const boardSeed =
  ref(
    Math.floor(
      Math.random()
      * 1_000_000,
    ),
  )

const collectionFormOpen =
  ref(false)

const collectionName =
  ref('')

const collectionColor =
  ref('#1A73E8')

const creatingCollection =
  ref(false)

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

const activeCollection =
  computed(
    () =>
      collections.value.find(
        collection =>
          collection.id
          === collectionFilter.value,
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

const quickCanCreate =
  computed(
    () => {
      if (
        quickTitle.value.trim()
        !== ''
      ) {
        return true
      }

      if (
        quickMode.value
        === 'note'
      ) {
        return (
          quickContent.value.trim()
          !== ''
        )
      }

      return quickTasks.value.some(
        task =>
          task.trim() !== '',
      )
    },
  )

async function load():
Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const params =
      new URLSearchParams({
        scope: props.scope,
      })

    if (query.value.trim()) {
      params.set(
        'q',
        query.value.trim(),
      )
    }

    if (
      collectionFilter.value
      !== null
    ) {
      params.set(
        'collectionId',
        String(
          collectionFilter.value,
        ),
      )
    }

    const [
      noteResponse,
      collectionResponse,
    ] = await Promise.all([
      api<{
        notes: NoteSummary[]
      }>(
        `/api/notes?${params}`,
      ),

      api<{
        collections:
          NoteCollection[]
      }>(
        '/api/collections',
      ),

      loadTags(),
    ])

    notes.value =
      noteResponse.notes

    collections.value =
      collectionResponse.collections

    if (
      collectionFilter.value
      !== null
      && !collections.value.some(
        collection =>
          collection.id
          === collectionFilter.value,
      )
    ) {
      collectionFilter.value = null
    }

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

async function openNote(
  summary: NoteSummary,
): Promise<void> {
  selected.value =
    await api<Note>(
      `/api/notes/${summary.id}`,
    )

  modalOpen.value = true
}

async function openQuickComposer():
Promise<void> {
  quickMode.value = 'note'
  quickComposerOpen.value = true

  await nextTick()

  quickContentInput.value?.focus()
}

async function openQuickListComposer():
Promise<void> {
  quickMode.value = 'list'
  quickComposerOpen.value = true

  await nextTick()

  quickTitleInput.value?.focus()
}

function resetQuickComposer(): void {
  quickComposerOpen.value = false
  quickMode.value = 'note'
  quickTitle.value = ''
  quickContent.value = ''
  quickTasks.value = ['']
}

function closeQuickComposer(): void {
  if (quickSaving.value) {
    return
  }

  resetQuickComposer()
}

function addQuickTask(): void {
  quickTasks.value.push('')
}

function removeQuickTask(
  index: number,
): void {
  if (
    quickTasks.value.length
    === 1
  ) {
    quickTasks.value[0] = ''
    return
  }

  quickTasks.value.splice(
    index,
    1,
  )
}

async function createQuickNote():
Promise<void> {
  if (
    quickSaving.value
    || !quickCanCreate.value
  ) {
    return
  }

  const title =
    quickTitle.value.trim()

  const content =
    quickMode.value === 'note'
      ? quickContent.value.trim()
      : ''

  const tasks =
    quickTasks.value
      .map(
        task => task.trim(),
      )
      .filter(
        task => task !== '',
      )

  quickSaving.value = true
  error.value = ''

  try {
    const note =
      await api<Note>(
        '/api/notes',
        {
          method: 'POST',
          body: JSON.stringify({
            title,
            content,
            tagIds: [],
            collectionId:
              collectionFilter.value,
            isPinned: false,
            color: '#FFFFFF',
          }),
        },
      )

    if (
      quickMode.value === 'list'
    ) {
      for (
        const task
        of tasks
      ) {
        await api(
          `/api/notes/${note.id}/tasks`,
          {
            method: 'POST',
            body: JSON.stringify({
              content: task,
            }),
          },
        )
      }
    }

    resetQuickComposer()

    await load()

    showSuccess(
      tasks.length > 0
        ? 'Liste créée.'
        : 'Note créée.',
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de créer la note.'

    /*
     * A failure after note creation may leave
     * the successfully created portion intact.
     * Reload so the UI reflects server state.
     */
    await load()
  } finally {
    quickSaving.value = false
  }
}

async function createImageNote(
  event: Event,
): Promise<void> {
  const input =
    event.target as HTMLInputElement

  const file =
    input.files?.[0]

  input.value = ''

  if (
    !file
    || quickSaving.value
  ) {
    return
  }

  quickSaving.value = true
  error.value = ''

  try {
    const form =
      new FormData()

    form.append(
      'image',
      file,
    )

    const image =
      await api<ImageAsset>(
        '/api/images',
        {
          method: 'POST',
          body: form,
        },
      )

    const note =
      await api<Note>(
        '/api/notes',
        {
          method: 'POST',
          body: JSON.stringify({
            title: '',
            content: '',
            tagIds: [],
            collectionId:
              collectionFilter.value,
            isPinned: false,
            color: '#FFFFFF',
          }),
        },
      )

    await api(
      `/api/images/${image.id}/notes/${note.id}`,
      {
        method: 'POST',
      },
    )

    await load()

    selected.value =
      await api<Note>(
        `/api/notes/${note.id}`,
      )

    modalOpen.value = true

    showSuccess(
      'Note avec image créée.',
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de créer la note avec image.'

    await load()
  } finally {
    quickSaving.value = false
  }
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
      await api<Note>(
        `/api/notes/${note.id}`,
        {
          method: 'PUT',
          body: JSON.stringify({
            title:
              note.title,
            content:
              note.content,
            tagIds:
              note.tags.map(
                tag => tag.id,
              ),
            collectionId:
              note.collectionId,
            isPinned:
              changes.isPinned
              ?? note.isPinned,
            color:
              changes.color
              ?? note.color,
          }),
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
    await api<Note>(
      `/api/notes/${note.id}/archive`,
      {
        method: 'POST',
      },
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

async function createCollection():
Promise<void> {
  const name =
    collectionName.value.trim()

  if (
    !name
    || creatingCollection.value
  ) {
    return
  }

  creatingCollection.value = true
  error.value = ''

  try {
    const collection =
      await api<NoteCollection>(
        '/api/collections',
        {
          method: 'POST',
          body: JSON.stringify({
            name,
            color:
              collectionColor.value,
          }),
        },
      )

    collectionName.value = ''
    collectionFormOpen.value = false
    collectionFilter.value =
      collection.id

    await load()

    showSuccess(
      'Collection créée.',
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de créer la collection.'
  } finally {
    creatingCollection.value = false
  }
}

async function deleteCollection(
  collection: NoteCollection,
): Promise<void> {
  const confirmed =
    window.confirm(
      `Supprimer la collection « ${collection.name} » ?\n\nLes notes resteront disponibles mais ne seront plus rangées dans cette collection.`,
    )

  if (!confirmed) {
    return
  }

  await api(
    `/api/collections/${collection.id}`,
    {
      method: 'DELETE',
    },
  )

  if (
    collectionFilter.value
    === collection.id
  ) {
    collectionFilter.value = null
  }

  await load()

  showSuccess(
    'Collection supprimée.',
  )
}

function selectCollection(
  collectionId: number | null,
): void {
  if (
    collectionFilter.value
    === collectionId
  ) {
    return
  }

  collectionFilter.value =
    collectionId
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
  ],
  () => {
    collectionFilter.value = null
    void load()
  },
)

watch(
  collectionFilter,
  () => void load(),
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
            activeCollection
          "
          class="muted"
        >
          Collection :
          {{ activeCollection.name }}
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

    <section
      v-if="scope === 'active'"
      class="collection-shelf"
    >
      <div class="collection-shelf-header">
        <div>
          <h2>
            Collections
          </h2>

          <p class="muted">
            Regroupez vos notes
            par thème ou par usage.
          </p>
        </div>

        <button
          class="ui-button ui-button--secondary collection-toggle-button"
          type="button"
          @click="
            collectionFormOpen =
              !collectionFormOpen
          "
        >
          <AppIcon
            name="plus"
            :size="17"
          />

          Nouvelle collection
        </button>
      </div>

      <form
        v-if="collectionFormOpen"
        class="collection-create-form"
        @submit.prevent="createCollection"
      >
        <input
          v-model.trim="collectionName"
          maxlength="80"
          placeholder="Nom de la collection"
          autofocus
          required
        />

        <input
          v-model="collectionColor"
          class="color-input"
          type="color"
          aria-label="Couleur de la collection"
        />

        <button
          class="ui-button ui-button--primary collection-create-submit"
          :disabled="
            creatingCollection
            || !collectionName
          "
        >
          {{
            creatingCollection
              ? 'Création…'
              : 'Créer'
          }}
        </button>
      </form>

      <div class="collection-chip-list">
        <button
          class="collection-chip"
          :class="{
            active:
              collectionFilter
              === null,
          }"
          type="button"
          @click="
            selectCollection(null)
          "
        >
          Toutes
        </button>

        <div
          v-for="collection in collections"
          :key="collection.id"
          class="collection-chip-wrap"
        >
          <button
            class="collection-chip"
            :class="{
              active:
                collectionFilter
                === collection.id,
            }"
            type="button"
            @click="
              selectCollection(
                collection.id,
              )
            "
          >
            <span
              class="collection-dot"
              :style="{
                background:
                  collection.color,
              }"
            />

            <span>
              {{ collection.name }}
            </span>

            <small>
              {{ collection.noteCount }}
            </small>
          </button>

          <button
            class="collection-delete"
            type="button"
            :aria-label="
              `Supprimer ${collection.name}`
            "
            title="Supprimer la collection"
            @click="
              deleteCollection(
                collection,
              )
            "
          >
            ×
          </button>
        </div>
      </div>
    </section>

    <div
      v-if="
        scope === 'active'
        && !quickComposerOpen
      "
      class="keep-note-composer-shell"
    >
      <button
        class="keep-note-composer keep-note-composer-main"
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

      <label
        class="keep-note-composer-shortcut"
        title="Créer une note avec une image"
        aria-label="Créer une note avec une image"
      >
        <AppIcon
          name="image"
          :size="20"
        />

        <input
          class="image-file-input"
          type="file"
          accept="image/jpeg,image/png,image/webp,image/gif"
          :disabled="quickSaving"
          @change="createImageNote"
        />
      </label>

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

    <form
      v-else-if="
        scope === 'active'
      "
      class="keep-quick-composer"
      @submit.prevent="
        createQuickNote
      "
    >
      <div class="keep-quick-mode-heading">
        <AppIcon
          :name="
            quickMode === 'list'
              ? 'list'
              : 'note'
          "
          :size="18"
        />

        <span>
          {{
            quickMode === 'list'
              ? 'Nouvelle liste'
              : 'Nouvelle note'
          }}
        </span>
      </div>

      <input
        ref="quickTitleInput"
        v-model="quickTitle"
        class="keep-quick-title"
        maxlength="255"
        placeholder="Titre"
        aria-label="Titre de la note"
      />

      <textarea
        v-if="
          quickMode === 'note'
        "
        ref="quickContentInput"
        v-model="quickContent"
        class="keep-quick-content"
        placeholder="Créer une note…"
        aria-label="Contenu de la note"
        rows="3"
        @keydown.ctrl.enter.prevent="
          createQuickNote
        "
        @keydown.meta.enter.prevent="
          createQuickNote
        "
        @keydown.esc="
          closeQuickComposer
        "
      />

      <div
        v-else
        class="keep-quick-list"
      >
        <div
          v-for="(_, index) in quickTasks"
          :key="index"
          class="keep-quick-task"
        >
          <input
            v-model="
              quickTasks[index]
            "
            maxlength="4000"
            :placeholder="
              `Élément ${index + 1}`
            "
            :aria-label="
              `Élément ${index + 1}`
            "
            @keydown.ctrl.enter.prevent="
              createQuickNote
            "
            @keydown.meta.enter.prevent="
              createQuickNote
            "
            @keydown.esc="
              closeQuickComposer
            "
          />

          <button
            type="button"
            title="Retirer cet élément"
            aria-label="Retirer cet élément"
            @click="
              removeQuickTask(index)
            "
          >
            <AppIcon
              name="close"
              :size="15"
            />
          </button>
        </div>

        <button
          type="button"
          class="keep-quick-add-task"
          @click="addQuickTask"
        >
          <AppIcon
            name="plus"
            :size="16"
          />

          Ajouter un élément
        </button>
      </div>

      <footer class="keep-quick-actions">
        <span class="muted">
          Ctrl/Cmd + Entrée pour créer
        </span>

        <div>
          <button
            type="button"
            class="keep-quick-close"
            :disabled="quickSaving"
            @click="
              closeQuickComposer
            "
          >
            Fermer
          </button>

          <button
            type="submit"
            class="keep-quick-create"
            :disabled="
              quickSaving
              || !quickCanCreate
            "
          >
            {{
              quickSaving
                ? 'Création…'
                : 'Créer'
            }}
          </button>
        </div>
      </footer>
    </form>

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
            : activeCollection
              ? 'Cette collection est vide.'
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

        <div class="notes-grid">
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
            <img
              v-if="
                note.previewImageUrl
              "
              class="note-card-preview"
              :src="
                note.previewImageUrl
              "
              alt=""
              loading="lazy"
            />

            <div
              v-if="
                scope === 'active'
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

            <div class="note-card-top">
              <div class="note-card-tags">
                <span
                  v-if="
                    note.collectionName
                  "
                  class="collection-label"
                  :style="{
                    '--collection':
                      note.collectionColor
                      ?? '#1A73E8',
                  }"
                >
                  {{
                    note.collectionName
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
                    note.taskCount > 0
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

            <h2>
              {{
                note.title
                || 'Sans titre'
              }}
            </h2>

            <p class="note-excerpt">
              {{ noteExcerpt(note) }}
            </p>

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

          <span>
            {{ noteExcerpt(note) }}
          </span>
        </div>

        <span
          v-if="note.collectionName"
          class="collection-label"
          :style="{
            '--collection':
              note.collectionColor
              ?? '#1A73E8',
          }"
        >
          {{ note.collectionName }}
        </span>

        <span
          v-if="note.taskCount > 0"
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
          <span
            v-if="note.collectionName"
            class="collection-label"
            :style="{
              '--collection':
                note.collectionColor
                ?? '#1A73E8',
            }"
          >
            {{ note.collectionName }}
          </span>

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

          <span>
            {{
              noteExcerpt(note)
                .slice(0, 150)
            }}
          </span>

          <small
            v-if="note.taskCount > 0"
          >
            {{ note.completedTaskCount }}
            /
            {{ note.taskCount }}
            tâches
          </small>
        </button>
      </div>
    </div>

    <BaseModal
      :open="modalOpen"
      :title="
        selected
          ? (
              selected.title
              || 'Note sans titre'
            )
          : 'Nouvelle note'
      "
      @close="
        modalOpen = false
      "
    >
      <NoteEditor
        :key="
          selected?.id
          ?? `new-note-${collectionFilter ?? 'all'}`
        "
        :note="selected"
        :tags="tags"
        :collections="collections"
        :default-collection-id="
          collectionFilter
        "
        @saved="
          (note) => {
            selected = note
            void load()
          }
        "
        @changed="load"
        @closed="
          modalOpen = false
        "
      />
    </BaseModal>
  </section>
</template>
