<script setup lang="ts">
import {
  computed,
  onMounted,
  onUnmounted,
  ref,
  watch,
} from 'vue'

import {
  useRoute,
  useRouter,
} from 'vue-router'

import BaseModal from './BaseModal.vue'
import Sidebar from './Sidebar.vue'
import TopBar from './TopBar.vue'

import {
  startUsageTracking,
  stopUsageTracking,
} from '../composables/useUsageTracking'

import {
  usePomodoro,
} from '../composables/usePomodoro'

import {
  useChannelInvitations,
} from '../composables/useChannelInvitations'

const route = useRoute()
const router = useRouter()

const {
  startGlobalTimer,
  stopGlobalTimer,
  loadPresets,
} = usePomodoro()

const {
  startInvitationPolling,
  stopInvitationPolling,
} = useChannelInvitations()

const MOBILE_QUERY =
  '(max-width: 720px)'

const mediaQuery =
  window.matchMedia(
    MOBILE_QUERY,
  )

const isMobile =
  ref(mediaQuery.matches)

const savedDesktopState =
  localStorage.getItem(
    'homeen-sidebar-collapsed',
  ) === '1'

const sidebarCollapsed = ref(
  isMobile.value
    ? true
    : savedDesktopState,
)

const deniedChannelCode = computed(
  () => {
    const value =
      route.query.channelDenied

    return typeof value === 'string'
      ? value
      : null
  },
)

function toggleSidebar(): void {
  sidebarCollapsed.value =
    !sidebarCollapsed.value
}

function handleViewportChange(
  event: MediaQueryListEvent,
): void {
  isMobile.value =
    event.matches

  if (event.matches) {
    sidebarCollapsed.value = true
  } else {
    sidebarCollapsed.value =
      localStorage.getItem(
        'homeen-sidebar-collapsed',
      ) === '1'
  }
}

async function closeDeniedModal(): Promise<void> {
  const query = {
    ...route.query,
  }

  delete query.channelDenied

  await router.replace({
    path: route.path,
    query,
  })
}

watch(
  sidebarCollapsed,
  (value) => {
    if (!isMobile.value) {
      localStorage.setItem(
        'homeen-sidebar-collapsed',
        value ? '1' : '0',
      )
    }
  },
)

watch(
  () => route.fullPath,
  () => {
    if (isMobile.value) {
      sidebarCollapsed.value = true
    }
  },
)

onMounted(() => {
  mediaQuery.addEventListener(
    'change',
    handleViewportChange,
  )

  startUsageTracking()
  startGlobalTimer()
  startInvitationPolling()

  void loadPresets()
})

onUnmounted(() => {
  mediaQuery.removeEventListener(
    'change',
    handleViewportChange,
  )

  stopUsageTracking()
  stopGlobalTimer()
  stopInvitationPolling()
})
</script>

<template>
  <div
    class="app-layout"
    :class="{
      'sidebar-collapsed':
        sidebarCollapsed,
      'is-mobile':
        isMobile,
    }"
  >
    <Sidebar
      :collapsed="sidebarCollapsed"
    />

    <button
      v-if="
        isMobile
        && !sidebarCollapsed
      "
      class="mobile-sidebar-backdrop"
      aria-label="Close navigation"
      @click="
        sidebarCollapsed = true
      "
    />

    <section class="workspace">
      <TopBar
        :sidebar-collapsed="
          sidebarCollapsed
        "
        @toggle-sidebar="
          toggleSidebar
        "
      />

      <div class="page-scroll">
        <RouterView
          v-slot="{
            Component,
            route: activeRoute,
          }"
        >
          <Transition
            name="page-fade"
            mode="out-in"
          >
            <component
              :is="Component"
              :key="activeRoute.path"
            />
          </Transition>
        </RouterView>
      </div>
    </section>

    <BaseModal
      :open="
        deniedChannelCode !== null
      "
      title="Accès au canal refusé"
      @close="
        closeDeniedModal
      "
    >
      <div class="channel-denied-modal">
        <p>
          Vous n'êtes pas autorisé
          à entrer dans le canal
          <strong>
            {{ deniedChannelCode }}
          </strong>.
        </p>

        <button
          class="primary"
          type="button"
          @click="
            closeDeniedModal
          "
        >
          Fermer
        </button>
      </div>
    </BaseModal>
  </div>
</template>
