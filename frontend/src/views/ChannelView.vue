<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
  watch,
} from 'vue'

import {
  useRoute,
  useRouter,
} from 'vue-router'

import AppIcon from '../components/AppIcon.vue'

import {
  ApiError,
  api,
} from '../services/api'

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

type ChannelTab =
  | 'notes'
  | 'tasks'
  | 'members'
  | 'chat'
  | 'settings'

const route = useRoute()
const router = useRouter()

const channel = ref<Channel | null>(null)
const loading = ref(true)
const error = ref('')

const activeTab =
  ref<ChannelTab>('notes')

const code = computed(
  () => String(
    route.params.code ?? '',
  ),
)

function formatCode(
  value: string,
): string {
  if (
    !/^\d{9}$/.test(value)
  ) {
    return value
  }

  return [
    value.slice(0, 3),
    value.slice(3, 6),
    value.slice(6, 9),
  ].join('-')
}

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  channel.value = null

  try {
    channel.value =
      await api<Channel>(
        `/api/channels/${code.value}`,
      )
  } catch (exception) {
    if (
      exception instanceof ApiError
      && exception.code
        === 'CHANNEL_FORBIDDEN'
    ) {
      await router.replace({
        path: '/notes',
        query: {
          channelDenied:
            formatCode(code.value),
        },
      })

      return
    }

    if (
      exception instanceof ApiError
      && exception.code
        === 'CHANNEL_NOT_FOUND'
    ) {
      error.value =
        'Ce canal n’existe pas ou a été fermé.'

      return
    }

    error.value =
      exception instanceof Error
        ? exception.message
        : 'Unable to load channel.'
  } finally {
    loading.value = false
  }
}

function selectTab(
  tab: ChannelTab,
): void {
  activeTab.value = tab
}

watch(
  () => route.params.code,
  () => void load(),
)

onMounted(() => void load())
</script>

<template>
  <section class="page-stack channel-page">
    <p
      v-if="loading"
      class="muted"
    >
      Chargement du canal…
    </p>

    <div
      v-else-if="error"
      class="settings-card"
    >
      <h1>Canal indisponible</h1>

      <p class="form-error">
        {{ error }}
      </p>

      <RouterLink
        class="primary channel-back-link"
        to="/channels"
      >
        Retour aux canaux
      </RouterLink>
    </div>

    <template v-else-if="channel">
      <header class="channel-header">
        <div class="channel-avatar">
          <img
            v-if="channel.profileImageUrl"
            :src="channel.profileImageUrl"
            alt=""
          />

          <AppIcon
            v-else
            name="users"
            :size="26"
          />
        </div>

        <div class="channel-header-copy">
          <p class="eyebrow">
            CANAL
          </p>

          <h1>
            {{ channel.name }}
          </h1>

          <p class="muted">
            {{ channel.formattedCode }}
            ·
            {{ channel.memberCount }}
            membre{{
              channel.memberCount > 1
                ? 's'
                : ''
            }}
          </p>
        </div>
      </header>

      <p
        v-if="channel.description"
        class="channel-lead"
      >
        {{ channel.description }}
      </p>

      <nav
        class="channel-tabs"
        aria-label="Navigation du canal"
      >
        <button
          type="button"
          :class="{
            active:
              activeTab === 'notes',
          }"
          @click="selectTab('notes')"
        >
          Notes
        </button>

        <button
          type="button"
          :class="{
            active:
              activeTab === 'tasks',
          }"
          @click="selectTab('tasks')"
        >
          Tâches
        </button>

        <button
          type="button"
          :class="{
            active:
              activeTab === 'members',
          }"
          @click="selectTab('members')"
        >
          Membres
        </button>

        <button
          type="button"
          :class="{
            active:
              activeTab === 'chat',
          }"
          @click="selectTab('chat')"
        >
          Chat
        </button>

        <button
          type="button"
          :class="{
            active:
              activeTab === 'settings',
          }"
          @click="selectTab('settings')"
        >
          Paramètres
        </button>
      </nav>

      <section class="settings-card channel-placeholder">
        <template v-if="activeTab === 'notes'">
          <h2>Notes</h2>

          <p class="muted">
            Les notes collaboratives seront
            branchées après la gestion des
            membres et invitations.
          </p>
        </template>

        <template v-else-if="activeTab === 'tasks'">
          <h2>Tâches</h2>

          <p class="muted">
            Les tâches du canal seront
            dérivées des notes partagées.
          </p>
        </template>

        <template v-else-if="activeTab === 'members'">
          <h2>Membres</h2>

          <p class="muted">
            La gestion des membres arrive
            à l’étape suivante.
          </p>
        </template>

        <template v-else-if="activeTab === 'chat'">
          <h2>Chat</h2>

          <p class="muted">
            Le chat temps réel sera ajouté
            après les invitations.
          </p>
        </template>

        <template v-else>
          <h2>Paramètres</h2>

          <p class="muted">
            Nom, description, image,
            export et fermeture seront
            disponibles ici.
          </p>
        </template>
      </section>
    </template>
  </section>
</template>
