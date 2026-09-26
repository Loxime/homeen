<script setup lang="ts">
import {
  onMounted,
  ref,
} from 'vue'

import AppIcon from './AppIcon.vue'

import {
  useTags,
} from '../composables/useTags'

import {
  useToast,
} from '../composables/useToast'

import type {
  Tag,
} from '../types/domain'

const {
  tags,
  loading,
  error,
  load,
  create,
  update,
  remove,
} = useTags()

const {
  success:
    showSuccess,
} = useToast()

const name =
  ref('')

const color =
  ref('#5B67F1')

const creating =
  ref(false)

const editingId =
  ref<number | null>(
    null,
  )

const editName =
  ref('')

const editColor =
  ref('#5B67F1')

const busyId =
  ref<number | null>(
    null,
  )

function beginEdit(
  tag: Tag,
): void {
  editingId.value =
    tag.id

  editName.value =
    tag.name

  editColor.value =
    tag.color
}

function cancelEdit(): void {
  editingId.value = null
}

async function createTag():
Promise<void> {
  const value =
    name.value.trim()

  if (
    !value
    || creating.value
  ) {
    return
  }

  creating.value = true

  try {
    await create(
      value,
      color.value,
    )

    name.value = ''

    showSuccess(
      'Tag créé.',
    )
  } catch {
    /*
     * Le store expose déjà
     * le message d'erreur.
     */
  } finally {
    creating.value = false
  }
}

async function save(
  tag: Tag,
): Promise<void> {
  const value =
    editName.value.trim()

  if (
    !value
    || busyId.value !== null
  ) {
    return
  }

  busyId.value =
    tag.id

  try {
    await update(
      tag.id,
      value,
      editColor.value,
    )

    editingId.value = null

    showSuccess(
      'Tag modifié.',
    )
  } catch {
    /*
     * L'erreur reste visible
     * directement dans la sidebar.
     */
  } finally {
    busyId.value = null
  }
}

async function deleteTag(
  tag: Tag,
): Promise<void> {
  const confirmed =
    window.confirm(
      `Supprimer le tag « ${tag.name} » ? Il sera retiré des tâches associées.`,
    )

  if (
    !confirmed
    || busyId.value !== null
  ) {
    return
  }

  busyId.value =
    tag.id

  try {
    await remove(
      tag.id,
    )

    showSuccess(
      'Tag supprimé.',
    )
  } catch {
    /*
     * L'erreur reste visible
     * directement dans la sidebar.
     */
  } finally {
    busyId.value = null
  }
}

onMounted(
  () => {
    void load()
  },
)
</script>

<template>
  <div class="sidebar-label-manager">
    <form
      class="sidebar-label-create"
      @submit.prevent="
        createTag
      "
    >
      <input
        v-model.trim="name"
        maxlength="80"
        placeholder="Nouveau tag"
        required
      />

      <input
        v-model="color"
        class="sidebar-label-color"
        type="color"
        aria-label="Couleur du tag"
      />

      <button
        type="submit"
        class="sidebar-label-add"
        :disabled="
          creating
          || !name
        "
        aria-label="Créer le tag"
        title="Créer le tag"
      >
        <AppIcon
          name="plus"
          :size="17"
        />
      </button>
    </form>

    <p
      v-if="error"
      class="sidebar-label-error"
    >
      {{ error }}
    </p>

    <div
      v-if="
        loading
        && tags.length === 0
      "
      class="sidebar-label-empty"
    >
      Chargement…
    </div>

    <div
      v-else
      class="sidebar-label-list"
    >
      <div
        v-for="tag in tags"
        :key="tag.id"
        class="sidebar-label-item"
        :class="{
          editing:
            editingId === tag.id,
        }"
      >
        <template
          v-if="
            editingId
            === tag.id
          "
        >
          <input
            v-model="editColor"
            class="sidebar-label-color"
            type="color"
            aria-label="Couleur du tag"
          />

          <input
            v-model.trim="editName"
            class="sidebar-label-edit-name"
            maxlength="80"
          />

          <button
            type="button"
            class="sidebar-label-icon"
            :disabled="
              busyId === tag.id
            "
            title="Enregistrer"
            aria-label="Enregistrer"
            @click="
              save(tag)
            "
          >
            <AppIcon
              name="check"
              :size="16"
            />
          </button>

          <button
            type="button"
            class="sidebar-label-icon"
            title="Annuler"
            aria-label="Annuler"
            @click="
              cancelEdit
            "
          >
            <AppIcon
              name="close"
              :size="16"
            />
          </button>
        </template>

        <template v-else>
          <span
            class="sidebar-label-dot"
            :style="{
              background:
                tag.color,
            }"
          />

          <button
            type="button"
            class="sidebar-label-name"
            :title="
              `Modifier ${tag.name}`
            "
            @click="
              beginEdit(tag)
            "
          >
            {{ tag.name }}
          </button>

          <span
            class="sidebar-label-count"
            :title="
              `${tag.taskCount ?? 0} tâche${(tag.taskCount ?? 0) > 1 ? 's' : ''}`
            "
          >
            {{ tag.taskCount ?? 0 }}
          </span>

          <button
            type="button"
            class="
              sidebar-label-icon
              danger
            "
            :disabled="
              busyId === tag.id
            "
            title="Supprimer"
            aria-label="Supprimer"
            @click="
              deleteTag(tag)
            "
          >
            <AppIcon
              name="trash"
              :size="15"
            />
          </button>
        </template>
      </div>

      <div
        v-if="
          tags.length === 0
          && !loading
        "
        class="sidebar-label-empty"
      >
        Aucun tag.
      </div>
    </div>
  </div>
</template>
