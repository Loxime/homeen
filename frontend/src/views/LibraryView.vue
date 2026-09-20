<script setup lang="ts">
import {
  onMounted,
  ref,
} from 'vue'

import AppIcon from '../components/AppIcon.vue'

import {
  api,
} from '../services/api'

import {
  useToast,
} from '../composables/useToast'

import type {
  ImageAsset,
} from '../types/domain'

const {
  success:
    showSuccess,
  error:
    showError,
} = useToast()

const images =
  ref<ImageAsset[]>([])

const loading =
  ref(true)

const uploading =
  ref(false)

const deletingId =
  ref<number | null>(
    null,
  )

const dragActive =
  ref(false)

const error =
  ref('')

async function load():
Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const response =
      await api<{
        images: ImageAsset[]
      }>(
        '/api/images',
      )

    images.value =
      response.images
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger la bibliothèque.'
  } finally {
    loading.value = false
  }
}

async function uploadFile(
  file: File,
): Promise<void> {
  const form =
    new FormData()

  form.append(
    'image',
    file,
  )

  await api<ImageAsset>(
    '/api/images',
    {
      method: 'POST',
      body: form,
    },
  )
}

async function uploadFiles(
  files: File[],
): Promise<void> {
  if (
    uploading.value
    || files.length === 0
  ) {
    return
  }

  uploading.value = true
  error.value = ''

  let uploaded = 0

  try {
    for (
      const file
      of files
    ) {
      await uploadFile(
        file
      )

      uploaded++
    }

    await load()

    showSuccess(
      uploaded > 1
        ? `${uploaded} images ajoutées à la bibliothèque.`
        : 'Image ajoutée à la bibliothèque.',
    )
  } catch (exception) {
    const message =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’ajouter l’image.'

    error.value =
      message

    showError(
      message
    )

    if (uploaded > 0) {
      await load()
    }
  } finally {
    uploading.value = false
  }
}

function selectFiles(
  event: Event,
): void {
  const input =
    event.target as HTMLInputElement

  const files =
    Array.from(
      input.files ?? [],
    )

  input.value = ''

  void uploadFiles(
    files
  )
}

function drop(
  event: DragEvent,
): void {
  dragActive.value = false

  const files =
    Array.from(
      event.dataTransfer?.files
      ?? [],
    )

  void uploadFiles(
    files
  )
}

async function remove(
  image: ImageAsset,
): Promise<void> {
  if (
    deletingId.value !== null
  ) {
    return
  }

  const linkedText =
    image.noteCount > 0
      ? `\n\nElle est utilisée dans ${image.noteCount} note${image.noteCount > 1 ? 's' : ''}.`
      : ''

  if (
    !window.confirm(
      `Supprimer définitivement « ${image.originalName} » de la bibliothèque ?${linkedText}`,
    )
  ) {
    return
  }

  deletingId.value =
    image.id

  try {
    await api(
      `/api/images/${image.id}`,
      {
        method: 'DELETE',
      },
    )

    images.value =
      images.value.filter(
        candidate =>
          candidate.id
          !== image.id,
      )

    showSuccess(
      'Image supprimée de la bibliothèque.',
    )
  } catch (exception) {
    const message =
      exception instanceof Error
        ? exception.message
        : 'Impossible de supprimer l’image.'

    error.value =
      message

    showError(
      message
    )
  } finally {
    deletingId.value = null
  }
}

function formatSize(
  bytes: number,
): string {
  if (
    bytes < 1024 * 1024
  ) {
    return `${
      Math.max(
        1,
        Math.round(
          bytes / 1024,
        ),
      )
    } Ko`
  }

  return `${
    (
      bytes
      / 1024
      / 1024
    ).toFixed(1)
  } Mo`
}

onMounted(
  () => {
    void load()
  },
)
</script>

<template>
  <section class="page image-library-page">
    <header class="page-header image-library-header">
      <div>
        <h1>
          Bibliothèque
        </h1>

        <p class="muted">
          Retrouvez toutes vos images,
          y compris celles utilisées
          dans vos notes.
        </p>
      </div>

      <label
        class="ui-button ui-button--primary image-upload-button"
        :class="{
          disabled:
            uploading,
        }"
      >
        <AppIcon
          name="plus"
          :size="18"
        />

        {{
          uploading
            ? 'Import…'
            : 'Ajouter des images'
        }}

        <input
          class="image-file-input"
          type="file"
          multiple
          accept="
            image/jpeg,
            image/png,
            image/webp,
            image/gif
          "
          :disabled="uploading"
          @change="selectFiles"
        />
      </label>
    </header>

    <div
      class="image-drop-zone"
      :class="{
        active:
          dragActive,
        busy:
          uploading,
      }"
      @dragenter.prevent="
        dragActive = true
      "
      @dragover.prevent="
        dragActive = true
      "
      @dragleave.prevent="
        dragActive = false
      "
      @drop.prevent="
        drop
      "
    >
      <AppIcon
        name="image"
        :size="28"
      />

      <div>
        <strong>
          Glissez vos images ici
        </strong>

        <span>
          JPEG, PNG, WebP ou GIF · 8 Mo maximum
        </span>
      </div>
    </div>

    <p
      v-if="error"
      class="form-error"
    >
      {{ error }}
    </p>

    <div
      v-if="loading"
      class="image-library-loading"
    >
      Chargement de la bibliothèque…
    </div>

    <div
      v-else-if="
        images.length === 0
      "
      class="empty-state image-library-empty"
    >
      <AppIcon
        name="image"
        :size="34"
      />

      <strong>
        Votre bibliothèque est vide.
      </strong>

      <p>
        Ajoutez une première image,
        ou insérez-en une dans une note.
      </p>
    </div>

    <div
      v-else
      class="image-library-grid"
    >
      <article
        v-for="image in images"
        :key="image.id"
        class="image-library-card"
      >
        <a
          class="image-library-preview"
          :href="image.contentUrl"
          target="_blank"
          rel="noopener"
        >
          <img
            :src="image.contentUrl"
            :alt="image.originalName"
            loading="lazy"
          />
        </a>

        <div class="image-library-meta">
          <strong
            :title="
              image.originalName
            "
          >
            {{ image.originalName }}
          </strong>

          <span>
            {{ formatSize(image.sizeBytes) }}

            <template
              v-if="
                image.width
                && image.height
              "
            >
              ·
              {{ image.width }}
              ×
              {{ image.height }}
            </template>
          </span>

          <span
            v-if="
              image.noteCount > 0
            "
            class="image-note-count"
          >
            {{
              image.noteCount
            }}
            note{{
              image.noteCount > 1
                ? 's'
                : ''
            }}
          </span>
        </div>

        <button
          type="button"
          class="image-library-delete"
          :disabled="
            deletingId
            === image.id
          "
          title="Supprimer définitivement"
          aria-label="Supprimer définitivement"
          @click="
            remove(image)
          "
        >
          <AppIcon
            name="trash"
            :size="17"
          />
        </button>
      </article>
    </div>
  </section>
</template>
