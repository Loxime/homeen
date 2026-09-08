<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
  watch,
} from 'vue'

import BaseModal from './BaseModal.vue'
import AppIcon from './AppIcon.vue'
import ChannelNoteEditor from './ChannelNoteEditor.vue'

import { api } from '../services/api'
import { formatDate } from '../services/format'

import type {
  ChannelNote,
  ChannelNoteSummary,
} from '../types/channel'

const props = defineProps<{
  channelCode: string
}>()

type Scope =
  | 'active'
  | 'archived'
  | 'trash'

const scope =
  ref<Scope>('active')

const notes =
  ref<ChannelNoteSummary[]>([])

const loading = ref(true)
const error = ref('')

const modalOpen = ref(false)

const selected =
  ref<ChannelNote | null>(null)

const title = computed(() => {
  if (scope.value === 'archived') {
    return 'Notes archivées'
  }

  if (scope.value === 'trash') {
    return 'Corbeille'
  }

  return 'Notes'
})

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const response =
      await api<{
        notes: ChannelNoteSummary[]
      }>(
        `/api/channels/${props.channelCode}/notes?scope=${scope.value}`,
      )

    notes.value =
      response.notes
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les notes du canal.'
  } finally {
    loading.value = false
  }
}

async function openNote(
  summary: ChannelNoteSummary,
): Promise<void> {
  error.value = ''

  try {
    selected.value =
      await api<ChannelNote>(
        `/api/channels/${props.channelCode}/notes/${summary.id}`,
      )

    modalOpen.value = true
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’ouvrir la note.'
  }
}

function newNote(): void {
  selected.value = null
  modalOpen.value = true
}

function noteExcerpt(
  note: ChannelNoteSummary,
): string {
  return note.content.trim()
    || 'Note vide'
}

function noteCardDate(
  note: ChannelNoteSummary,
): string {
  if (
    scope.value === 'trash'
    && note.deletedAt
  ) {
    return note.deletedAt
  }

  return note.updatedAt
}

function closeEditor(): void {
  modalOpen.value = false
}

function saved(
  note: ChannelNote,
): void {
  selected.value = note

  void load()
}

watch(
  scope,
  () => {
    void load()
  },
)

watch(
  () => props.channelCode,
  () => {
    selected.value = null
    modalOpen.value = false
    scope.value = 'active'

    void load()
  },
)

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="channel-notes-panel">
    <div class="channel-notes-toolbar">
      <div>
        <h2>
          {{ title }}
        </h2>

        <p class="muted">
          Notes partagées avec tous les
          membres du canal.
        </p>
      </div>

      <div
        class="segmented channel-note-scope"
        aria-label="État des notes"
      >
        <button
          type="button"
          :class="{
            active:
              scope === 'active',
          }"
          @click="
            scope = 'active'
          "
        >
          Actives
        </button>

        <button
          type="button"
          :class="{
            active:
              scope === 'archived',
          }"
          @click="
            scope = 'archived'
          "
        >
          Archivées
        </button>

        <button
          type="button"
          :class="{
            active:
              scope === 'trash',
          }"
          @click="
            scope = 'trash'
          "
        >
          Corbeille
        </button>
      </div>
    </div>

    <button
      v-if="scope === 'active'"
      class="keep-note-composer channel-note-composer"
      type="button"
      @click="newNote"
    >
      <AppIcon
        name="note"
        :size="21"
      />

      <span>
        Écrire une note partagée…
      </span>

      <span class="keep-composer-actions">
        <AppIcon
          name="check"
          :size="19"
        />

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
          scope === 'trash'
            ? 'La corbeille est vide.'
            : scope === 'archived'
              ? 'Aucune note archivée.'
              : 'Aucune note partagée.'
        }}
      </strong>

      <p
        v-if="scope === 'active'"
      >
        Créez la première note du canal.
      </p>
    </div>

    <div
      v-else
      class="notes-grid channel-notes-grid"
    >
      <article
        v-for="note in notes"
        :key="note.id"
        class="note-card"
        @click="openNote(note)"
      >
        <div class="note-card-top">
          <span class="task-ratio">
            {{
              note.completedTaskCount
            }}
            /
            {{ note.taskCount }}
          </span>

          <span class="channel-note-version">
            v{{ note.version }}
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

        <p
          v-if="note.createdByEmail"
          class="channel-note-author"
        >
          {{ note.createdByEmail }}
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

    <BaseModal
      :open="modalOpen"
      :title="
        selected
          ? (
              selected.title
              || 'Note sans titre'
            )
          : 'Nouvelle note partagée'
      "
      @close="closeEditor"
    >
      <ChannelNoteEditor
        :key="
          selected
            ? `${selected.id}-${selected.version}`
            : 'new-channel-note'
        "
        :channel-code="channelCode"
        :note="selected"
        @saved="saved"
        @changed="load"
        @closed="closeEditor"
      />
    </BaseModal>
  </div>
</template>
