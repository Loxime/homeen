<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../services/api'
import { useAccess } from '../composables/useAccess'

interface ProfileEmail {
  id: number
  email: string
  isPrimary: boolean
}

interface Profile {
  id: number
  primaryEmail: string
  notificationSoundEnabled: boolean
  emails: ProfileEmail[]
}

const { state } = useAccess()

const profile = ref<Profile | null>(null)
const loading = ref(true)
const error = ref('')
const success = ref('')

const deletePassword = ref('')
const deletingAccount = ref(false)

const newEmail = ref('')
const addingEmail = ref(false)

const currentPassword = ref('')
const password = ref('')
const confirmation = ref('')
const changingPassword = ref(false)

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    profile.value =
      await api<Profile>(
        '/api/profile',
      )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Unable to load profile.'
  } finally {
    loading.value = false
  }
}

async function addEmail(): Promise<void> {
  error.value = ''
  success.value = ''
  addingEmail.value = true

  try {
    await api(
      '/api/profile/emails',
      {
        method: 'POST',
        body: JSON.stringify({
          email: newEmail.value,
        }),
      },
    )

    newEmail.value = ''
    success.value =
      'Login email added.'

    await load()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Unable to add email.'
  } finally {
    addingEmail.value = false
  }
}

async function deleteAccount(): Promise<void> {
  error.value = ''
  success.value = ''

  if (!deletePassword.value) {
    error.value =
      'Enter your current password.'

    return
  }

  const confirmed = window.confirm(
    'Delete your Homeen account and all personal data? This action cannot be undone.',
  )

  if (!confirmed) {
    return
  }

  deletingAccount.value = true

  try {
    await api(
      '/api/profile',
      {
        method: 'DELETE',
        body: JSON.stringify({
          password:
            deletePassword.value,
        }),
      },
    )

    /*
     * The backend invalidated the session.
     * A complete reload guarantees that all
     * in-memory personal state is discarded.
     */
    window.location.assign('/')
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Unable to delete account.'

    deletingAccount.value = false
  }
}

async function removeEmail(
  email: ProfileEmail,
): Promise<void> {
  if (email.isPrimary) {
    return
  }

  if (
    !window.confirm(
      `Remove ${email.email} from your login addresses?`,
    )
  ) {
    return
  }

  error.value = ''
  success.value = ''

  try {
    await api(
      `/api/profile/emails/${email.id}`,
      {
        method: 'DELETE',
      },
    )

    success.value =
      'Login email removed.'

    await load()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Unable to remove email.'
  }
}

async function changePassword(): Promise<void> {
  error.value = ''
  success.value = ''

  if (
    password.value !== confirmation.value
  ) {
    error.value =
      'Passwords do not match.'
    return
  }

  changingPassword.value = true

  try {
    await api(
      '/api/profile/password',
      {
        method: 'POST',
        body: JSON.stringify({
          currentPassword:
            currentPassword.value,
          password: password.value,
          confirmation:
            confirmation.value,
        }),
      },
    )

    currentPassword.value = ''
    password.value = ''
    confirmation.value = ''

    success.value =
      'Password updated.'
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Unable to change password.'
  } finally {
    changingPassword.value = false
  }
}

async function toggleSound(): Promise<void> {
  if (!profile.value) {
    return
  }

  const previous =
    profile.value
      .notificationSoundEnabled

  const next = !previous

  profile.value
    .notificationSoundEnabled = next

  try {
    await api(
      '/api/profile/notifications',
      {
        method: 'PATCH',
        body: JSON.stringify({
          soundEnabled: next,
        }),
      },
    )
  } catch (exception) {
    profile.value
      .notificationSoundEnabled =
        previous

    error.value =
      exception instanceof Error
        ? exception.message
        : 'Unable to update notifications.'
  }
}

onMounted(() => void load())
</script>

<template>
  <section class="page-stack profile-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">
          ACCOUNT
        </p>
        <h1>Profile</h1>
        <p class="muted">
          {{ state.email }}
        </p>
      </div>
    </header>

    <p
      v-if="error"
      class="form-error"
    >
      {{ error }}
    </p>

    <p
      v-if="success"
      class="form-success"
    >
      {{ success }}
    </p>

    <p v-if="loading">
      Loading profile…
    </p>

    <template v-else-if="profile">
      <section class="settings-card">
        <div class="settings-heading">
          <div>
            <h2>
              Login addresses
            </h2>
            <p class="muted">
              You can use any linked
              email address to sign in.
            </p>
          </div>
        </div>

        <div class="email-list">
          <div
            v-for="email in profile.emails"
            :key="email.id"
            class="email-row"
          >
            <div>
              <strong>
                {{ email.email }}
              </strong>

              <span
                v-if="email.isPrimary"
                class="profile-badge"
              >
                Primary
              </span>
            </div>

            <button
              v-if="!email.isPrimary"
              class="ghost danger-text"
              type="button"
              @click="removeEmail(email)"
            >
              Remove
            </button>
          </div>
        </div>

        <form
          class="profile-inline-form"
          @submit.prevent="addEmail"
        >
          <input
            v-model.trim="newEmail"
            type="email"
            autocomplete="email"
            placeholder="Add another email"
            required
          />

          <button
            class="primary"
            :disabled="
              addingEmail
              || !newEmail
            "
          >
            {{
              addingEmail
                ? 'Adding…'
                : 'Add'
            }}
          </button>
        </form>
      </section>

      <section class="settings-card">
        <h2>
          Password
        </h2>

        <p class="muted">
          Changing your password here
          requires your current password.
        </p>

        <form
          class="profile-password-form"
          @submit.prevent="changePassword"
        >
          <label>
            Current password
            <input
              v-model="currentPassword"
              type="password"
              autocomplete="current-password"
              required
            />
          </label>

          <label>
            New password
            <input
              v-model="password"
              type="password"
              minlength="12"
              maxlength="72"
              autocomplete="new-password"
              required
            />
          </label>

          <label>
            Confirm new password
            <input
              v-model="confirmation"
              type="password"
              minlength="12"
              maxlength="72"
              autocomplete="new-password"
              required
            />
          </label>

          <button
            class="primary"
            :disabled="
              changingPassword
              || !currentPassword
              || !password
              || !confirmation
            "
          >
            {{
              changingPassword
                ? 'Updating…'
                : 'Change password'
            }}
          </button>
        </form>
      </section>

      <section class="settings-card">
        <div class="preference-row">
          <div>
            <h2>
              Notification sounds
            </h2>

            <p class="muted">
              Visual notifications
              will always remain enabled.
            </p>
          </div>

          <button
            type="button"
            class="toggle-button"
            :class="{
              active:
                profile
                  .notificationSoundEnabled,
            }"
            :aria-pressed="
              profile
                .notificationSoundEnabled
            "
            @click="toggleSound"
          >
            {{
              profile
                .notificationSoundEnabled
                ? 'On'
                : 'Off'
            }}
          </button>
        </div>
      </section>

      <section class="settings-card danger-zone">
        <h2>
          Delete account
        </h2>

        <p class="muted">
          Account deletion will become
          available once Homeen personal
          data ownership has been migrated
          to individual users.
        </p>

      <section class="settings-card danger-zone">
        <h2>
          Delete account
        </h2>

        <p class="muted">
          Permanently delete your Homeen
          account and all personal data.
          This action cannot be undone.
        </p>

        <label class="delete-account-password">
          Current password

          <input
            v-model="deletePassword"
            type="password"
            autocomplete="current-password"
            placeholder="Confirm your password"
          />
        </label>

        <button
          class="danger"
          type="button"
          :disabled="
            deletingAccount
            || !deletePassword
          "
          @click="deleteAccount"
        >
          {{
            deletingAccount
              ? 'Deleting…'
              : 'Delete my account'
          }}
        </button>
      </section>

      </section>
    </template>
  </section>
</template>
