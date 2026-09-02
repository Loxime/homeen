<script setup lang="ts">
import { ref } from 'vue'
import { useAccess } from '../composables/useAccess'

const { login } = useAccess()
const accessKey = ref('')
const error = ref('')
const submitting = ref(false)

async function submit(): Promise<void> {
  error.value = ''
  submitting.value = true
  try {
    await login(accessKey.value)
    accessKey.value = ''
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Access denied.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="access-screen">
    <section class="access-card">
      <div class="access-mark">H</div>
      <p class="eyebrow">PRIVATE WORKSPACE</p>
      <h1>Homeen</h1>
      <p class="muted">Enter the access key configured on this environment.</p>
      <form @submit.prevent="submit">
        <label for="access-key">Access key</label>
        <input id="access-key" v-model="accessKey" type="password" autocomplete="current-password" autofocus required />
        <p v-if="error" class="form-error">{{ error }}</p>
        <button class="primary wide" :disabled="submitting || !accessKey">{{ submitting ? 'Checking…' : 'Enter Homeen' }}</button>
      </form>
    </section>
  </main>
</template>
