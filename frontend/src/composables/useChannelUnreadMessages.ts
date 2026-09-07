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

const total =
  ref(0)

const channels =
  ref<ChannelUnreadCount[]>([])

let interval:
  ReturnType<typeof window.setInterval>
  | null = null

let consumers = 0

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

function start(): void {
  consumers += 1

  if (consumers !== 1) {
    return
  }

  void refresh()

  interval =
    window.setInterval(
      () => {
        void refresh()
      },
      30_000,
    )
}

function stop(): void {
  consumers =
    Math.max(
      0,
      consumers - 1,
    )

  if (
    consumers !== 0
    || interval === null
  ) {
    return
  }

  window.clearInterval(
    interval,
  )

  interval = null
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
