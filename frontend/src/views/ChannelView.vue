<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
  watch,
} from 'vue'

import {
  useRoute,
  useRouter,
} from 'vue-router'

import AppIcon from '../components/AppIcon.vue'
import ChannelNotesPanel from '../components/ChannelNotesPanel.vue'

import {
  ApiError,
  api,
} from '../services/api'

interface Channel {
  id: number
  code: string
  formattedCode: string
  name: string
  description: string
  profileImageUrl: string | null
  creatorUserId: number
  memberCount: number
  isCreator: boolean
  createdAt: string
}

interface ChannelMember {
  userId: number
  email: string
  isCreator: boolean
  joinedAt: string
}

interface MembersResponse {
  members: ChannelMember[]
}

type ChannelTab =
  | 'notes'
  | 'tasks'
  | 'members'
  | 'chat'
  | 'settings'

const route = useRoute()
const router = useRouter()

const channel = ref<Channel | null>(null)

const loading = ref(true)
const error = ref('')

const activeTab =
  ref<ChannelTab>('notes')

const members =
  ref<ChannelMember[]>([])

const membersLoading = ref(false)
const membersLoaded = ref(false)

const inviteEmail = ref('')
const inviting = ref(false)
const memberError = ref('')
const memberSuccess = ref('')

const removingMemberId =
  ref<number | null>(null)

const code = computed(
  () =>
    String(
      route.params.code ?? '',
    ),
)

function formatCode(
  value: string,
): string {
  if (
    !/^\d{9}$/.test(value)
  ) {
    return value
  }

  return [
    value.slice(0, 3),
    value.slice(3, 6),
    value.slice(6, 9),
  ].join('-')
}

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  channel.value = null

  try {
    channel.value =
      await api<Channel>(
        `/api/channels/${code.value}`,
      )
  } catch (exception) {
    if (
      exception instanceof ApiError
      && exception.code
        === 'CHANNEL_FORBIDDEN'
    ) {
      await router.replace({
        path: '/notes',

        query: {
          channelDenied:
            formatCode(
              code.value,
            ),
        },
      })

      return
    }

    if (
      exception instanceof ApiError
      && exception.code
        === 'CHANNEL_NOT_FOUND'
    ) {
      error.value =
        'Ce canal n’existe pas ou a été fermé.'

      return
    }

    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger le canal.'
  } finally {
    loading.value = false
  }
}

async function loadMembers(
  force = false,
): Promise<void> {
  if (!channel.value) {
    return
  }

  if (
    membersLoaded.value
    && !force
  ) {
    return
  }

  membersLoading.value = true
  memberError.value = ''

  try {
    const response =
      await api<MembersResponse>(
        `/api/channels/${channel.value.code}/members`,
      )

    members.value =
      response.members

    membersLoaded.value = true
  } catch (exception) {
    memberError.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les membres.'
  } finally {
    membersLoading.value = false
  }
}

async function inviteMember(): Promise<void> {
  if (
    !channel.value
    || !inviteEmail.value.trim()
  ) {
    return
  }

  memberError.value = ''
  memberSuccess.value = ''
  inviting.value = true

  try {
    await api(
      `/api/channels/${channel.value.code}/invitations`,
      {
        method: 'POST',

        body: JSON.stringify({
          email:
            inviteEmail.value.trim(),
        }),
      },
    )

    memberSuccess.value =
      `Invitation envoyée à ${inviteEmail.value.trim()}.`

    inviteEmail.value = ''
  } catch (exception) {
    memberError.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’envoyer l’invitation.'
  } finally {
    inviting.value = false
  }
}

async function removeMember(
  member: ChannelMember,
): Promise<void> {
  if (
    !channel.value
    || member.isCreator
    || !channel.value.isCreator
  ) {
    return
  }

  const confirmed =
    window.confirm(
      `Retirer ${member.email} du canal ?`,
    )

  if (!confirmed) {
    return
  }

  memberError.value = ''
  memberSuccess.value = ''

  removingMemberId.value =
    member.userId

  try {
    await api(
      `/api/channels/${channel.value.code}/members/${member.userId}`,
      {
        method: 'DELETE',
      },
    )

    members.value =
      members.value.filter(
        candidate =>
          candidate.userId
          !== member.userId,
      )

    channel.value = {
      ...channel.value,

      memberCount:
        Math.max(
          1,
          channel.value.memberCount - 1,
        ),
    }

    memberSuccess.value =
      `${member.email} a été retiré du canal.`
  } catch (exception) {
    memberError.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de retirer ce membre.'
  } finally {
    removingMemberId.value = null
  }
}

function selectTab(
  tab: ChannelTab,
): void {
  activeTab.value = tab

  memberError.value = ''
  memberSuccess.value = ''

  if (tab === 'members') {
    void loadMembers()
  }
}

watch(
  () => route.params.code,
  () => {
    members.value = []
    membersLoaded.value = false
    activeTab.value = 'notes'

    void load()
  },
)

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="page-stack channel-page">
    <p
      v-if="loading"
      class="muted"
    >
      Chargement du canal…
    </p>

    <div
      v-else-if="error"
      class="settings-card"
    >
      <h1>
        Canal indisponible
      </h1>

      <p class="form-error">
        {{ error }}
      </p>

      <RouterLink
        class="primary channel-back-link"
        to="/channels"
      >
        Retour aux canaux
      </RouterLink>
    </div>

    <template v-else-if="channel">
      <header class="channel-header">
        <div class="channel-avatar">
          <img
            v-if="
              channel.profileImageUrl
            "
            :src="
              channel.profileImageUrl
            "
            alt=""
          />

          <AppIcon
            v-else
            name="users"
            :size="26"
          />
        </div>

        <div class="channel-header-copy">
          <p class="eyebrow">
            CANAL
          </p>

          <h1>
            {{ channel.name }}
          </h1>

          <p class="muted">
            {{
              channel.formattedCode
            }}
            ·
            {{
              channel.memberCount
            }}
            membre{{
              channel.memberCount > 1
                ? 's'
                : ''
            }}

            <template
              v-if="channel.isCreator"
            >
              · Vous êtes le créateur
            </template>
          </p>
        </div>
      </header>

      <p
        v-if="channel.description"
        class="channel-lead"
      >
        {{ channel.description }}
      </p>

      <nav
        class="channel-tabs"
        aria-label="Navigation du canal"
      >
        <button
          type="button"
          :class="{
            active:
              activeTab === 'notes',
          }"
          @click="
            selectTab('notes')
          "
        >
          Notes
        </button>

        <button
          type="button"
          :class="{
            active:
              activeTab === 'tasks',
          }"
          @click="
            selectTab('tasks')
          "
        >
          Tâches
        </button>

        <button
          type="button"
          :class="{
            active:
              activeTab === 'members',
          }"
          @click="
            selectTab('members')
          "
        >
          Membres

          <span
            class="channel-tab-count"
          >
            {{ channel.memberCount }}
          </span>
        </button>

        <button
          type="button"
          :class="{
            active:
              activeTab === 'chat',
          }"
          @click="
            selectTab('chat')
          "
        >
          Chat
        </button>

        <button
          type="button"
          :class="{
            active:
              activeTab === 'settings',
          }"
          @click="
            selectTab('settings')
          "
        >
          Paramètres
        </button>
      </nav>

      <section
        class="settings-card channel-content"
      >
        <template
          v-if="
            activeTab === 'notes'
          "
        >
          <ChannelNotesPanel
            :channel-code="channel.code"
          />
        </template>

        <template
          v-else-if="
            activeTab === 'tasks'
          "
        >
          <h2>
            Tâches
          </h2>

          <p class="muted">
            Les tâches du canal seront
            rattachées aux notes
            collaboratives.
          </p>
        </template>

        <template
          v-else-if="
            activeTab === 'members'
          "
        >
          <div class="channel-members-heading">
            <div>
              <h2>
                Membres
              </h2>

              <p class="muted">
                {{
                  channel.memberCount
                }}
                membre{{
                  channel.memberCount > 1
                    ? 's'
                    : ''
                }}
              </p>
            </div>
          </div>

          <p
            v-if="memberError"
            class="form-error"
          >
            {{ memberError }}
          </p>

          <p
            v-if="memberSuccess"
            class="form-success"
          >
            {{ memberSuccess }}
          </p>

          <form
            v-if="channel.isCreator"
            class="channel-invite-form"
            @submit.prevent="
              inviteMember
            "
          >
            <label class="channel-invite-field">
              <span>
                Inviter un utilisateur
              </span>

              <input
                v-model="
                  inviteEmail
                "
                type="email"
                autocomplete="email"
                placeholder="utilisateur@exemple.fr"
                required
              />
            </label>

            <button
              class="primary"
              type="submit"
              :disabled="
                inviting
                || !inviteEmail.trim()
              "
            >
              {{
                inviting
                  ? 'Invitation…'
                  : 'Inviter'
              }}
            </button>
          </form>

          <p
            v-if="membersLoading"
            class="muted"
          >
            Chargement des membres…
          </p>

          <div
            v-else
            class="channel-member-list"
          >
            <article
              v-for="member in members"
              :key="member.userId"
              class="channel-member-row"
            >
              <div class="channel-member-identity">
                <div class="channel-member-avatar">
                  <AppIcon
                    name="user"
                    :size="18"
                  />
                </div>

                <div>
                  <div class="channel-member-name">
                    <strong>
                      {{ member.email }}
                    </strong>

                    <span
                      v-if="
                        member.isCreator
                      "
                      class="profile-badge"
                    >
                      Créateur
                    </span>
                  </div>

                  <span
                    class="channel-member-date"
                  >
                    Membre depuis
                    {{
                      new Date(
                        member.joinedAt,
                      ).toLocaleDateString(
                        'fr-FR',
                      )
                    }}
                  </span>
                </div>
              </div>

              <button
                v-if="
                  channel.isCreator
                  && !member.isCreator
                "
                class="ghost danger-text"
                type="button"
                :disabled="
                  removingMemberId
                  === member.userId
                "
                @click="
                  removeMember(member)
                "
              >
                {{
                  removingMemberId
                    === member.userId
                    ? 'Retrait…'
                    : 'Retirer'
                }}
              </button>
            </article>
          </div>
        </template>

        <template
          v-else-if="
            activeTab === 'chat'
          "
        >
          <h2>
            Chat
          </h2>

          <p class="muted">
            Le chat sera ajouté après les
            notes partagées et avant le
            temps réel.
          </p>
        </template>

        <template v-else>
          <h2>
            Paramètres
          </h2>

          <p class="muted">
            Nom, description, image,
            export et fermeture du canal
            seront disponibles ici.
          </p>
        </template>
      </section>
    </template>
  </section>
</template>
