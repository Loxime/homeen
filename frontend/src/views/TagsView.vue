<script setup lang="ts">
import {
  onMounted,
  ref,
} from 'vue'

import Swal from 'sweetalert2'

import AppIcon from '../components/AppIcon.vue'

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
  load,
  create,
  update,
  remove,
} = useTags()

const {
  success:
    showSuccess,
} = useToast()

const newName = ref('')
const newColor = ref('#5B67F1')
const creating = ref(false)

const editingId =
  ref<number | null>(null)

const editName = ref('')
const editColor = ref('#5B67F1')

const busyId =
  ref<number | null>(null)

function beginEdit(
  tag: Tag,
): void {
  editingId.value = tag.id
  editName.value = tag.name
  editColor.value = tag.color
}

function cancelEdit(): void {
  editingId.value = null
}

async function createTag():
Promise<void> {
  const name =
    newName.value.trim()

  if (
    !name
    || creating.value
  ) {
    return
  }

  creating.value = true

  try {
    await create(
      name,
      newColor.value,
    )

    newName.value = ''

    showSuccess(
      'Tag créé.',
    )
  } catch {
    // useTags expose déjà le message.
  } finally {
    creating.value = false
  }
}

async function saveTag(
  tag: Tag,
): Promise<void> {
  const name =
    editName.value.trim()

  if (
    !name
    || busyId.value !== null
  ) {
    return
  }

  busyId.value = tag.id

  try {
    await update(
      tag.id,
      name,
      editColor.value,
    )

    editingId.value = null

    showSuccess(
      'Tag modifié.',
    )
  } catch {
    // useTags expose déjà le message.
  } finally {
    busyId.value = null
  }
}

async function deleteTag(
  tag: Tag,
): Promise<void> {
  if (busyId.value !== null) {
    return
  }

  const result =
    await Swal.fire({
      icon: 'warning',
      title: 'Supprimer ce tag ?',
      text:
        `« ${tag.name} » sera retiré des notes et tâches associées.`,
      showCancelButton: true,
      confirmButtonText: 'Supprimer',
      cancelButtonText: 'Annuler',
      focusCancel: true,
    })

  if (!result.isConfirmed) {
    return
  }

  busyId.value = tag.id

  try {
    await remove(tag.id)

    if (
      editingId.value
      === tag.id
    ) {
      editingId.value = null
    }

    showSuccess(
      'Tag supprimé.',
    )
  } catch {
    // useTags expose déjà le message.
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
  <section class="page tags-page">
    <header class="page-header">
      <div>
        <h1>
          Tags
        </h1>

        <p class="muted">
          Organisez les tags utilisés dans vos
          notes et vos tâches.
        </p>
      </div>
    </header>

    <form
      class="tag-create-card"
      @submit.prevent="
        createTag
      "
    >
      <div class="tag-create-copy">
        <strong>
          Nouveau tag
        </strong>

        <span class="muted">
          Choisissez un nom et une couleur.
        </span>
      </div>

      <input
        v-model.trim="newName"
        maxlength="80"
        placeholder="Nom du tag"
        required
        aria-label="Nom du nouveau tag"
      />

      <input
        v-model="newColor"
        class="tag-color-input"
        type="color"
        aria-label="Couleur du nouveau tag"
      />

      <button
        type="submit"
        class="primary tag-create-button"
        :disabled="
          creating
          || !newName.trim()
        "
      >
        <AppIcon
          name="plus"
          :size="17"
        />

        {{
          creating
            ? 'Création…'
            : 'Créer'
        }}
      </button>
    </form>



    <div
      v-if="
        loading
        && tags.length === 0
      "
      class="empty-state"
    >
      Chargement des tags…
    </div>

    <div
      v-else-if="
        tags.length === 0
      "
      class="empty-state"
    >
      <strong>
        Aucun tag pour le moment.
      </strong>

      <p>
        Créez votre premier tag ci-dessus.
      </p>
    </div>

    <div
      v-else
      class="tag-management-list"
    >
      <article
        v-for="tag in tags"
        :key="tag.id"
        class="tag-management-row"
      >
        <template
          v-if="
            editingId === tag.id
          "
        >
          <input
            v-model="editColor"
            class="tag-color-input"
            type="color"
            aria-label="Couleur du tag"
          />

          <input
            v-model.trim="editName"
            class="tag-name-input"
            maxlength="80"
            aria-label="Nom du tag"
            @keydown.enter.prevent="
              saveTag(tag)
            "
            @keydown.esc="
              cancelEdit
            "
          />

          <div class="tag-counts">
            <span>
              {{ tag.noteCount ?? 0 }}
              notes
            </span>

            <span>
              {{ tag.taskCount ?? 0 }}
              tâches
            </span>
          </div>

          <div class="tag-row-actions">
            <button
              type="button"
              class="secondary"
              :disabled="
                busyId === tag.id
                || !editName.trim()
              "
              @click="
                saveTag(tag)
              "
            >
              <AppIcon
                name="check"
                :size="16"
              />

              Enregistrer
            </button>

            <button
              type="button"
              class="secondary"
              :disabled="
                busyId === tag.id
              "
              @click="
                cancelEdit
              "
            >
              Annuler
            </button>
          </div>
        </template>

        <template v-else>
          <span
            class="tag-color-dot"
            :style="{
              background:
                tag.color,
            }"
          />

          <div class="tag-main">
            <strong>
              {{ tag.name }}
            </strong>

            <span class="muted tag-hex">
              {{ tag.color }}
            </span>
          </div>

          <div class="tag-counts">
            <span>
              {{ tag.noteCount ?? 0 }}
              notes
            </span>

            <span>
              {{ tag.taskCount ?? 0 }}
              tâches
            </span>
          </div>

          <div class="tag-row-actions">
            <button
              type="button"
              class="tag-icon-action"
              title="Modifier"
              :aria-label="
                `Modifier le tag ${tag.name}`
              "
              :disabled="
                busyId !== null
              "
              @click="
                beginEdit(tag)
              "
            >
              <span aria-hidden="true">
                ✏️
              </span>
            </button>

            <button
              type="button"
              class="
                tag-icon-action
                tag-icon-action--danger
              "
              title="Supprimer"
              :aria-label="
                `Supprimer le tag ${tag.name}`
              "
              :disabled="
                busyId !== null
              "
              @click="
                deleteTag(tag)
              "
            >
              <span aria-hidden="true">
                🗑️
              </span>
            </button>
          </div>
        </template>
      </article>
    </div>
  </section>
</template>

<style scoped>
.tags-page {
  max-width: 980px;
}

.tag-create-card {
  display: grid;
  grid-template-columns:
    minmax(180px, 1fr)
    minmax(180px, 320px)
    48px
    auto;
  align-items: center;
  gap: 12px;
  margin-bottom: 22px;
  padding: 18px;
  border: 1px solid var(--g-border);
  border-radius: 16px;
  background: #fff;
}

.tag-create-copy,
.tag-main,
.tag-counts {
  min-width: 0;
  display: grid;
  gap: 3px;
}

.tag-create-copy .muted {
  font-size: .86rem;
}

.tag-create-card > input,
.tag-name-input {
  min-width: 0;
  width: 100%;
}

.tag-color-input {
  width: 44px;
  height: 40px;
  padding: 3px;
  border-radius: 10px;
  cursor: pointer;
}

.tag-create-button {
  min-height: 40px;
  display: inline-flex;
  align-items: center;
  gap: 7px;
}

.tag-management-list {
  display: grid;
  gap: 10px;
}

.tag-management-row {
  min-height: 72px;
  display: grid;
  grid-template-columns:
    18px
    minmax(180px, 1fr)
    minmax(150px, auto)
    auto;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border: 1px solid var(--g-border);
  border-radius: 14px;
  background: #fff;
}

.tag-management-row:has(
  .tag-name-input
) {
  grid-template-columns:
    48px
    minmax(180px, 1fr)
    minmax(150px, auto)
    auto;
}

.tag-color-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
}

.tag-hex {
  font-size: .78rem;
  text-transform: uppercase;
}

.tag-counts {
  grid-auto-flow: column;
  gap: 12px;
  color: var(--g-muted);
  font-size: .84rem;
  white-space: nowrap;
}

.tag-row-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.tag-row-actions button {
  min-height: 36px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.tag-icon-action {
  width: 38px;
  height: 38px;
  min-height: 38px;
  padding: 0;
  display: grid;
  place-items: center;
  border: 1px solid var(--g-border);
  border-radius: 10px;
  background: transparent;
  font-size: 1rem;
  cursor: pointer;
}

.tag-icon-action:hover:not(:disabled) {
  background: var(--g-surface-alt);
}

.tag-icon-action--danger:hover:not(:disabled) {
  background: var(--g-red-soft);
}

.tag-delete-button {
  min-height: 36px;
  padding: 0 12px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: var(--g-red-strong);
  cursor: pointer;
}

.tag-delete-button:hover {
  background: var(--g-red-soft);
}

.tag-delete-button:disabled {
  cursor: default;
  opacity: .45;
}

@media (max-width: 820px) {
  .tag-create-card {
    grid-template-columns:
      minmax(0, 1fr)
      48px
      auto;
  }

  .tag-create-copy {
    grid-column: 1 / -1;
  }

  .tag-management-row,
  .tag-management-row:has(
    .tag-name-input
  ) {
    grid-template-columns:
      44px
      minmax(0, 1fr)
      auto;
  }

  .tag-counts {
    grid-column: 2 / -1;
    justify-content: start;
  }

  .tag-row-actions {
    grid-column: 2 / -1;
    justify-content: start;
  }
}

@media (max-width: 520px) {
  .tag-create-card {
    grid-template-columns:
      minmax(0, 1fr)
      48px;
  }

  .tag-create-button {
    grid-column: 1 / -1;
    justify-content: center;
  }
}
</style>
