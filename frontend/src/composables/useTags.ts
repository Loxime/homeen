import {
  ref,
} from 'vue'

import {
  api,
} from '../services/api'

import type {
  Tag,
} from '../types/domain'

const tags =
  ref<Tag[]>([])

const loading =
  ref(false)

const loaded =
  ref(false)

const error =
  ref('')

let loadingPromise:
  Promise<void>
  | null = null

function errorMessage(
  exception: unknown,
  fallback: string,
): string {
  return exception instanceof Error
    ? exception.message
    : fallback
}

async function load(
  force = false,
): Promise<void> {
  if (
    loaded.value
    && !force
  ) {
    return
  }

  if (loadingPromise) {
    await loadingPromise
    return
  }

  loadingPromise = (
    async () => {
      loading.value = true
      error.value = ''

      try {
        const response =
          await api<{
            tags: Tag[]
          }>(
            '/api/tags',
          )

        tags.value =
          response.tags

        loaded.value = true
      } catch (exception) {
        error.value =
          errorMessage(
            exception,
            'Impossible de charger les tags.',
          )

        throw exception
      } finally {
        loading.value = false
        loadingPromise = null
      }
    }
  )()

  await loadingPromise
}

async function create(
  name: string,
  color: string,
): Promise<Tag> {
  error.value = ''

  try {
    const created =
      await api<Tag>(
        '/api/tags',
        {
          method: 'POST',

          body: JSON.stringify({
            name,
            color,
          }),
        },
      )

    await load(true)

    return created
  } catch (exception) {
    error.value =
      errorMessage(
        exception,
        'Impossible de créer le tag.',
      )

    throw exception
  }
}

async function update(
  id: number,
  name: string,
  color: string,
): Promise<Tag> {
  error.value = ''

  try {
    const updated =
      await api<Tag>(
        `/api/tags/${id}`,
        {
          method: 'PUT',

          body: JSON.stringify({
            name,
            color,
          }),
        },
      )

    await load(true)

    return updated
  } catch (exception) {
    error.value =
      errorMessage(
        exception,
        'Impossible de modifier le tag.',
      )

    throw exception
  }
}

async function remove(
  id: number,
): Promise<void> {
  error.value = ''

  try {
    await api(
      `/api/tags/${id}`,
      {
        method: 'DELETE',
      },
    )

    await load(true)
  } catch (exception) {
    error.value =
      errorMessage(
        exception,
        'Impossible de supprimer le tag.',
      )

    throw exception
  }
}

export function useTags() {
  return {
    tags,
    loading,
    loaded,
    error,
    load,
    create,
    update,
    remove,
  }
}
