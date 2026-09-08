<script setup lang="ts">
import {
  ref,
  watch,
} from 'vue'

import {
  api,
} from '../services/api'

import ChannelRolesPanel from './ChannelRolesPanel.vue'

interface Channel {
  id: number
  code: string
  formattedCode: string
  name: string
  description: string
  profileImageUrl: string | null
  creatorUserId: number
  memberCount: number
  isCreator: boolean
  createdAt: string
}

const props =
  defineProps<{
    channel: Channel
  }>()

const emit =
  defineEmits<{
    updated: [channel: Channel]
    closed: []
    left: []
  }>()

const name =
  ref(
    props.channel.name,
  )

const description =
  ref(
    props.channel.description,
  )

const profileImageUrl =
  ref(
    props.channel.profileImageUrl
    ?? '',
  )

const saving =
  ref(false)

const closing =
  ref(false)

const leaving =
  ref(false)

const exporting =
  ref(false)

const error =
  ref('')

const success =
  ref('')

function synchronize(): void {
  name.value =
    props.channel.name

  description.value =
    props.channel.description

  profileImageUrl.value =
    props.channel.profileImageUrl
    ?? ''
}

watch(
  () => props.channel,
  () => {
    synchronize()
  },
)

async function save(): Promise<void> {
  if (
    !props.channel.isCreator
    || saving.value
  ) {
    return
  }

  error.value = ''
  success.value = ''
  saving.value = true

  try {
    const updated =
      await api<Channel>(
        `/api/channels/${props.channel.code}/settings`,
        {
          method: 'PATCH',

          body: JSON.stringify({
            name:
              name.value.trim(),

            description:
              description.value.trim(),

            profileImageUrl:
              profileImageUrl.value.trim(),
          }),
        },
      )

    emit(
      'updated',
      updated,
    )

    success.value =
      'Les paramètres du canal ont été enregistrés.'
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’enregistrer les paramètres.'
  } finally {
    saving.value = false
  }
}

async function refreshAfterOwnershipTransfer(): Promise<void> {
  error.value = ''
  success.value = ''

  try {
    const updated =
      await api<Channel>(
        `/api/channels/${props.channel.code}`,
      )

    emit(
      'updated',
      updated,
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'La propriété a été transférée, mais le canal n’a pas pu être rechargé.'
  }
}

async function exportChannel(): Promise<void> {
  if (
    !props.channel.isCreator
    || exporting.value
  ) {
    return
  }

  exporting.value = true
  error.value = ''
  success.value = ''

  try {
    const exported =
      await api<Record<string, unknown>>(
        `/api/channels/${props.channel.code}/export`,
      )

    const blob =
      new Blob(
        [
          JSON.stringify(
            exported,
            null,
            2,
          ),
        ],
        {
          type:
            'application/json;charset=utf-8',
        },
      )

    const url =
      URL.createObjectURL(
        blob,
      )

    const link =
      document.createElement(
        'a',
      )

    link.href = url

    link.download =
      `homeen-channel-${props.channel.code}.json`

    document.body.appendChild(
      link,
    )

    link.click()
    link.remove()

    URL.revokeObjectURL(
      url,
    )

    success.value =
      'Export du canal généré.'
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’exporter le canal.'
  } finally {
    exporting.value = false
  }
}

async function leaveChannel(): Promise<void> {
  if (
    props.channel.isCreator
    || leaving.value
  ) {
    return
  }

  const confirmed =
    window.confirm(
      `Quitter le canal « ${props.channel.name} » ?\n\nVous perdrez immédiatement l’accès à ses notes, tâches et messages.`,
    )

  if (!confirmed) {
    return
  }

  leaving.value = true
  error.value = ''
  success.value = ''

  try {
    await api(
      `/api/channels/${props.channel.code}/leave`,
      {
        method: 'POST',
      },
    )

    emit(
      'left',
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de quitter le canal.'
  } finally {
    leaving.value = false
  }
}

async function closeChannel(): Promise<void> {
  if (
    !props.channel.isCreator
    || closing.value
  ) {
    return
  }

  const confirmed =
    window.confirm(
      `Fermer définitivement le canal « ${props.channel.name} » ?\n\nLe canal ne sera plus accessible à ses membres. Les données seront conservées.`,
    )

  if (!confirmed) {
    return
  }

  error.value = ''
  success.value = ''
  closing.value = true

  try {
    await api(
      `/api/channels/${props.channel.code}/close`,
      {
        method: 'POST',
      },
    )

    emit(
      'closed',
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de fermer le canal.'
  } finally {
    closing.value = false
  }
}
</script>

<template>
  <div class="channel-settings-stack">
    <section
      class="settings-card channel-content"
    >
      <div class="settings-heading">
        <div>
          <h2>
            Paramètres
          </h2>

          <p class="muted">
            Identité et présentation du canal.
          </p>
        </div>
      </div>

      <template v-if="channel.isCreator">
        <form
          class="channel-settings-form"
          @submit.prevent="save"
        >
          <label class="channel-settings-field">
            <span>
              Nom
            </span>

            <input
              v-model="name"
              type="text"
              maxlength="120"
              required
              autocomplete="off"
              placeholder="Nom du canal"
            />
          </label>

          <label class="channel-settings-field">
            <span>
              Description
            </span>

            <textarea
              v-model="description"
              maxlength="2000"
              rows="6"
              placeholder="Description du canal"
            />
          </label>

          <label class="channel-settings-field">
            <span>
              Image du canal
            </span>

            <input
              v-model="profileImageUrl"
              type="url"
              maxlength="2048"
              autocomplete="off"
              placeholder="https://..."
            />
          </label>

          <div
            v-if="profileImageUrl.trim()"
            class="channel-settings-preview"
          >
            <img
              :src="profileImageUrl.trim()"
              alt="Aperçu de l’image du canal"
            />
          </div>

          <p
            v-if="error"
            class="form-error"
          >
            {{ error }}
          </p>

          <p
            v-if="success"
            class="form-success"
          >
            {{ success }}
          </p>

          <div class="channel-settings-actions">
            <button
              type="submit"
              class="primary"
              :disabled="
                saving
                || !name.trim()
              "
            >
              {{
                saving
                  ? 'Enregistrement…'
                  : 'Enregistrer'
              }}
            </button>
          </div>
        </form>
      </template>

      <p
        v-else
        class="muted"
      >
        Seul le créateur du canal peut modifier
        son nom, sa description et son image.
      </p>
    </section>

        <ChannelRolesPanel
      v-if="channel.isCreator"
      :channel-code="channel.code"
      @transferred="refreshAfterOwnershipTransfer"
    />

<section
      v-if="channel.isCreator"
      class="settings-card channel-content"
    >
      <div class="settings-heading">
        <div>
          <h2>
            Exporter le canal
          </h2>

          <p class="muted">
            Télécharge une copie JSON des
            métadonnées, membres, notes,
            tâches et messages du canal.
          </p>
        </div>
      </div>

      <div class="channel-settings-actions">
        <button
          type="button"
          class="primary"
          :disabled="exporting"
          @click="exportChannel"
        >
          {{
            exporting
              ? 'Export en cours…'
              : 'Télécharger l’export'
          }}
        </button>
      </div>
    </section>

    <section
      v-if="!channel.isCreator"
      class="settings-card danger-zone channel-danger-zone"
    >
      <div class="settings-heading">
        <div>
          <h2>
            Quitter le canal
          </h2>

          <p class="muted">
            Vous perdrez immédiatement l’accès
            aux notes, tâches et messages de ce canal.
          </p>
        </div>
      </div>

      <button
        type="button"
        class="channel-danger-button"
        :disabled="leaving"
        @click="leaveChannel"
      >
        {{
          leaving
            ? 'Départ…'
            : 'Quitter le canal'
        }}
      </button>
    </section>

    <section
      v-if="channel.isCreator"
      class="settings-card danger-zone channel-danger-zone"
    >
      <div class="settings-heading">
        <div>
          <h2>
            Fermer le canal
          </h2>

          <p class="muted">
            Le canal disparaîtra pour tous ses membres.
            Les notes, tâches et messages resteront
            conservés en base de données.
          </p>
        </div>
      </div>

      <button
        type="button"
        class="channel-danger-button"
        :disabled="closing"
        @click="closeChannel"
      >
        {{
          closing
            ? 'Fermeture…'
            : 'Fermer le canal'
        }}
      </button>
    </section>
  </div>
</template>
