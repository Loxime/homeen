import { ref } from 'vue'
import { api } from '../services/api'

const unreadCount = ref(0)
const loading = ref(false)

let intervalId:
  ReturnType<typeof window.setInterval>
  | null = null

async function refreshUnreadCount(): Promise<void> {
  if (loading.value) {
    return
  }

  loading.value = true

  try {
    const response =
      await api<{
        count: number
      }>(
        '/api/channel-invitations/unread-count',
      )

    unreadCount.value =
      response.count
  } finally {
    loading.value = false
  }
}

function markLocallySeen(): void {
  unreadCount.value = 0
}

function startInvitationPolling(): void {
  if (intervalId !== null) {
    return
  }

  void refreshUnreadCount()

  intervalId =
    window.setInterval(
      () => {
        void refreshUnreadCount()
      },
      30_000,
    )
}

function stopInvitationPolling(): void {
  if (intervalId === null) {
    return
  }

  window.clearInterval(
    intervalId,
  )

  intervalId = null
}

export function useChannelInvitations() {
  return {
    unreadCount,
    refreshUnreadCount,
    markLocallySeen,
    startInvitationPolling,
    stopInvitationPolling,
  }
}
