<script setup lang="ts">
import {
  computed,
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

import type {
  Label,
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

const notes =
  ref<NoteSummary[]>([])

const labels =
  ref<Label[]>([])

const collections =
  ref<NoteCollection[]>([])

const collectionFilter =
  ref<number | null>(null)

const loading = ref(true)
const error = ref('')

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
      labelResponse,
      collectionResponse,
    ] = await Promise.all([
      api<{
        notes: NoteSummary[]
      }>(
        `/api/notes?${params}`,
      ),

      api<{
        labels: Label[]
      }>(
        '/api/labels',
      ),

      api<{
        collections:
          NoteCollection[]
      }>(
        '/api/collections',
      ),
    ])

    notes.value =
      noteResponse.notes

    labels.value =
      labelResponse.labels

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

function newNote(): void {
  selected.value = null
  modalOpen.value = true
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
          Projet :
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
            par projet ou par thème.
          </p>
        </div>

        <button
          class="secondary"
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
          placeholder="Nom du projet ou de la collection"
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
          class="primary"
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

    <button
      v-if="scope === 'active'"
      class="keep-note-composer"
      @click="newNote"
    >
      <AppIcon
        name="note"
        :size="21"
      />

      <span>
        Créer une note…
      </span>

      <span class="keep-composer-actions">
        <AppIcon
          name="plus"
          :size="19"
        />
      </span>
    </button>

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
      class="notes-grid"
    >
      <article
        v-for="note in notes"
        :key="note.id"
        class="note-card"
        @click="openNote(note)"
      >
        <div class="note-card-top">
          <div class="note-card-tags">
            <span
              v-if="note.collectionName"
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
              v-if="note.labelName"
              class="label-chip"
              :style="{
                '--label':
                  note.labelColor
                  ?? '#64748B',
              }"
            >
              {{ note.labelName }}
            </span>
          </div>

          <span class="task-ratio">
            {{ note.completedTaskCount }}
            /
            {{ note.taskCount }}
          </span>
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
        @click="openNote(note)"
      >
        <div class="list-title">
          <strong>
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

        <span>
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
          :style="
            boardPosition(
              note,
              index,
            )
          "
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

          <small>
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
        :labels="labels"
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
