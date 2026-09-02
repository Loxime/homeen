<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAccess } from '../composables/useAccess'

const route = useRoute()
const router = useRouter()
const { logout } = useAccess()
const query = ref(typeof route.query.q === 'string' ? route.query.q : '')

watch(() => route.query.q, (value) => {
  query.value = typeof value === 'string' ? value : ''
})

async function search(): Promise<void> {
  await router.push({ path: '/notes', query: query.value.trim() ? { q: query.value.trim() } : {} })
}

async function signOut(): Promise<void> {
  await logout()
  await router.push('/')
}
</script>

<template>
  <header class="topbar">
    <form class="search-box" @submit.prevent="search">
      <span>⌕</span>
      <input v-model="query" type="search" placeholder="Search notes, tasks or labels…" />
      <kbd>Enter</kbd>
    </form>
    <button class="ghost" @click="signOut">Lock</button>
  </header>
</template>
