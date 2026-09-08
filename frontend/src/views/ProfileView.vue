<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../services/api'
import { useAccess } from '../composables/useAccess'
import {
  useNotificationSound,
} from '../composables/useNotificationSound'

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

const {
  syncEnabled:
    syncNotificationSoundEnabled,
  setEnabled:
    setNotificationSoundEnabled,
} = useNotificationSound()

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
    const loadedProfile =
      await api<Profile>(
        '/api/profile',
      )

    profile.value =
      loadedProfile

    syncNotificationSoundEnabled(
      loadedProfile
        .notificationSoundEnabled,
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger le profil.'
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
      'Adresse de connexion ajoutée.'

    await load()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’ajouter l’adresse e-mail.'
  } finally {
    addingEmail.value = false
  }
}

async function deleteAccount(): Promise<void> {
  error.value = ''
  success.value = ''

  if (!deletePassword.value) {
    error.value =
      'Saisissez votre mot de passe actuel.'

    return
  }

  const confirmed = window.confirm(
    'Supprimer définitivement votre compte et toutes vos données personnelles ? Cette action est irréversible.',
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
        : 'Impossible de supprimer le compte.'

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
      `Retirer ${email.email} de vos adresses de connexion ?`,
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
      'Adresse de connexion supprimée.'

    await load()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de supprimer l’adresse e-mail.'
  }
}

async function changePassword(): Promise<void> {
  error.value = ''
  success.value = ''

  if (
    password.value !== confirmation.value
  ) {
    error.value =
      'Les mots de passe ne correspondent pas.'
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
      'Mot de passe mis à jour.'
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier le mot de passe.'
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

  const next =
    !previous

  profile.value
    .notificationSoundEnabled =
      next

  error.value = ''

  try {
    await setNotificationSoundEnabled(
      next,
    )
  } catch (exception) {
    profile.value
      .notificationSoundEnabled =
        previous

    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier les notifications.'
  }
}

onMounted(() => void load())
</script>

<template>
  <section class="page-stack profile-page">
    <header class="page-heading">
      <div>
        <h1>
          Profil
        </h1>

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
      Chargement du profil…
    </p>

    <template v-else-if="profile">
      <section class="settings-card">
        <div class="settings-heading">
          <div>
            <h2>
              Adresses de connexion
            </h2>

            <p class="muted">
              Vous pouvez utiliser
              n’importe quelle adresse liée
              à votre compte pour vous connecter.
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
                Principale
              </span>
            </div>

            <button
              v-if="!email.isPrimary"
              class="ghost danger-text"
              type="button"
              @click="removeEmail(email)"
            >
              Retirer
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
            placeholder="Ajouter une adresse e-mail"
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
                ? 'Ajout…'
                : 'Ajouter'
            }}
          </button>
        </form>
      </section>

      <section class="settings-card">
        <h2>
          Mot de passe
        </h2>

        <p class="muted">
          La modification du mot de passe
          nécessite votre mot de passe actuel.
        </p>

        <form
          class="profile-password-form"
          @submit.prevent="changePassword"
        >
          <label>
            Mot de passe actuel

            <input
              v-model="currentPassword"
              type="password"
              autocomplete="current-password"
              required
            />
          </label>

          <label>
            Nouveau mot de passe

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
            Confirmer le nouveau mot de passe

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
                ? 'Modification…'
                : 'Modifier le mot de passe'
            }}
          </button>
        </form>
      </section>

      <section class="settings-card">
        <div class="preference-row">
          <div>
            <h2>
              Sons de notification
            </h2>

            <p class="muted">
              Les notifications visuelles
              restent toujours actives.
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
                ? 'Activés'
                : 'Désactivés'
            }}
          </button>
        </div>
      </section>

      <section class="settings-card danger-zone">
        <h2>
          Supprimer le compte
        </h2>

        <p class="muted">
          Supprime définitivement votre compte
          et toutes vos données personnelles.
          Cette action est irréversible.
        </p>

        <label class="delete-account-password">
          Mot de passe actuel

          <input
            v-model="deletePassword"
            type="password"
            autocomplete="current-password"
            placeholder="Confirmez votre mot de passe"
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
              ? 'Suppression…'
              : 'Supprimer mon compte'
          }}
        </button>
      </section>
    </template>
  </section>
</template>
