import {
  ref,
} from 'vue'

import {
  api,
} from '../services/api'

import type {
  Label,
} from '../types/domain'

const labels =
  ref<Label[]>([])

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
            labels: Label[]
          }>(
            '/api/labels',
          )

        labels.value =
          response.labels

        loaded.value = true
      } catch (exception) {
        error.value =
          errorMessage(
            exception,
            'Impossible de charger les libellés.',
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
): Promise<Label> {
  error.value = ''

  try {
    const created =
      await api<Label>(
        '/api/labels',
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
        'Impossible de créer le libellé.',
      )

    throw exception
  }
}

async function update(
  id: number,
  name: string,
  color: string,
): Promise<Label> {
  error.value = ''

  try {
    const updated =
      await api<Label>(
        `/api/labels/${id}`,
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
        'Impossible de modifier le libellé.',
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
      `/api/labels/${id}`,
      {
        method: 'DELETE',
      },
    )

    await load(true)
  } catch (exception) {
    error.value =
      errorMessage(
        exception,
        'Impossible de supprimer le libellé.',
      )

    throw exception
  }
}

export function useLabels() {
  return {
    labels,
    loading,
    loaded,
    error,
    load,
    create,
    update,
    remove,
  }
}
