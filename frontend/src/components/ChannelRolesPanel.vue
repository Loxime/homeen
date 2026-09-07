<script setup lang="ts">
import {
  onMounted,
  ref,
  watch,
} from 'vue'

import {
  api,
} from '../services/api'

type ChannelRole =
  | 'creator'
  | 'admin'
  | 'member'

interface ChannelRoleMember {
  userId: number
  email: string
  role: ChannelRole
  joinedAt: string
}

const props =
  defineProps<{
    channelCode: string
  }>()

const members =
  ref<ChannelRoleMember[]>([])

const loading =
  ref(true)

const updatingUserId =
  ref<number | null>(null)

const error =
  ref('')

const success =
  ref('')

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const response =
      await api<{
        members: ChannelRoleMember[]
      }>(
        `/api/channels/${props.channelCode}/roles`,
      )

    members.value =
      response.members
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les rôles.'
  } finally {
    loading.value = false
  }
}

async function updateRole(
  member: ChannelRoleMember,
  event: Event,
): Promise<void> {
  if (
    member.role === 'creator'
    || updatingUserId.value !== null
  ) {
    return
  }

  const target =
    event.target as HTMLSelectElement

  const role =
    target.value as
      | 'admin'
      | 'member'

  const previous =
    member.role

  member.role =
    role

  updatingUserId.value =
    member.userId

  error.value = ''
  success.value = ''

  try {
    const updated =
      await api<ChannelRoleMember>(
        `/api/channels/${props.channelCode}/members/${member.userId}/role`,
        {
          method: 'PATCH',

          body: JSON.stringify({
            role,
          }),
        },
      )

    member.role =
      updated.role

    success.value =
      `${member.email} est maintenant ${
        updated.role === 'admin'
          ? 'administrateur'
          : 'membre'
      }.`
  } catch (exception) {
    member.role =
      previous

    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier le rôle.'
  } finally {
    updatingUserId.value = null
  }
}

watch(
  () => props.channelCode,
  () => {
    void load()
  },
)

onMounted(
  () => {
    void load()
  },
)
</script>

<template>
  <section class="settings-card channel-role-panel">
    <div class="settings-heading">
      <div>
        <h2>
          Rôles du canal
        </h2>

        <p class="muted">
          Les administrateurs pourront recevoir
          des permissions de gestion déléguées.
        </p>
      </div>
    </div>

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

    <p
      v-if="loading"
      class="muted"
    >
      Chargement des membres…
    </p>

    <div
      v-else
      class="channel-role-list"
    >
      <article
        v-for="member in members"
        :key="member.userId"
        class="channel-role-row"
      >
        <div class="channel-role-identity">
          <strong>
            {{ member.email }}
          </strong>

          <span
            v-if="member.role === 'creator'"
            class="channel-role-badge"
          >
            Créateur
          </span>

          <span
            v-else-if="member.role === 'admin'"
            class="channel-role-badge"
          >
            Administrateur
          </span>
        </div>

        <select
          v-if="member.role !== 'creator'"
          :value="member.role"
          :disabled="
            updatingUserId === member.userId
          "
          @change="
            updateRole(
              member,
              $event,
            )
          "
        >
          <option value="member">
            Membre
          </option>

          <option value="admin">
            Administrateur
          </option>
        </select>
      </article>
    </div>
  </section>
</template>

<style scoped>
.channel-role-panel {
  display: grid;
  gap: 1rem;
}

.channel-role-list {
  display: grid;
  gap: .65rem;
}

.channel-role-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: .75rem 0;
  border-bottom: 1px solid #e8eaed;
}

.channel-role-row:last-child {
  border-bottom: 0;
}

.channel-role-identity {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: .5rem;
  min-width: 0;
}

.channel-role-identity strong {
  overflow: hidden;
  text-overflow: ellipsis;
}

.channel-role-badge {
  padding: .25rem .5rem;
  border-radius: 999px;
  background: #f1f3f4;
  font-size: .7rem;
  font-weight: 700;
}

.channel-role-row select {
  min-width: 160px;
}

@media (max-width: 720px) {
  .channel-role-row {
    align-items: stretch;
    flex-direction: column;
  }

  .channel-role-row select {
    width: 100%;
  }
}
</style>
