import {
  computed,
  ref,
} from 'vue'

import {
  api,
} from '../services/api'

interface InvitationUnreadResponse {
  unreadCount?: number
  count?: number
  unread?: number
  total?: number
}

interface MercureAuthorization {
  hubUrl: string
  topic: string
}

interface PersonalNotification {
  type?: string
  channelCode?: string
}

const unreadCount =
  ref(0)

/*
 * Incremented only when a new invitation
 * signal arrives through Mercure.
 *
 * Views that display the invitation list
 * can watch it and reload authoritative
 * data from PostgreSQL.
 */
const eventVersion =
  ref(0)

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
      await api<InvitationUnreadResponse>(
        '/api/channel-invitations/unread-count',
      )

    const value =
      response.unreadCount
      ?? response.count
      ?? response.unread
      ?? response.total
      ?? 0

    unreadCount.value =
      Number.isFinite(
        Number(value),
      )
        ? Math.max(
            0,
            Number(value),
          )
        : 0
  } catch {
    /*
     * The badge is secondary.
     * A refresh failure must not interrupt
     * normal application navigation.
     */
  }
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

    source.onmessage = event => {
      try {
        const notification =
          JSON.parse(
            event.data,
          ) as PersonalNotification

        if (
          notification.type
          !== 'channel-invitation'
        ) {
          return
        }

        /*
         * Mercure only signals that something
         * changed. The HTTP endpoint remains
         * authoritative for the actual count.
         */
        eventVersion.value += 1

        void refresh()
      } catch {
        /*
         * Ignore malformed or unrelated
         * personal events.
         */
      }
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

export function useChannelInvitations() {
  return {
    unreadCount:
      computed(
        () =>
          unreadCount.value,
      ),

    eventVersion:
      computed(
        () =>
          eventVersion.value,
      ),

    refresh,
    start,
    stop,
  }
}
