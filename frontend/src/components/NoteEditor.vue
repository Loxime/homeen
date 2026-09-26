<script setup lang="ts">
import {
  computed,
  ref,
  watch,
} from 'vue'

import AppIcon from './AppIcon.vue'
import { api } from '../services/api'

import type {
  ImageAsset,
  Note,
  NoteCollection,
  Tag,
  Task,
  TaskPriority,
  TaskStatus,
} from '../types/domain'

const props = withDefaults(
  defineProps<{
    note: Note | null
    tags: Tag[]
    collections: NoteCollection[]
    defaultCollectionId?: number | null
  }>(),
  {
    defaultCollectionId: null,
  },
)

const emit = defineEmits<{
  saved: [note: Note]
  closed: []
  changed: []
}>()

const title = ref('')
const content = ref('')

const isPinned =
  ref(false)

const noteColor =
  ref('#FFFFFF')

const noteColors = [
  {
    label: 'Blanc',
    value: '#FFFFFF',
  },
  {
    label: 'Rouge doux',
    value: '#FADBD8',
  },
  {
    label: 'Orange doux',
    value: '#FDEBD0',
  },
  {
    label: 'Jaune doux',
    value: '#FFF3BF',
  },
  {
    label: 'Vert doux',
    value: '#D3F9D8',
  },
  {
    label: 'Turquoise doux',
    value: '#C5F6FA',
  },
  {
    label: 'Bleu doux',
    value: '#D6E4FF',
  },
  {
    label: 'Violet doux',
    value: '#E5DBFF',
  },
] as const

const selectedNoteTagIds =
  ref<number[]>([])

const collectionId =
  ref<number | null>(null)
const taskText = ref('')
const saving = ref(false)
const error = ref('')
const localNote = ref<Note | null>(null)

const noteImages =
  ref<ImageAsset[]>([])

const libraryImages =
  ref<ImageAsset[]>([])

const imageUploading =
  ref(false)

const imageBusyId =
  ref<number | null>(
    null,
  )

const imageLibraryOpen =
  ref(false)

const imageLibraryLoading =
  ref(false)

const isNew = computed(
  () => localNote.value === null,
)

const isTrash = computed(
  () =>
    localNote.value?.deletedAt !== null
    && localNote.value?.deletedAt !== undefined,
)

const isArchived = computed(
  () =>
    localNote.value?.archivedAt !== null
    && localNote.value?.archivedAt !== undefined,
)

function cloneNote(
  note: Note,
): Note {
  return {
    ...note,

    tags: note.tags.map(
      tag => ({
        ...tag,
      }),
    ),

    tasks: note.tasks.map(
      task => ({
        ...task,

        tags: task.tags.map(
          tag => ({
            ...tag,
          }),
        ),
      }),
    ),
  }
}

watch(
  () => [
    props.note,
    props.defaultCollectionId,
  ] as const,
  ([value]) => {
    localNote.value =
      value
        ? cloneNote(value)
        : null

    title.value =
      value?.title ?? ''

    content.value =
      value?.content ?? ''

    isPinned.value =
      value?.isPinned ?? false

    noteColor.value =
      value?.color ?? '#FFFFFF'

    selectedNoteTagIds.value =
      value?.tags.map(
        tag => tag.id,
      ) ?? []

    collectionId.value =
      value?.collectionId
      ?? props.defaultCollectionId
      ?? null

    taskText.value = ''
    error.value = ''

    noteImages.value = []
    libraryImages.value = []
    imageLibraryOpen.value = false

    if (value) {
      void loadNoteImages(
        value.id
      )
    }
  },
  {
    immediate: true,
  },
)

async function save(
  notifyParent = true,
):
Promise<Note | null> {
  saving.value = true
  error.value = ''

  try {
    const payload =
      JSON.stringify({
        title: title.value,
        content: content.value,
        isPinned:
          isPinned.value,
        color:
          noteColor.value,
        tagIds:
          selectedNoteTagIds.value,
        collectionId:
          collectionId.value,
      })

    const note =
      isNew.value
        ? await api<Note>(
            '/api/notes',
            {
              method: 'POST',
              body: payload,
            },
          )
        : await api<Note>(
            `/api/notes/${localNote.value!.id}`,
            {
              method: 'PUT',
              body: payload,
            },
          )

    localNote.value = note

    if (notifyParent) {
      emit(
        'saved',
        note,
      )
    }

    return note
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’enregistrer la note.'

    return null
  } finally {
    saving.value = false
  }
}

async function done(): Promise<void> {
  const note =
    await save()

  if (note) {
    emit('closed')
  }
}

async function loadNoteImages(
  noteId: number,
): Promise<void> {
  try {
    const response =
      await api<{
        images: ImageAsset[]
      }>(
        `/api/images/note/${noteId}`,
      )

    noteImages.value =
      response.images
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les images.'
  }
}

async function loadImageLibrary():
Promise<void> {
  imageLibraryLoading.value = true

  try {
    const response =
      await api<{
        images: ImageAsset[]
      }>(
        '/api/images',
      )

    libraryImages.value =
      response.images
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger la bibliothèque.'
  } finally {
    imageLibraryLoading.value = false
  }
}

async function toggleImageLibrary():
Promise<void> {
  imageLibraryOpen.value =
    !imageLibraryOpen.value

  if (
    imageLibraryOpen.value
    && libraryImages.value.length === 0
  ) {
    await loadImageLibrary()
  }
}

async function ensureSavedNote():
Promise<Note | null> {
  if (localNote.value) {
    return localNote.value
  }

  return await save(false)
}

async function attachImage(
  image: ImageAsset,
): Promise<void> {
  if (
    imageBusyId.value !== null
  ) {
    return
  }

  const note =
    await ensureSavedNote()

  if (!note) {
    return
  }

  imageBusyId.value =
    image.id

  try {
    await api(
      `/api/images/${image.id}/notes/${note.id}`,
      {
        method: 'POST',
      },
    )

    await loadNoteImages(
      note.id
    )

    emit('changed')
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’ajouter l’image à la note.'
  } finally {
    imageBusyId.value = null
  }
}

async function detachImage(
  image: ImageAsset,
): Promise<void> {
  if (
    !localNote.value
    || imageBusyId.value !== null
  ) {
    return
  }

  imageBusyId.value =
    image.id

  try {
    await api(
      `/api/images/${image.id}/notes/${localNote.value.id}`,
      {
        method: 'DELETE',
      },
    )

    noteImages.value =
      noteImages.value.filter(
        candidate =>
          candidate.id
          !== image.id,
      )

    emit('changed')
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de retirer l’image de la note.'
  } finally {
    imageBusyId.value = null
  }
}

async function uploadImages(
  event: Event,
): Promise<void> {
  const input =
    event.target as HTMLInputElement

  const files =
    Array.from(
      input.files ?? [],
    )

  input.value = ''

  if (
    files.length === 0
    || imageUploading.value
  ) {
    return
  }

  const note =
    await ensureSavedNote()

  if (!note) {
    return
  }

  imageUploading.value = true
  error.value = ''

  try {
    for (
      const file
      of files
    ) {
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

      await api(
        `/api/images/${image.id}/notes/${note.id}`,
        {
          method: 'POST',
        },
      )
    }

    await Promise.all([
      loadNoteImages(
        note.id
      ),
      loadImageLibrary(),
    ])

    emit('changed')
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’ajouter l’image.'
  } finally {
    imageUploading.value = false
  }
}

function isImageAttached(
  imageId: number,
): boolean {
  return noteImages.value.some(
    image =>
      image.id === imageId,
  )
}

function isNoteTagged(
  tagId: number,
): boolean {
  return selectedNoteTagIds.value
    .includes(tagId)
}

function toggleNoteTag(
  tagId: number,
  event: Event,
): void {
  const input =
    event.target as HTMLInputElement

  selectedNoteTagIds.value =
    input.checked
      ? Array.from(
          new Set([
            ...selectedNoteTagIds.value,
            tagId,
          ]),
        )
      : selectedNoteTagIds.value.filter(
          id => id !== tagId,
        )
}

async function addTask(): Promise<void> {
  if (
    !localNote.value
    || !taskText.value.trim()
  ) {
    return
  }

  const task =
    await api<Task>(
      `/api/notes/${localNote.value.id}/tasks`,
      {
        method: 'POST',
        body: JSON.stringify({
          content:
            taskText.value.trim(),
        }),
      },
    )

  localNote.value.tasks.push(task)
  taskText.value = ''

  emit('changed')
}

async function updateTask(
  task: Task,
  payload: {
    priority?: TaskPriority
    status?: TaskStatus
    tagIds?: number[]
  },
): Promise<void> {
  error.value = ''

  try {
    const updated =
      await api<Task>(
        `/api/tasks/${task.id}`,
        {
          method: 'PUT',
          body: JSON.stringify(
            payload,
          ),
        },
      )

    Object.assign(
      task,
      updated,
    )

    emit('changed')
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier la tâche.'
  }
}

async function setTaskPriority(
  task: Task,
  event: Event,
): Promise<void> {
  const select =
    event.target as HTMLSelectElement

  await updateTask(
    task,
    {
      priority:
        select.value as TaskPriority,
    },
  )
}

async function setTaskStatus(
  task: Task,
  event: Event,
): Promise<void> {
  const select =
    event.target as HTMLSelectElement

  await updateTask(
    task,
    {
      status:
        select.value as TaskStatus,
    },
  )
}

function isTaskTagged(
  task: Task,
  tagId: number,
): boolean {
  return task.tags.some(
    tag =>
      tag.id === tagId,
  )
}

async function toggleTaskTag(
  task: Task,
  tagId: number,
  event: Event,
): Promise<void> {
  const input =
    event.target as HTMLInputElement

  const currentIds =
    task.tags.map(
      tag => tag.id,
    )

  const tagIds =
    input.checked
      ? Array.from(
          new Set([
            ...currentIds,
            tagId,
          ]),
        )
      : currentIds.filter(
          id =>
            id !== tagId,
        )

  await updateTask(
    task,
    {
      tagIds,
    },
  )
}

async function toggleTask(
  task: Task,
): Promise<void> {
  const updated =
    await api<Task>(
      `/api/tasks/${task.id}/completed`,
      {
        method: 'PUT',
        body: JSON.stringify({
          completed:
            !task.isCompleted,
        }),
      },
    )

  Object.assign(
    task,
    updated,
  )

  emit('changed')
}

async function deleteTask(
  task: Task,
): Promise<void> {
  if (!localNote.value) {
    return
  }

  await api(
    `/api/tasks/${task.id}`,
    {
      method: 'DELETE',
    },
  )

  localNote.value.tasks =
    localNote.value.tasks.filter(
      item =>
        item.id !== task.id,
    )

  emit('changed')
}

async function duplicate(): Promise<void> {
  if (!localNote.value) {
    return
  }

  const duplicated =
    await api<Note>(
      `/api/notes/${localNote.value.id}/duplicate`,
      {
        method: 'POST',
      },
    )

  emit(
    'saved',
    duplicated,
  )

  emit('closed')
}

async function setArchive(
  archive: boolean,
): Promise<void> {
  if (!localNote.value) {
    return
  }

  await api<Note>(
    `/api/notes/${localNote.value.id}/${
      archive
        ? 'archive'
        : 'unarchive'
    }`,
    {
      method: 'POST',
    },
  )

  emit('changed')
  emit('closed')
}

async function trash(): Promise<void> {
  if (!localNote.value) {
    return
  }

  await api(
    `/api/notes/${localNote.value.id}`,
    {
      method: 'DELETE',
    },
  )

  emit('changed')
  emit('closed')
}

async function restore(): Promise<void> {
  if (!localNote.value) {
    return
  }

  await api<Note>(
    `/api/notes/${localNote.value.id}/restore`,
    {
      method: 'POST',
    },
  )

  emit('changed')
  emit('closed')
}
</script>

<template>
  <div
    class="note-editor keep-note-editor"
    :style="{
      '--note-color':
        noteColor,
    }"
  >
    <template v-if="isTrash">
      <div class="trash-message">
        <strong>
          Cette note est dans la corbeille.
        </strong>

        <p>
          Elle sera supprimée définitivement
          30 jours après sa mise à la corbeille.
        </p>
      </div>

      <div class="keep-editor-footer">
        <button
          class="secondary"
          @click="restore"
        >
          Restaurer la note
        </button>

        <button
          class="keep-done"
          @click="emit('closed')"
        >
          Fermer
        </button>
      </div>
    </template>

    <template v-else>
      <div class="keep-note-main">
        <div class="keep-note-appearance">
          <button
            type="button"
            class="keep-pin-button"
            :class="{
              active:
                isPinned,
            }"
            :title="
              isPinned
                ? 'Désépingler'
                : 'Épingler'
            "
            :aria-label="
              isPinned
                ? 'Désépingler la note'
                : 'Épingler la note'
            "
            @click="
              isPinned = !isPinned
            "
          >
            <AppIcon
              name="pin"
              :size="18"
            />

            <span>
              {{
                isPinned
                  ? 'Épinglée'
                  : 'Épingler'
              }}
            </span>
          </button>

          <div
            class="keep-color-palette"
            aria-label="Couleur de la note"
          >
            <button
              v-for="choice in noteColors"
              :key="choice.value"
              type="button"
              class="keep-color-swatch"
              :class="{
                active:
                  noteColor
                  === choice.value,
              }"
              :style="{
                background:
                  choice.value,
              }"
              :title="choice.label"
              :aria-label="
                `Couleur ${choice.label}`
              "
              @click="
                noteColor =
                  choice.value
              "
            >
              <AppIcon
                v-if="
                  noteColor
                  === choice.value
                "
                name="check"
                :size="14"
              />
            </button>
          </div>

          <label
            class="keep-custom-color"
            title="Couleur personnalisée"
          >
            <span>
              Personnalisée
            </span>

            <input
              v-model="noteColor"
              type="color"
              aria-label="Couleur personnalisée de la note"
            />
          </label>
        </div>

        <input
          v-model="title"
          class="keep-title-input"
          maxlength="255"
          placeholder="Titre"
        />

        <textarea
          v-model="content"
          class="keep-content-input"
          placeholder="Écrivez votre note…"
        />

        <section class="note-images-section">
          <div class="note-images-heading">
            <div>
              <span class="keep-section-label">
                Images
              </span>

              <small>
                {{
                  noteImages.length === 0
                    ? 'Aucune image'
                    : `${noteImages.length} image${noteImages.length > 1 ? 's' : ''}`
                }}
              </small>
            </div>

            <div class="note-image-actions">
              <label
                class="note-image-action"
                :class="{
                  disabled:
                    imageUploading,
                }"
              >
                <AppIcon
                  name="plus"
                  :size="16"
                />

                {{
                  imageUploading
                    ? 'Ajout…'
                    : 'Importer'
                }}

                <input
                  class="image-file-input"
                  type="file"
                  multiple
                  accept="image/jpeg,image/png,image/webp,image/gif"
                  :disabled="
                    imageUploading
                  "
                  @change="
                    uploadImages
                  "
                />
              </label>

              <button
                type="button"
                class="note-image-action"
                @click="
                  toggleImageLibrary
                "
              >
                <AppIcon
                  name="image"
                  :size="16"
                />

                Bibliothèque
              </button>
            </div>
          </div>

          <div
            v-if="
              noteImages.length > 0
            "
            class="note-image-grid"
          >
            <figure
              v-for="
                image in noteImages
              "
              :key="image.id"
              class="note-image-card"
            >
              <img
                :src="
                  image.contentUrl
                "
                :alt="
                  image.originalName
                "
                loading="lazy"
              />

              <figcaption>
                <span
                  :title="
                    image.originalName
                  "
                >
                  {{
                    image.originalName
                  }}
                </span>

                <button
                  type="button"
                  :disabled="
                    imageBusyId
                    === image.id
                  "
                  title="Retirer de la note"
                  aria-label="Retirer de la note"
                  @click="
                    detachImage(
                      image,
                    )
                  "
                >
                  <AppIcon
                    name="close"
                    :size="15"
                  />
                </button>
              </figcaption>
            </figure>
          </div>

          <div
            v-if="
              imageLibraryOpen
            "
            class="note-library-picker"
          >
            <p
              v-if="
                imageLibraryLoading
              "
              class="muted"
            >
              Chargement de la bibliothèque…
            </p>

            <p
              v-else-if="
                libraryImages.length === 0
              "
              class="muted"
            >
              La bibliothèque est vide.
            </p>

            <div
              v-else
              class="note-library-grid"
            >
              <button
                v-for="
                  image in libraryImages
                "
                :key="image.id"
                type="button"
                class="note-library-image"
                :class="{
                  attached:
                    isImageAttached(
                      image.id,
                    ),
                }"
                :disabled="
                  isImageAttached(
                    image.id,
                  )
                  || imageBusyId !== null
                "
                :title="
                  isImageAttached(image.id)
                    ? 'Déjà ajoutée'
                    : `Ajouter ${image.originalName}`
                "
                @click="
                  attachImage(
                    image,
                  )
                "
              >
                <img
                  :src="
                    image.contentUrl
                  "
                  :alt="
                    image.originalName
                  "
                  loading="lazy"
                />

                <span>
                  {{
                    isImageAttached(
                      image.id,
                    )
                      ? 'Ajoutée'
                      : 'Ajouter'
                  }}
                </span>
              </button>
            </div>
          </div>
        </section>

        <div class="note-organization-grid">
          <label>
            <span class="keep-label-caption">
              Collection
            </span>

            <select
              v-model="collectionId"
              class="keep-label-select"
            >
              <option :value="null">
                Aucune collection
              </option>

              <option
                v-for="collection in collections"
                :key="collection.id"
                :value="collection.id"
              >
                {{ collection.name }}
              </option>
            </select>
          </label>

          <div class="note-tag-field">
            <span class="keep-label-caption">
              Tags
            </span>

            <div
              v-if="tags.length > 0"
              class="note-tag-picker"
            >
              <label
                v-for="tag in tags"
                :key="tag.id"
                class="task-tag-option"
                :class="{
                  active:
                    isNoteTagged(tag.id),
                }"
              >
                <input
                  type="checkbox"
                  :checked="
                    isNoteTagged(
                      tag.id,
                    )
                  "
                  @change="
                    toggleNoteTag(
                      tag.id,
                      $event,
                    )
                  "
                />

                <span
                  class="task-tag-dot"
                  :style="{
                    background:
                      tag.color,
                  }"
                />

                <span>
                  {{ tag.name }}
                </span>
              </label>
            </div>

            <span
              v-else
              class="muted"
            >
              Aucun tag disponible.
            </span>
          </div>
        </div>

        <p
          v-if="error"
          class="form-error"
        >
          {{ error }}
        </p>

        <section
          v-if="localNote"
          class="tasks-panel keep-tasks-panel"
        >
          <div class="section-heading keep-task-heading">
            <div>
              <span class="keep-section-label">
                Tâches
              </span>

              <small>
                {{
                  localNote.tasks.filter(
                    task =>
                      task.isCompleted,
                  ).length
                }}
                /
                {{ localNote.tasks.length }}
                terminées
              </small>
            </div>
          </div>

          <form
            class="task-entry keep-task-entry"
            @submit.prevent="addTask"
          >
            <AppIcon
              name="plus"
              :size="19"
            />

            <input
              v-model="taskText"
              maxlength="4000"
              placeholder="Ajouter une tâche"
            />

            <span class="char-count">
              {{ taskText.length }}/4000
            </span>
          </form>

          <div class="task-list">
            <div
              v-for="task in localNote.tasks"
              :key="task.id"
              class="task-row keep-task-row"
              :class="{
                complete:
                  task.isCompleted,
              }"
            >
              <input
                type="checkbox"
                :checked="task.isCompleted"
                @change="toggleTask(task)"
              />

              <div class="task-main">
                <RouterLink
                  class="task-content task-content-link"
                  :to="`/tasks/${task.id}`"
                  title="Ouvrir la tâche en plein écran"
                >
                  {{ task.content }}
                </RouterLink>

                <div class="task-meta">
                  <label class="task-field">
                    <span>
                      Priorité
                    </span>

                    <select
                      :value="task.priority"
                      @change="
                        setTaskPriority(
                          task,
                          $event,
                        )
                      "
                    >
                      <option value="low">
                        Basse
                      </option>

                      <option value="normal">
                        Normale
                      </option>

                      <option value="high">
                        Haute
                      </option>

                      <option value="urgent">
                        Urgente
                      </option>
                    </select>
                  </label>

                  <label class="task-field">
                    <span>
                      État
                    </span>

                    <select
                      :value="task.status"
                      @change="
                        setTaskStatus(
                          task,
                          $event,
                        )
                      "
                    >
                      <option value="todo">
                        À faire
                      </option>

                      <option value="in_progress">
                        En cours
                      </option>

                      <option value="done">
                        Terminée
                      </option>
                    </select>
                  </label>

                  <div
                    v-if="tags.length > 0"
                    class="task-tags"
                  >
                    <span class="task-tags-label">
                      Tags
                    </span>

                    <label
                      v-for="tag in tags"
                      :key="tag.id"
                      class="task-tag-option"
                      :class="{
                        active:
                          isTaskTagged(
                            task,
                            tag.id,
                          ),
                      }"
                    >
                      <input
                        type="checkbox"
                        :checked="
                          isTaskTagged(
                            task,
                            tag.id,
                          )
                        "
                        @change="
                          toggleTaskTag(
                            task,
                            tag.id,
                            $event,
                          )
                        "
                      />

                      <span
                        class="task-tag-dot"
                        :style="{
                          background:
                            tag.color,
                        }"
                      />

                      <span>
                        {{ tag.name }}
                      </span>
                    </label>
                  </div>
                </div>
              </div>

              <button
                class="icon-button small"
                title="Supprimer la tâche"
                aria-label="Supprimer la tâche"
                @click="deleteTask(task)"
              >
                <AppIcon
                  name="close"
                  :size="16"
                />
              </button>
            </div>
          </div>
        </section>

        <p
          v-else
          class="keep-task-hint"
        >
          Enregistrez d’abord la note
          pour pouvoir ajouter des tâches.
        </p>
      </div>

      <footer class="keep-editor-footer">
        <div class="keep-editor-tools">
          <button
            v-if="!isNew"
            class="keep-tool-button"
            title="Dupliquer"
            @click="duplicate"
          >
            <AppIcon
              name="copy"
              :size="19"
            />
          </button>

          <button
            v-if="!isNew"
            class="keep-tool-button"
            :title="
              isArchived
                ? 'Désarchiver'
                : 'Archiver'
            "
            @click="
              setArchive(
                !isArchived
              )
            "
          >
            <AppIcon
              name="archive"
              :size="19"
            />
          </button>

          <button
            v-if="!isNew"
            class="keep-tool-button danger"
            title="Mettre à la corbeille"
            @click="trash"
          >
            <AppIcon
              name="trash"
              :size="19"
            />
          </button>
        </div>

        <button
          class="keep-done"
          :disabled="saving"
          @click="done"
        >
          {{
            saving
              ? 'Enregistrement…'
              : 'Terminer'
          }}
        </button>
      </footer>
    </template>
  </div>
</template>
