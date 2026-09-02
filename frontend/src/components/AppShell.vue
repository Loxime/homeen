<script setup lang="ts">
import {
  onMounted,
  onUnmounted,
  ref,
  watch,
} from 'vue'
import { useRoute } from 'vue-router'

import Sidebar from './Sidebar.vue'
import TopBar from './TopBar.vue'

import {
  startUsageTracking,
  stopUsageTracking,
} from '../composables/useUsageTracking'

import { usePomodoro } from '../composables/usePomodoro'

const route = useRoute()

const {
  startGlobalTimer,
  stopGlobalTimer,
  loadPresets,
} = usePomodoro()

const MOBILE_QUERY = '(max-width: 720px)'

const mediaQuery = window.matchMedia(MOBILE_QUERY)

const isMobile = ref(mediaQuery.matches)

const savedDesktopState =
  localStorage.getItem('homeen-sidebar-collapsed') === '1'

const sidebarCollapsed = ref(
  isMobile.value
    ? true
    : savedDesktopState,
)

function toggleSidebar(): void {
  sidebarCollapsed.value = !sidebarCollapsed.value
}

function handleViewportChange(
  event: MediaQueryListEvent,
): void {
  isMobile.value = event.matches

  if (event.matches) {
    sidebarCollapsed.value = true
  } else {
    sidebarCollapsed.value =
      localStorage.getItem(
        'homeen-sidebar-collapsed',
      ) === '1'
  }
}

watch(sidebarCollapsed, (value) => {
  if (!isMobile.value) {
    localStorage.setItem(
      'homeen-sidebar-collapsed',
      value ? '1' : '0',
    )
  }
})

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

  void loadPresets()
})

onUnmounted(() => {
  mediaQuery.removeEventListener(
    'change',
    handleViewportChange,
  )

  stopUsageTracking()
  stopGlobalTimer()
})
</script>

<template>
  <div
    class="app-layout"
    :class="{
      'sidebar-collapsed': sidebarCollapsed,
      'is-mobile': isMobile,
    }"
  >
    <Sidebar :collapsed="sidebarCollapsed" />

    <button
      v-if="isMobile && !sidebarCollapsed"
      class="mobile-sidebar-backdrop"
      aria-label="Close navigation"
      @click="sidebarCollapsed = true"
    />

    <section class="workspace">
      <TopBar
        :sidebar-collapsed="sidebarCollapsed"
        @toggle-sidebar="toggleSidebar"
      />

      <div class="page-scroll">
        <RouterView v-slot="{ Component, route }">
          <Transition
            name="page-fade"
            mode="out-in"
          >
            <component
              :is="Component"
              :key="route.path"
            />
          </Transition>
        </RouterView>
      </div>
    </section>
  </div>
</template>
