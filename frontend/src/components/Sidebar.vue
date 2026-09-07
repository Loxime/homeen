<script setup lang="ts">
import {
  computed,
  onUnmounted,
  ref,
} from 'vue'

import {
  useRouter,
} from 'vue-router'

import AppIcon from './AppIcon.vue'

import {
  ApiError,
} from '../services/api'

import {
  usePomodoro,
} from '../composables/usePomodoro'

import {
  useChannelInvitations,
} from '../composables/useChannelInvitations'

import {
  useChannelUnreadMessages,
} from '../composables/useChannelUnreadMessages'

defineProps<{
  collapsed: boolean
}>()

const router = useRouter()

const {
  store,
  quickStart,
} = usePomodoro()

const {
  unreadCount,
} = useChannelInvitations()

const {
  total: unreadMessageCount,
  start: startUnreadMessages,
  stop: stopUnreadMessages,
} = useChannelUnreadMessages()

const channelNotificationCount =
  computed(
    () =>
      unreadCount.value
      + unreadMessageCount.value,
  )

startUnreadMessages()

onUnmounted(() => {
  stopUnreadMessages()
})

const quickLoading = ref(false)

const quickLabel = computed(() => {
  if (store.active) {
    return 'Focus running'
  }

  const latest =
    store.presets[0]

  return latest
    ? `Start ${latest.workMinutes}m`
    : 'New session'
})

async function quickFocus(): Promise<void> {
  if (store.active) {
    await router.push(
      '/pomodoro',
    )

    return
  }

  quickLoading.value = true

  try {
    await quickStart()

    await router.push(
      '/pomodoro',
    )
  } catch (error) {
    if (
      error instanceof ApiError
      && error.status === 404
    ) {
      await router.push(
        '/pomodoro',
      )
    } else {
      throw error
    }
  } finally {
    quickLoading.value = false
  }
}
</script>

<template>
  <aside
    class="sidebar"
    :class="{
      collapsed,
    }"
  >
    <button
      class="sidebar-entry focus-quick"
      :title="
        collapsed
          ? quickLabel
          : undefined
      "
      :disabled="
        quickLoading
      "
      @click="
        quickFocus
      "
    >
      <span class="sidebar-icon-slot">
        <AppIcon name="timer" />

        <span
          class="pulse-dot"
          :class="{
            active: store.active,
          }"
        />
      </span>

      <span class="sidebar-label">
        {{
          quickLoading
            ? 'Starting…'
            : quickLabel
        }}
      </span>
    </button>

    <nav class="nav-list">
      <RouterLink
        class="sidebar-entry"
        to="/notes"
      >
        <span class="sidebar-icon-slot">
          <AppIcon name="note" />
        </span>

        <span class="sidebar-label">
          Notes
        </span>
      </RouterLink>

      <RouterLink
        class="sidebar-entry"
        to="/labels"
      >
        <span class="sidebar-icon-slot">
          <AppIcon name="tag" />
        </span>

        <span class="sidebar-label">
          Labels
        </span>
      </RouterLink>

      <RouterLink
        class="sidebar-entry"
        to="/pomodoro"
      >
        <span class="sidebar-icon-slot">
          <AppIcon name="timer" />
        </span>

        <span class="sidebar-label">
          Pomodoro
        </span>
      </RouterLink>

      <RouterLink
        class="sidebar-entry"
        to="/statistics"
      >
        <span class="sidebar-icon-slot">
          <AppIcon name="chart" />
        </span>

        <span class="sidebar-label">
          Statistics
        </span>
      </RouterLink>

      <RouterLink
        class="sidebar-entry channel-entry"
        to="/channels"
      >
        <span class="sidebar-icon-slot">
          <AppIcon name="users" />

          <span
            v-if="
              collapsed
              && channelNotificationCount > 0
            "
            class="sidebar-notification-dot"
          />
        </span>

        <span class="sidebar-label">
          Canaux
        </span>

        <span
          v-if="
            !collapsed
            && channelNotificationCount > 0
          "
          class="sidebar-notification-badge"
          :title="
            `${channelNotificationCount} notification${channelNotificationCount > 1 ? 's' : ''} non lue${channelNotificationCount > 1 ? 's' : ''}`
          "
        >
          {{
            channelNotificationCount > 99
              ? '99+'
              : channelNotificationCount
          }}
        </span>
      </RouterLink>

      <RouterLink
        class="sidebar-entry"
        to="/archived"
      >
        <span class="sidebar-icon-slot">
          <AppIcon name="archive" />
        </span>

        <span class="sidebar-label">
          Archived
        </span>
      </RouterLink>

      <RouterLink
        class="sidebar-entry"
        to="/trash"
      >
        <span class="sidebar-icon-slot">
          <AppIcon name="trash" />
        </span>

        <span class="sidebar-label">
          Trash
        </span>
      </RouterLink>

      <RouterLink
        class="sidebar-entry"
        to="/profile"
      >
        <span class="sidebar-icon-slot">
          <AppIcon name="user" />
        </span>

        <span class="sidebar-label">
          Profile
        </span>
      </RouterLink>
    </nav>
  </aside>
</template>
