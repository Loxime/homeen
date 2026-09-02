<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import Sidebar from './Sidebar.vue'
import TopBar from './TopBar.vue'
import { startUsageTracking, stopUsageTracking } from '../composables/useUsageTracking'
import { usePomodoro } from '../composables/usePomodoro'

const { startGlobalTimer, stopGlobalTimer, loadPresets } = usePomodoro()
onMounted(() => {
  startUsageTracking()
  startGlobalTimer()
  void loadPresets()
})

onUnmounted(() => {
  stopUsageTracking()
  stopGlobalTimer()
})
</script>

<template>
  <div class="app-layout">
    <Sidebar />
    <section class="workspace">
      <TopBar />
      <div class="page-scroll">
        <RouterView />
      </div>
    </section>
  </div>
</template>
