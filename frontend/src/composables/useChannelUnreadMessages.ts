import {
  computed,
  ref,
} from 'vue'

import {
  api,
} from '../services/api'

interface ChannelUnreadCount {
  channelId: number
  channelCode: string
  unreadCount: number
}

interface ChannelUnreadResponse {
  total: number
  channels: ChannelUnreadCount[]
}

interface MercureAuthorization {
  hubUrl: string
  topic: string
}

const total =
  ref(0)

const channels =
  ref<ChannelUnreadCount[]>([])

let consumers = 0

let eventSource:
  EventSource
  | null = null

let reconnectTimer:
  ReturnType<typeof window.setTimeout>
  | null = null

let connecting = false

async function refresh(): Promise<void> {
  try {
    const response =
      await api<ChannelUnreadResponse>(
        '/api/channels/unread-messages',
      )

    total.value =
      response.total

    channels.value =
      response.channels
  } catch {
    /*
     * A badge refresh failure must not
     * interrupt navigation.
     */
  }
}

function unreadFor(
  code: string,
): number {
  return (
    channels.value.find(
      channel =>
        channel.channelCode === code,
    )?.unreadCount
    ?? 0
  )
}

function closeRealtime(): void {
  if (!eventSource) {
    return
  }

  eventSource.close()
  eventSource = null
}

function clearReconnect(): void {
  if (reconnectTimer === null) {
    return
  }

  window.clearTimeout(
    reconnectTimer,
  )

  reconnectTimer = null
}

function scheduleReconnect(): void {
  if (
    consumers === 0
    || reconnectTimer !== null
  ) {
    return
  }

  reconnectTimer =
    window.setTimeout(
      () => {
        reconnectTimer = null
        void connectRealtime()
      },
      2_000,
    )
}

async function connectRealtime(): Promise<void> {
  if (
    consumers === 0
    || connecting
  ) {
    return
  }

  connecting = true

  closeRealtime()
  clearReconnect()

  try {
    const authorization =
      await api<MercureAuthorization>(
        '/api/channel-notifications/mercure-auth',
        {
          method: 'POST',
        },
      )

    if (consumers === 0) {
      return
    }

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

    source.onmessage = () => {
      /*
       * The notification carries no message
       * contents. PostgreSQL remains the source
       * of truth for the unread count.
       */
      void refresh()
    }

    source.onerror = () => {
      if (
        eventSource !== source
      ) {
        return
      }

      source.close()
      eventSource = null

      scheduleReconnect()
    }
  } catch {
    scheduleReconnect()
  } finally {
    connecting = false
  }
}

function start(): void {
  consumers += 1

  if (consumers !== 1) {
    return
  }

  void refresh()
  void connectRealtime()
}

function stop(): void {
  consumers =
    Math.max(
      0,
      consumers - 1,
    )

  if (consumers !== 0) {
    return
  }

  clearReconnect()
  closeRealtime()
}

export function useChannelUnreadMessages() {
  return {
    total:
      computed(
        () =>
          total.value,
      ),

    channels:
      computed(
        () =>
          channels.value,
      ),

    unreadFor,

    refresh,
    start,
    stop,
  }
}
