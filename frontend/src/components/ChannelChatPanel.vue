<script setup lang="ts">
import {
  nextTick,
  onMounted,
  onUnmounted,
  ref,
  watch,
} from 'vue'

import { api } from '../services/api'
import { formatDate } from '../services/format'

interface ChannelMessage {
  id: number
  content: string
  authorUserId: number | null
  authorEmail: string | null
  createdAt: string
  updatedAt: string
  editedAt: string | null
  isMine: boolean
}

const props = defineProps<{
  channelCode: string
}>()

const messages =
  ref<ChannelMessage[]>([])

const content = ref('')
const sending = ref(false)
const loading = ref(true)
const error = ref('')

const messageList =
  ref<HTMLElement | null>(null)

let pollingId:
  ReturnType<typeof window.setInterval>
  | null = null

async function scrollBottom(): Promise<void> {
  await nextTick()

  if (!messageList.value) {
    return
  }

  messageList.value.scrollTop =
    messageList.value.scrollHeight
}

async function markRead(): Promise<void> {
  try {
    await api(
      `/api/channels/${props.channelCode}/messages/read`,
      {
        method: 'POST',
      },
    )
  } catch {
    // Reading state must never break the chat.
  }
}

async function loadInitial(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const response =
      await api<{
        messages: ChannelMessage[]
      }>(
        `/api/channels/${props.channelCode}/messages`,
      )

    messages.value =
      response.messages

    await scrollBottom()
    await markRead()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger le chat.'
  } finally {
    loading.value = false
  }
}

async function poll(): Promise<void> {
  const last =
    messages.value.at(-1)

  if (!last) {
    await loadInitial()
    return
  }

  try {
    const response =
      await api<{
        messages: ChannelMessage[]
      }>(
        `/api/channels/${props.channelCode}/messages?after=${last.id}`,
      )

    if (
      response.messages.length
      === 0
    ) {
      return
    }

    const existing =
      new Set(
        messages.value.map(
          message =>
            message.id,
        ),
      )

    for (
      const message
      of response.messages
    ) {
      if (
        !existing.has(
          message.id
        )
      ) {
        messages.value.push(
          message
        )
      }
    }

    await scrollBottom()
    await markRead()
  } catch {
    // Temporary polling failures are ignored.
  }
}

async function send(): Promise<void> {
  const value =
    content.value.trim()

  if (
    !value
    || sending.value
  ) {
    return
  }

  sending.value = true
  error.value = ''

  try {
    const message =
      await api<ChannelMessage>(
        `/api/channels/${props.channelCode}/messages`,
        {
          method: 'POST',

          body: JSON.stringify({
            content: value,
          }),
        },
      )

    messages.value.push(
      message
    )

    content.value = ''

    await scrollBottom()
    await markRead()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’envoyer le message.'
  } finally {
    sending.value = false
  }
}

function startPolling(): void {
  if (pollingId !== null) {
    return
  }

  pollingId =
    window.setInterval(
      () => {
        void poll()
      },
      5000,
    )
}

function stopPolling(): void {
  if (pollingId === null) {
    return
  }

  window.clearInterval(
    pollingId
  )

  pollingId = null
}

watch(
  () => props.channelCode,
  async () => {
    stopPolling()

    messages.value = []

    await loadInitial()

    startPolling()
  },
)

onMounted(async () => {
  await loadInitial()

  startPolling()
})

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <section class="channel-chat-panel">
    <header class="channel-chat-header">
      <div>
        <h2>
          Chat
        </h2>

        <p class="muted">
          Discutez avec les membres
          du canal.
        </p>
      </div>

      <span class="channel-chat-status">
        Actualisation automatique
      </span>
    </header>

    <p
      v-if="error"
      class="form-error"
    >
      {{ error }}
    </p>

    <div
      ref="messageList"
      class="channel-chat-messages"
    >
      <div
        v-if="loading"
        class="empty-state"
      >
        Chargement des messages…
      </div>

      <div
        v-else-if="
          messages.length === 0
        "
        class="empty-state"
      >
        <strong>
          Aucun message pour le moment.
        </strong>

        <p>
          Lancez la conversation.
        </p>
      </div>

      <article
        v-for="message in messages"
        :key="message.id"
        class="channel-message"
        :class="{
          mine:
            message.isMine,
        }"
      >
        <div class="channel-message-meta">
          <strong>
            {{
              message.isMine
                ? 'Vous'
                : (
                    message.authorEmail
                    || 'Ancien membre'
                  )
            }}
          </strong>

          <span>
            {{
              formatDate(
                message.createdAt,
              )
            }}
          </span>
        </div>

        <p>
          {{ message.content }}
        </p>
      </article>
    </div>

    <form
      class="channel-chat-composer"
      @submit.prevent="send"
    >
      <textarea
        v-model="content"
        maxlength="4000"
        rows="2"
        placeholder="Écrire un message…"
        :disabled="sending"
        @keydown.enter.exact.prevent="
          send
        "
      />

      <div class="channel-chat-composer-footer">
        <span class="char-count">
          {{ content.length }}/4000
        </span>

        <button
          class="primary"
          type="submit"
          :disabled="
            sending
            || !content.trim()
          "
        >
          {{
            sending
              ? 'Envoi…'
              : 'Envoyer'
          }}
        </button>
      </div>
    </form>
  </section>
</template>
