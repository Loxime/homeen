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
        : 'Impossible de se connecter.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="access-screen">
    <section class="access-card">
      <img
        class="access-logo"
        src="/favicon.svg"
        alt=""
      />

      <h1>
        Connexion
      </h1>

      <p class="muted">
        Utilisez une adresse e-mail
        associée à votre compte.
      </p>

      <form @submit.prevent="submit">
        <label for="login-email">
          Adresse e-mail
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
          Mot de passe
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
              ? 'Connexion…'
              : 'Se connecter'
          }}
        </button>
      </form>
    </section>
  </main>
</template>
