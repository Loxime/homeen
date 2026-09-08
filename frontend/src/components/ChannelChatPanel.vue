<script setup lang="ts">
import {
  nextTick,
  onMounted,
  onUnmounted,
  ref,
  watch,
} from 'vue'

import {
  api,
} from '../services/api'

import {
  useChannelUnreadMessages,
} from '../composables/useChannelUnreadMessages'

import {
  formatDate,
} from '../services/format'

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

interface MercureAuthorization {
  hubUrl: string
  topic: string
  currentUserId: number
}

interface ChannelMessageUpdatedEvent {
  type: 'updated'
  message: ChannelMessage
}

interface ChannelMessageDeletedEvent {
  type: 'deleted'
  messageId: number
}

type ChannelRealtimeEvent =
  | ChannelMessage
  | ChannelMessageUpdatedEvent
  | ChannelMessageDeletedEvent

const props = defineProps<{
  channelCode: string
}>()

const {
  refresh: refreshUnreadMessages,
} = useChannelUnreadMessages()

const messages =
  ref<ChannelMessage[]>([])

const content = ref('')
const sending = ref(false)
const loading = ref(true)
const error = ref('')

const editingMessageId =
  ref<number | null>(null)

const editingContent =
  ref('')

const updatingMessageId =
  ref<number | null>(null)

const deletingMessageId =
  ref<number | null>(null)

const currentUserId =
  ref<number | null>(null)

const realtimeStatus =
  ref<
    | 'connecting'
    | 'connected'
    | 'reconnecting'
  >('connecting')

const messageList =
  ref<HTMLElement | null>(null)

let eventSource:
  EventSource
  | null = null

async function scrollBottom(): Promise<void> {
  await nextTick()

  if (!messageList.value) {
    return
  }

  messageList.value.scrollTop =
    messageList.value.scrollHeight
}

function appendMessage(
  message: ChannelMessage,
): boolean {
  const exists =
    messages.value.some(
      current =>
        current.id === message.id,
    )

  if (exists) {
    return false
  }

  const normalized: ChannelMessage = {
    ...message,

    isMine:
      currentUserId.value !== null
        ? message.authorUserId
          === currentUserId.value
        : message.isMine,
  }

  messages.value.push(
    normalized,
  )

  messages.value.sort(
    (a, b) =>
      a.id - b.id,
  )

  return true
}

function replaceMessage(
  message: ChannelMessage,
): void {
  const index =
    messages.value.findIndex(
      current =>
        current.id === message.id,
    )

  const normalized: ChannelMessage = {
    ...message,

    isMine:
      currentUserId.value !== null
        ? message.authorUserId
          === currentUserId.value
        : message.isMine,
  }

  if (index === -1) {
    appendMessage(
      normalized,
    )

    return
  }

  messages.value[index] =
    normalized
}

function removeMessage(
  messageId: number,
): void {
  messages.value =
    messages.value.filter(
      message =>
        message.id !== messageId,
    )

  if (
    editingMessageId.value
    === messageId
  ) {
    editingMessageId.value = null
    editingContent.value = ''
  }
}

async function markRead(): Promise<void> {
  try {
    await api(
      `/api/channels/${props.channelCode}/messages/read`,
      {
        method: 'POST',
      },
    )

    await refreshUnreadMessages()
  } catch {
    /*
     * Reading state is secondary and must
     * never interrupt the live conversation.
     */
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

    /*
     * Merge instead of replacing because a
     * realtime message may arrive while this
     * HTTP request is still in progress.
     */
    for (
      const message
      of response.messages
    ) {
      appendMessage(
        message,
      )
    }

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

function closeRealtime(): void {
  if (!eventSource) {
    return
  }

  eventSource.close()
  eventSource = null
}

async function connectRealtime(): Promise<void> {
  closeRealtime()

  realtimeStatus.value =
    'connecting'

  const authorization =
    await api<MercureAuthorization>(
      `/api/channels/${props.channelCode}/mercure-auth`,
      {
        method: 'POST',
      },
    )

  currentUserId.value =
    authorization.currentUserId

  const url =
    new URL(
      authorization.hubUrl,
      window.location.origin,
    )

  url.searchParams.append(
    'topic',
    authorization.topic,
  )

  const source =
    new EventSource(
      url.toString(),
      {
        withCredentials: true,
      },
    )

  eventSource = source

  source.onopen = () => {
    realtimeStatus.value =
      'connected'
  }

  source.onerror = () => {
    /*
     * EventSource automatically reconnects.
     * We therefore keep the same instance.
     */
    realtimeStatus.value =
      'reconnecting'
  }

  source.onmessage = event => {
    try {
      const payload =
        JSON.parse(
          event.data,
        ) as ChannelRealtimeEvent

      if (
        'type' in payload
        && payload.type === 'updated'
      ) {
        replaceMessage(
          payload.message,
        )

        return
      }

      if (
        'type' in payload
        && payload.type === 'deleted'
      ) {
        removeMessage(
          payload.messageId,
        )

        return
      }

      if (
        !('type' in payload)
        && appendMessage(
          payload,
        )
      ) {
        void scrollBottom()
        void markRead()
      }
    } catch {
      /*
       * Ignore malformed events instead of
       * breaking the live stream.
       */
    }
  }
}

async function openChannel(): Promise<void> {
  closeRealtime()

  messages.value = []
  content.value = ''
  editingMessageId.value = null
  editingContent.value = ''
  updatingMessageId.value = null
  deletingMessageId.value = null
  currentUserId.value = null
  error.value = ''

  try {
    /*
     * Connect first, then hydrate history.
     * Any message arriving while history is
     * loading is merged by appendMessage().
     */
    await connectRealtime()
    await loadInitial()
  } catch (exception) {
    loading.value = false

    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’établir le temps réel.'
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

    /*
     * Immediate local rendering.
     * The Mercure copy will be ignored
     * by appendMessage() thanks to its ID.
     */
    appendMessage(
      message,
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

function startEdit(
  message: ChannelMessage,
): void {
  if (!message.isMine) {
    return
  }

  editingMessageId.value =
    message.id

  editingContent.value =
    message.content

  error.value = ''
}

function cancelEdit(): void {
  editingMessageId.value = null
  editingContent.value = ''
}

async function saveEdit(
  message: ChannelMessage,
): Promise<void> {
  const value =
    editingContent.value.trim()

  if (
    !message.isMine
    || !value
    || updatingMessageId.value !== null
  ) {
    return
  }

  updatingMessageId.value =
    message.id

  error.value = ''

  try {
    const updated =
      await api<ChannelMessage>(
        `/api/channels/${props.channelCode}/messages/${message.id}`,
        {
          method: 'PATCH',

          body: JSON.stringify({
            content: value,
          }),
        },
      )

    replaceMessage(
      updated,
    )

    cancelEdit()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier le message.'
  } finally {
    updatingMessageId.value = null
  }
}

async function deleteMessage(
  message: ChannelMessage,
): Promise<void> {
  if (
    !message.isMine
    || deletingMessageId.value !== null
  ) {
    return
  }

  const confirmed =
    window.confirm(
      'Supprimer ce message ?',
    )

  if (!confirmed) {
    return
  }

  deletingMessageId.value =
    message.id

  error.value = ''

  try {
    await api(
      `/api/channels/${props.channelCode}/messages/${message.id}`,
      {
        method: 'DELETE',
      },
    )

    removeMessage(
      message.id,
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de supprimer le message.'
  } finally {
    deletingMessageId.value = null
  }
}

watch(
  () => props.channelCode,
  () => {
    void openChannel()
  },
)

onMounted(() => {
  void openChannel()
})

onUnmounted(() => {
  closeRealtime()
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

      <span
        class="channel-chat-status"
        :class="{
          connected:
            realtimeStatus
              === 'connected',
          reconnecting:
            realtimeStatus
              === 'reconnecting',
        }"
      >
        <template
          v-if="
            realtimeStatus
              === 'connected'
          "
        >
          Temps réel actif
        </template>

        <template
          v-else-if="
            realtimeStatus
              === 'reconnecting'
          "
        >
          Reconnexion…
        </template>

        <template v-else>
          Connexion…
        </template>
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
        v-if="
          loading
          && messages.length === 0
        "
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

          <div class="channel-message-meta-right">
            <span>
              {{
                formatDate(
                  message.createdAt,
                )
              }}

              <template
                v-if="message.editedAt"
              >
                · modifié
              </template>
            </span>

            <div
              v-if="
                message.isMine
                && editingMessageId !== message.id
              "
              class="channel-message-actions"
            >
              <button
                type="button"
                class="channel-message-action"
                :disabled="
                  deletingMessageId === message.id
                "
                @click="
                  startEdit(message)
                "
              >
                Modifier
              </button>

              <button
                type="button"
                class="channel-message-action danger"
                :disabled="
                  deletingMessageId === message.id
                "
                @click="
                  deleteMessage(message)
                "
              >
                {{
                  deletingMessageId === message.id
                    ? 'Suppression…'
                    : 'Supprimer'
                }}
              </button>
            </div>
          </div>
        </div>

        <div
          v-if="
            editingMessageId === message.id
          "
          class="channel-message-edit"
        >
          <textarea
            v-model="editingContent"
            maxlength="4000"
            rows="3"
            :disabled="
              updatingMessageId === message.id
            "
            @keydown.esc.prevent="
              cancelEdit
            "
            @keydown.enter.exact.prevent="
              saveEdit(message)
            "
          />

          <div class="channel-message-edit-footer">
            <span class="char-count">
              {{ editingContent.length }}/4000
            </span>

            <div class="channel-message-edit-actions">
              <button
                type="button"
                class="channel-message-action"
                :disabled="
                  updatingMessageId === message.id
                "
                @click="
                  cancelEdit
                "
              >
                Annuler
              </button>

              <button
                type="button"
                class="primary"
                :disabled="
                  updatingMessageId === message.id
                  || !editingContent.trim()
                "
                @click="
                  saveEdit(message)
                "
              >
                {{
                  updatingMessageId === message.id
                    ? 'Enregistrement…'
                    : 'Enregistrer'
                }}
              </button>
            </div>
          </div>
        </div>

        <p v-else>
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
