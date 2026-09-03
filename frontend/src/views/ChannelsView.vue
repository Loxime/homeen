<script setup lang="ts">
import {
  onMounted,
  ref,
} from 'vue'

import { useRouter } from 'vue-router'

import AppIcon from '../components/AppIcon.vue'
import { api } from '../services/api'

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

interface ChannelListResponse {
  channels: Channel[]
}

interface ChannelInvitation {
  id: number
  channelId: number
  code: string
  formattedCode: string
  name: string
  description: string
  invitedByEmail: string
  createdAt: string
  seenAt: string | null
}

interface InvitationResponse {
  invitations: ChannelInvitation[]
}

const router = useRouter()

const channels = ref<Channel[]>([])
const invitations = ref<ChannelInvitation[]>([])

const loading = ref(true)
const invitationsLoading = ref(true)
const creating = ref(false)

const error = ref('')
const invitationError = ref('')

const name = ref('')
const description = ref('')

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const response =
      await api<ChannelListResponse>(
        '/api/channels',
      )

    channels.value =
      response.channels
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les canaux.'
  } finally {
    loading.value = false
  }
}

async function loadInvitations(): Promise<void> {
  invitationsLoading.value = true
  invitationError.value = ''

  try {
    const response =
      await api<InvitationResponse>(
        '/api/channel-invitations',
      )

    invitations.value =
      response.invitations

    const hasUnread =
      invitations.value.some(
        invitation =>
          invitation.seenAt === null,
      )

    if (hasUnread) {
      await api(
        '/api/channel-invitations/seen',
        {
          method: 'POST',
        },
      )

      invitations.value =
        invitations.value.map(
          invitation => ({
            ...invitation,
            seenAt:
              invitation.seenAt
              ?? new Date().toISOString(),
          }),
        )
    }
  } catch (exception) {
    invitationError.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les invitations.'
  } finally {
    invitationsLoading.value = false
  }
}

async function createChannel(): Promise<void> {
  error.value = ''

  if (!name.value.trim()) {
    return
  }

  creating.value = true

  try {
    const channel =
      await api<Channel>(
        '/api/channels',
        {
          method: 'POST',

          body: JSON.stringify({
            name:
              name.value.trim(),

            description:
              description.value.trim(),
          }),
        },
      )

    name.value = ''
    description.value = ''

    await router.push(
      `/canal/${channel.code}`,
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de créer le canal.'
  } finally {
    creating.value = false
  }
}

async function openChannel(
  channel: Channel,
): Promise<void> {
  await router.push(
    `/canal/${channel.code}`,
  )
}

async function acceptInvitation(
  invitation: ChannelInvitation,
): Promise<void> {
  invitationError.value = ''

  try {
    const response =
      await api<{
        code: string
        formattedCode: string
      }>(
        `/api/channel-invitations/${invitation.id}/accept`,
        {
          method: 'POST',
        },
      )

    await router.push(
      `/canal/${response.code}`,
    )
  } catch (exception) {
    invitationError.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’accepter l’invitation.'
  }
}

async function rejectInvitation(
  invitation: ChannelInvitation,
): Promise<void> {
  invitationError.value = ''

  try {
    await api(
      `/api/channel-invitations/${invitation.id}/reject`,
      {
        method: 'POST',
      },
    )

    invitations.value =
      invitations.value.filter(
        candidate =>
          candidate.id
          !== invitation.id,
      )
  } catch (exception) {
    invitationError.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de refuser l’invitation.'
  }
}

onMounted(() => {
  void Promise.all([
    load(),
    loadInvitations(),
  ])
})
</script>

<template>
  <section class="page-stack channels-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">
          COLLABORATION
        </p>

        <h1>
          Canaux
        </h1>

        <p class="muted">
          Vos espaces de travail partagés.
        </p>
      </div>
    </header>

    <p
      v-if="error"
      class="form-error"
    >
      {{ error }}
    </p>

    <p
      v-if="invitationError"
      class="form-error"
    >
      {{ invitationError }}
    </p>

    <section
      v-if="
        invitationsLoading
        || invitations.length
      "
      class="settings-card channel-invitations"
    >
      <div>
        <p class="eyebrow">
          INVITATIONS
        </p>

        <h2>
          Invitations reçues
        </h2>
      </div>

      <p
        v-if="invitationsLoading"
        class="muted"
      >
        Chargement…
      </p>

      <div
        v-else
        class="channel-invitation-list"
      >
        <article
          v-for="invitation in invitations"
          :key="invitation.id"
          class="channel-invitation-row"
        >
          <div>
            <div class="channel-card-title">
              <strong>
                {{ invitation.name }}
              </strong>
            </div>

            <p
              v-if="invitation.description"
              class="muted channel-description"
            >
              {{ invitation.description }}
            </p>

            <div class="channel-meta">
              <span>
                {{ invitation.formattedCode }}
              </span>

              <span>
                Invité par
                {{ invitation.invitedByEmail }}
              </span>
            </div>
          </div>

          <div class="channel-invitation-actions">
            <button
              class="ghost"
              type="button"
              @click="
                rejectInvitation(invitation)
              "
            >
              Refuser
            </button>

            <button
              class="primary"
              type="button"
              @click="
                acceptInvitation(invitation)
              "
            >
              Accepter
            </button>
          </div>
        </article>
      </div>
    </section>

    <section class="settings-card">
      <h2>
        Créer un canal
      </h2>

      <form
        class="channel-create-form"
        @submit.prevent="createChannel"
      >
        <label>
          <span>
            Nom
          </span>

          <input
            v-model="name"
            type="text"
            maxlength="120"
            placeholder="Nom du canal"
            required
          />
        </label>

        <label>
          <span>
            Description
          </span>

          <textarea
            v-model="description"
            maxlength="2000"
            rows="3"
            placeholder="Description facultative"
          />
        </label>

        <button
          class="primary"
          type="submit"
          :disabled="
            creating
            || !name.trim()
          "
        >
          <AppIcon
            name="plus"
            :size="18"
          />

          {{
            creating
              ? 'Création…'
              : 'Créer le canal'
          }}
        </button>
      </form>
    </section>

    <section class="channels-section">
      <div class="channels-heading">
        <h2>
          Mes canaux
        </h2>

        <span class="muted">
          {{ channels.length }}
        </span>
      </div>

      <p
        v-if="loading"
        class="muted"
      >
        Chargement…
      </p>

      <div
        v-else-if="channels.length"
        class="channel-grid"
      >
        <button
          v-for="channel in channels"
          :key="channel.id"
          class="channel-card"
          type="button"
          @click="openChannel(channel)"
        >
          <div class="channel-card-icon">
            <img
              v-if="channel.profileImageUrl"
              :src="channel.profileImageUrl"
              alt=""
            />

            <AppIcon
              v-else
              name="users"
              :size="22"
            />
          </div>

          <div class="channel-card-body">
            <div class="channel-card-title">
              <strong>
                {{ channel.name }}
              </strong>

              <span
                v-if="channel.isCreator"
                class="profile-badge"
              >
                Créateur
              </span>
            </div>

            <p
              v-if="channel.description"
              class="muted channel-description"
            >
              {{ channel.description }}
            </p>

            <div class="channel-meta">
              <span>
                {{ channel.formattedCode }}
              </span>

              <span>
                {{ channel.memberCount }}
                membre{{
                  channel.memberCount > 1
                    ? 's'
                    : ''
                }}
              </span>
            </div>
          </div>
        </button>
      </div>

      <div
        v-else
        class="empty-channel-state"
      >
        <AppIcon
          name="users"
          :size="28"
        />

        <strong>
          Aucun canal
        </strong>

        <p class="muted">
          Créez votre premier espace
          collaboratif.
        </p>
      </div>
    </section>
  </section>
</template>
