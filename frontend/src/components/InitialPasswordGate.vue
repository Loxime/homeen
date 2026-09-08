<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAccess } from '../composables/useAccess'

const {
  state,
  changeTemporaryPassword,
} = useAccess()

const password = ref('')
const confirmation = ref('')
const error = ref('')
const submitting = ref(false)

const passwordsMatch = computed(
  () =>
    password.value
      === confirmation.value,
)

const passwordValid = computed(
  () =>
    password.value.length >= 12
      && password.value.length <= 72,
)

const canSubmit = computed(
  () =>
    passwordValid.value
      && passwordsMatch.value
      && confirmation.value.length > 0
      && !submitting.value,
)

async function submit(): Promise<void> {
  error.value = ''

  if (!passwordsMatch.value) {
    error.value =
      'La confirmation ne correspond pas.'

    return
  }

  if (!passwordValid.value) {
    error.value =
      'Le mot de passe doit contenir entre 12 et 72 caractères.'

    return
  }

  submitting.value = true

  try {
    await changeTemporaryPassword(
      password.value,
      confirmation.value,
    )

    password.value = ''
    confirmation.value = ''
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier le mot de passe.'
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
        Choisissez votre mot de passe
      </h1>

      <p class="muted">
        Votre mot de passe temporaire
        a été utilisé. Définissez maintenant
        votre mot de passe personnel.
      </p>

      <p
        v-if="state.email"
        class="muted"
      >
        {{ state.email }}
      </p>

      <form @submit.prevent="submit">
        <label for="new-password">
          Nouveau mot de passe
        </label>

        <input
          id="new-password"
          v-model="password"
          type="password"
          minlength="12"
          maxlength="72"
          autocomplete="new-password"
          autofocus
          required
        />

        <label for="confirm-password">
          Confirmer le mot de passe
        </label>

        <input
          id="confirm-password"
          v-model="confirmation"
          type="password"
          minlength="12"
          maxlength="72"
          autocomplete="new-password"
          required
        />

        <p class="muted">
          De 12 à 72 caractères.
        </p>

        <p
          v-if="
            confirmation
            && !passwordsMatch
          "
          class="form-error"
        >
          Les mots de passe ne correspondent pas.
        </p>

        <p
          v-if="error"
          class="form-error"
        >
          {{ error }}
        </p>

        <button
          class="primary wide"
          :disabled="!canSubmit"
        >
          {{
            submitting
              ? 'Enregistrement…'
              : 'Enregistrer le mot de passe'
          }}
        </button>
      </form>
    </section>
  </main>
</template>
