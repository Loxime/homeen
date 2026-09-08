<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
  watch,
  onUnmounted,
} from 'vue'

import {
  useRoute,
  useRouter,
} from 'vue-router'

import AppIcon from '../components/AppIcon.vue'
import ChannelNotesPanel from '../components/ChannelNotesPanel.vue'
import ChannelTasksPanel from '../components/ChannelTasksPanel.vue'
import ChannelChatPanel from '../components/ChannelChatPanel.vue'
import ChannelSettingsPanel from '../components/ChannelSettingsPanel.vue'

import {
  ApiError,
  api,
} from '../services/api'

import {
  useChannelUnreadMessages,
} from '../composables/useChannelUnreadMessages'

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

type ChannelMemberRole =
  | 'creator'
  | 'admin'
  | 'member'

interface ChannelMember {
  userId: number
  email: string
  isCreator: boolean
  role: ChannelMemberRole
  joinedAt: string
}

interface RoleMember {
  userId: number
  email: string
  role: ChannelMemberRole
  joinedAt: string
}

interface MembersResponse {
  members: RoleMember[]
}

interface ChannelPermissions {
  currentUserId: number
  role: ChannelMemberRole
  isCreator: boolean
  isAdmin: boolean
  canManageMembers: boolean
}

type ChannelTab =
  | 'notes'
  | 'tasks'
  | 'members'
  | 'chat'
  | 'settings'

const route = useRoute()
const router = useRouter()

const {
  lastStructureEvent:
    channelStructureEvent,
  structureEventVersion:
    channelStructureVersion,
  start:
    startChannelStructureRealtime,
  stop:
    stopChannelStructureRealtime,
} = useChannelUnreadMessages()

const channel = ref<Channel | null>(null)

const channelPermissions =
  ref<ChannelPermissions | null>(null)

const canManageMembers =
  computed(
    () =>
      channelPermissions.value?.canManageMembers
      ?? channel.value?.isCreator
      ?? false,
  )

const currentUserId =
  computed(
    () =>
      channelPermissions.value?.currentUserId
      ?? null,
  )

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

    await loadPermissions()
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

async function loadPermissions(): Promise<void> {
  if (!channel.value) {
    channelPermissions.value = null

    return
  }

  try {
    channelPermissions.value =
      await api<ChannelPermissions>(
        `/api/channels/${channel.value.code}/management/permissions`,
      )
  } catch {
    channelPermissions.value = null
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
        `/api/channels/${channel.value.code}/roles`,
      )

    members.value =
      response.members.map(
        member => ({
          ...member,

          isCreator:
            member.role === 'creator',
        }),
      )

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
    || !canManageMembers.value
    || !inviteEmail.value.trim()
  ) {
    return
  }

  memberError.value = ''
  memberSuccess.value = ''
  inviting.value = true

  try {
    await api(
      `/api/channels/${channel.value.code}/management/invitations`,
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

function canRemoveMember(
  member: ChannelMember,
): boolean {
  if (
    !channel.value
    || !canManageMembers.value
    || member.isCreator
    || member.userId === currentUserId.value
  ) {
    return false
  }

  /*
   * Le créateur peut retirer membres et admins.
   * Un admin ne peut retirer qu'un membre simple.
   */
  if (channel.value.isCreator) {
    return true
  }

  return member.role === 'member'
}

async function removeMember(
  member: ChannelMember,
): Promise<void> {
  if (
    !channel.value
    || !canRemoveMember(
      member,
    )
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
      `/api/channels/${channel.value.code}/management/members/${member.userId}`,
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

function applyChannelUpdate(
  updatedChannel: Channel,
): void {
  channel.value =
    updatedChannel

  membersLoaded.value = false

  void loadPermissions()
}

function handleChannelClosed(): void {
  void router.replace(
    '/channels',
  )
}

function handleChannelLeft(): void {
  void router.replace(
    '/channels',
  )
}

async function refreshChannelStructure(): Promise<void> {
  try {
    channel.value =
      await api<Channel>(
        `/api/channels/${code.value}`,
      )

    await loadPermissions()

    if (
      membersLoaded.value
      || activeTab.value === 'members'
    ) {
      membersLoaded.value = false

      await loadMembers()
    }
  } catch (exception) {
    if (
      exception instanceof ApiError
      && (
        exception.code === 'CHANNEL_FORBIDDEN'
        || exception.code === 'CHANNEL_NOT_FOUND'
      )
    ) {
      await router.replace(
        '/channels',
      )

      return
    }

    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de rafraîchir le canal.'
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
  channelStructureVersion,
  () => {
    const event =
      channelStructureEvent.value

    if (
      !event
      || event.channelCode !== code.value
    ) {
      return
    }

    /*
     * Closing a channel invalidates it for
     * every open tab immediately.
     */
    if (
      event.event === 'channel-closed'
    ) {
      void router.replace(
        '/channels',
      )

      return
    }

    /*
     * A user who leaves or is removed must
     * lose the open channel immediately,
     * even if their old Mercure cookie still
     * exists for a short time.
     */
    if (
      (
        event.event === 'member-left'
        || event.event === 'member-removed'
      )
      && event.userId
        === currentUserId.value
    ) {
      void router.replace(
        '/channels',
      )

      return
    }

    /*
     * Membership, roles, ownership and
     * settings are reloaded from the API.
     * PostgreSQL remains authoritative.
     */
    void refreshChannelStructure()
  },
)

watch(
  () => route.params.code,
  () => {
    members.value = []
    membersLoaded.value = false
    channelPermissions.value = null
    activeTab.value = 'notes'

    void load()
  },
)

onMounted(() => {
  startChannelStructureRealtime()

  void load()
})

onUnmounted(() => {
  stopChannelStructureRealtime()
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

            <template
              v-else-if="
                channelPermissions?.isAdmin
              "
            >
              · Administrateur
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
          <ChannelTasksPanel
            :channel-code="channel.code"
          />
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
            v-if="canManageMembers"
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
                v-if="canRemoveMember(member)"
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
          <ChannelChatPanel
            :channel-code="channel.code"
          />
        </template>

        <ChannelSettingsPanel
        v-else
        :channel="channel"
        @updated="applyChannelUpdate"
        @closed="handleChannelClosed"
        @left="handleChannelLeft"
      />
      </section>
    </template>
  </section>
</template>
