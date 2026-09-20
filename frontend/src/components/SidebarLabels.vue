<script setup lang="ts">
import {
  onMounted,
  ref,
} from 'vue'

import AppIcon from './AppIcon.vue'

import {
  useLabels,
} from '../composables/useLabels'

import {
  useToast,
} from '../composables/useToast'

import type {
  Label,
} from '../types/domain'

const {
  labels,
  loading,
  error,
  load,
  create,
  update,
  remove,
} = useLabels()

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
  label: Label,
): void {
  editingId.value =
    label.id

  editName.value =
    label.name

  editColor.value =
    label.color
}

function cancelEdit(): void {
  editingId.value = null
}

async function createLabel():
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
      'Libellé créé.',
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
  label: Label,
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
    label.id

  try {
    await update(
      label.id,
      value,
      editColor.value,
    )

    editingId.value = null

    showSuccess(
      'Libellé modifié.',
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

async function deleteLabel(
  label: Label,
): Promise<void> {
  const confirmed =
    window.confirm(
      `Supprimer le libellé « ${label.name} » ? Les notes associées n’auront plus de libellé.`,
    )

  if (
    !confirmed
    || busyId.value !== null
  ) {
    return
  }

  busyId.value =
    label.id

  try {
    await remove(
      label.id,
    )

    showSuccess(
      'Libellé supprimé.',
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
        createLabel
      "
    >
      <input
        v-model.trim="name"
        maxlength="80"
        placeholder="Nouveau libellé"
        required
      />

      <input
        v-model="color"
        class="sidebar-label-color"
        type="color"
        aria-label="Couleur du libellé"
      />

      <button
        type="submit"
        class="sidebar-label-add"
        :disabled="
          creating
          || !name
        "
        aria-label="Créer le libellé"
        title="Créer le libellé"
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
        && labels.length === 0
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
        v-for="label in labels"
        :key="label.id"
        class="sidebar-label-item"
        :class="{
          editing:
            editingId === label.id,
        }"
      >
        <template
          v-if="
            editingId
            === label.id
          "
        >
          <input
            v-model="editColor"
            class="sidebar-label-color"
            type="color"
            aria-label="Couleur du libellé"
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
              busyId === label.id
            "
            title="Enregistrer"
            aria-label="Enregistrer"
            @click="
              save(label)
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
                label.color,
            }"
          />

          <button
            type="button"
            class="sidebar-label-name"
            :title="
              `Modifier ${label.name}`
            "
            @click="
              beginEdit(label)
            "
          >
            {{ label.name }}
          </button>

          <span
            class="sidebar-label-count"
            :title="
              `${label.noteCount} note${label.noteCount > 1 ? 's' : ''}`
            "
          >
            {{ label.noteCount }}
          </span>

          <button
            type="button"
            class="
              sidebar-label-icon
              danger
            "
            :disabled="
              busyId === label.id
            "
            title="Supprimer"
            aria-label="Supprimer"
            @click="
              deleteLabel(label)
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
          labels.length === 0
          && !loading
        "
        class="sidebar-label-empty"
      >
        Aucun libellé.
      </div>
    </div>
  </div>
</template>
