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
      'Password confirmation does not match.'

    return
  }

  if (!passwordValid.value) {
    error.value =
      'Password must contain between 12 and 72 characters.'

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
        : 'Unable to update password.'
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
        FIRST LOGIN
      </p>

      <h1>
        Choose your password
      </h1>

      <p class="muted">
        Your temporary password has
        now been consumed.
      </p>

      <p
        v-if="state.email"
        class="muted"
      >
        {{ state.email }}
      </p>

      <form @submit.prevent="submit">
        <label for="new-password">
          New password
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
          Confirm password
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
          12 to 72 characters.
        </p>

        <p
          v-if="
            confirmation
            && !passwordsMatch
          "
          class="form-error"
        >
          Passwords do not match.
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
              ? 'Saving…'
              : 'Set password'
          }}
        </button>
      </form>
    </section>
  </main>
</template>
