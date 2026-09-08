<script setup lang="ts">
import { ref } from 'vue'
import { useAccess } from '../composables/useAccess'

const { loginUser } = useAccess()

const email = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)

async function submit(): Promise<void> {
  error.value = ''
  submitting.value = true

  try {
    await loginUser(
      email.value,
      password.value,
    )

    password.value = ''
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Unable to sign in.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="access-screen">
    <section class="access-card">
      <div class="access-mark">
        H
      </div>

      <p class="eyebrow">
        USER ACCOUNT
      </p>

      <h1>
        Sign in
      </h1>

      <p class="muted">
        Use any email address linked
        to your Homeen account.
      </p>

      <form @submit.prevent="submit">
        <label for="login-email">
          Email
        </label>

        <input
          id="login-email"
          v-model.trim="email"
          type="email"
          autocomplete="username"
          autofocus
          required
        />

        <label for="login-password">
          Password
        </label>

        <input
          id="login-password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          required
        />

        <p
          v-if="error"
          class="form-error"
        >
          {{ error }}
        </p>

        <button
          class="primary wide"
          :disabled="
            submitting
            || !email
            || !password
          "
        >
          {{
            submitting
              ? 'Signing in…'
              : 'Sign in'
          }}
        </button>
      </form>
    </section>
  </main>
</template>
