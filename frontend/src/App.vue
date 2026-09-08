<script setup lang="ts">
import { onMounted } from 'vue'

import AccessGate from './components/AccessGate.vue'
import AppShell from './components/AppShell.vue'
import InitialPasswordGate from './components/InitialPasswordGate.vue'
import UserLoginGate from './components/UserLoginGate.vue'
import { useAccess } from './composables/useAccess'

const {
  state,
  initialize,
} = useAccess()

onMounted(
  () => void initialize(),
)
</script>

<template>
  <main
    v-if="state.loading"
    class="center-screen"
  >
    <div class="brand-loader">
      H
    </div>

    <p>
      Opening Homeen…
    </p>
  </main>

  <AccessGate
    v-else-if="
      !state.accessGranted
    "
  />

  <UserLoginGate
    v-else-if="
      !state.userAuthenticated
    "
  />

  <InitialPasswordGate
    v-else-if="
      state.mustChangePassword
    "
  />

  <AppShell
    v-else-if="
      state.authenticated
    "
  />
</template>
