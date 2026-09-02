<script setup lang="ts">
import { onMounted } from 'vue'
import AccessGate from './components/AccessGate.vue'
import AppShell from './components/AppShell.vue'
import { useAccess } from './composables/useAccess'

const { state, initialize } = useAccess()
onMounted(() => void initialize())
</script>

<template>
  <main v-if="state.loading" class="center-screen">
    <div class="brand-loader">H</div>
    <p>Opening Homeen…</p>
  </main>
  <AccessGate v-else-if="!state.authenticated" />
  <AppShell v-else />
</template>
