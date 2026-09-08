import {
  computed,
  ref,
} from 'vue'

import {
  api,
} from '../services/api'

import {
  useNotificationSound,
} from './useNotificationSound'

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

interface PersonalNotification {
  type?: string
  channelCode?: string
  messageId?: number
}

export interface ChannelStructureEvent {
  type: 'channel-structure'

  event:
    | 'member-joined'
    | 'member-left'
    | 'member-removed'
    | 'role-changed'
    | 'ownership-transferred'
    | 'channel-updated'
    | 'channel-closed'

  channelCode: string

  userId?: number
  role?: string

  previousCreatorUserId?: number
  newCreatorUserId?: number
}

const total =
  ref(0)

const channels =
  ref<ChannelUnreadCount[]>([])

const lastStructureEvent =
  ref<ChannelStructureEvent | null>(
    null,
  )

const structureEventVersion =
  ref(0)

let consumers = 0

let eventSource:
  EventSource
  | null = null

let reconnectTimer:
  ReturnType<typeof window.setTimeout>
  | null = null

let connecting = false

const {
  initialize:
    initializeNotificationSound,
  play:
    playNotificationSound,
} = useNotificationSound()

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
     * Notification state is secondary.
     * Navigation must remain usable if
     * this refresh temporarily fails.
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

function isStructureEvent(
  value: unknown,
): value is ChannelStructureEvent {
  if (
    typeof value !== 'object'
    || value === null
  ) {
    return false
  }

  const candidate =
    value as Partial<ChannelStructureEvent>

  return (
    candidate.type
      === 'channel-structure'
    && typeof candidate.event
      === 'string'
    && typeof candidate.channelCode
      === 'string'
  )
}

function handleNotification(
  event: MessageEvent<string>,
): void {
  let notification:
    PersonalNotification
    | ChannelStructureEvent

  try {
    notification =
      JSON.parse(
        event.data,
      ) as
        PersonalNotification
        | ChannelStructureEvent
  } catch {
    return
  }

  if (
    isStructureEvent(
      notification,
    )
  ) {
    lastStructureEvent.value =
      notification

    structureEventVersion.value += 1

    /*
     * Membership changes can also alter the
     * unread-channel response. Keep the shared
     * badge state authoritative.
     */
    void refresh()

    return
  }

  if (
    notification.type
    === 'channel-message-state-changed'
  ) {
    /*
     * Deletion/edit state is not a new message:
     * recalculate without any sound.
     */
    void refresh()

    return
  }

  if (
    notification.type
    !== 'channel-message'
  ) {
    /*
     * Invitation events have their own
     * composable and must not trigger the
     * channel-message notification sound.
     */
    return
  }

  playNotificationSound()

  void refresh()
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

    source.onmessage =
      handleNotification

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

  void initializeNotificationSound()
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

    lastStructureEvent:
      computed(
        () =>
          lastStructureEvent.value,
      ),

    structureEventVersion:
      computed(
        () =>
          structureEventVersion.value,
      ),

    unreadFor,

    refresh,
    start,
    stop,
  }
}
