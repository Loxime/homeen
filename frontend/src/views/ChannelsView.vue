<script setup lang="ts">
import { onMounted, ref } from 'vue'
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

const router = useRouter()

const channels = ref<Channel[]>([])
const loading = ref(true)
const creating = ref(false)
const error = ref('')

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
        : 'Unable to load channels.'
  } finally {
    loading.value = false
  }
}

async function createChannel(): Promise<void> {
  error.value = ''
  creating.value = true

  try {
    const channel =
      await api<Channel>(
        '/api/channels',
        {
          method: 'POST',
          body: JSON.stringify({
            name: name.value,
            description:
              description.value,
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
        : 'Unable to create channel.'
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

onMounted(() => void load())
</script>

<template>
  <section class="page-stack channels-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">
          COLLABORATION
        </p>

        <h1>Canaux</h1>

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

    <section class="settings-card">
      <h2>Créer un canal</h2>

      <form
        class="channel-create-form"
        @submit.prevent="createChannel"
      >
        <label>
          <span>Nom</span>

          <input
            v-model.trim="name"
            type="text"
            maxlength="120"
            placeholder="Nom du canal"
            required
          />
        </label>

        <label>
          <span>Description</span>

          <textarea
            v-model.trim="description"
            maxlength="2000"
            rows="3"
            placeholder="Description facultative"
          />
        </label>

        <button
          class="primary"
          :disabled="
            creating
            || !name
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
        <h2>Mes canaux</h2>

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
            <AppIcon
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
                {{
                  channel.memberCount
                }}
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
