<script setup lang="ts">
import {
  onMounted,
  ref,
} from 'vue'

import Swal from 'sweetalert2'

import AppIcon from '../components/AppIcon.vue'
import {
  ApiError,
  api,
} from '../services/api'

import type {
  Project,
  ProjectRole,
} from '../types/domain'

interface ProjectInvitation {
  id: number
  projectId: number
  name: string
  description: string
  color: string
  invitedByEmail: string | null
  createdAt: string
}

interface ProjectMember {
  userId: number
  email: string
  role: ProjectRole
  joinedAt: string
}

interface ProjectInvitee {
  email: string
}

interface AccessStatus {
  email: string | null
}

const projects =
  ref<Project[]>([])

const invitations =
  ref<ProjectInvitation[]>([])

const currentEmail =
  ref('')

const projectMembers =
  ref<
    Record<number, ProjectMember[]>
  >({})

const memberActionBusy =
  ref<string | null>(null)

const loading = ref(true)
const error = ref('')

const name = ref('')
const description = ref('')
const color = ref('#1A73E8')
const creating = ref(false)

const inviteEmails =
  ref<Record<number, string>>({})

const confirmedInvitees =
  ref<
    Record<
      number,
      ProjectInvitee | undefined
    >
  >({})

const lookupBusyProjectId =
  ref<number | null>(null)

const busyProjectId =
  ref<number | null>(null)

const busyInvitationId =
  ref<number | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const [
      projectResponse,
      invitationResponse,
      accessResponse,
    ] = await Promise.all([
      api<{
        projects: Project[]
      }>('/api/projects'),

      api<{
        invitations:
          ProjectInvitation[]
      }>(
        '/api/project-invitations',
      ),

      api<AccessStatus>(
        '/api/access/status',
      ),
    ])

    currentEmail.value =
      accessResponse.email?.trim()
      ?? ''

    projects.value =
      projectResponse.projects

    invitations.value =
      invitationResponse.invitations
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les projets.'
  } finally {
    loading.value = false
  }
}

async function createProject():
Promise<void> {
  const projectName =
    name.value.trim()

  if (
    projectName === ''
    || creating.value
  ) {
    return
  }

  creating.value = true
  error.value = ''

  try {
    await api<Project>(
      '/api/projects',
      {
        method: 'POST',

        body: JSON.stringify({
          name: projectName,
          description:
            description.value,
          color: color.value,
        }),
      },
    )

    name.value = ''
    description.value = ''
    color.value = '#1A73E8'

    await load()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de créer le projet.'
  } finally {
    creating.value = false
  }
}

function invitationErrorMessage(
  exception: unknown,
  fallback: string,
): string {
  if (!(exception instanceof ApiError)) {
    return fallback
  }

  return {
    PROJECT_INVALID_INPUT:
      'Saisissez une adresse email valide.',

    PROJECT_USER_NOT_FOUND:
      'Aucun compte Harpocrate ne correspond à cette adresse.',

    PROJECT_ALREADY_MEMBER:
      'Cet utilisateur est déjà membre du projet.',

    PROJECT_ALREADY_INVITED:
      'Une invitation est déjà en attente pour cet utilisateur.',

    PROJECT_CANNOT_INVITE_SELF:
      'Vous ne pouvez pas vous inviter vous-même.',
  }[exception.code ?? '']
    ?? exception.message
}

function clearConfirmedInvitee(
  projectId: number,
): void {
  delete confirmedInvitees.value[
    projectId
  ]
}

async function lookupInvitee(
  project: Project,
): Promise<void> {
  const email =
    (
      inviteEmails.value[
        project.id
      ] ?? ''
    ).trim()

  if (
    email === ''
    || lookupBusyProjectId.value
      !== null
    || busyProjectId.value
      !== null
  ) {
    return
  }

  clearConfirmedInvitee(
    project.id,
  )

  lookupBusyProjectId.value =
    project.id

  error.value = ''

  try {
    const invitee =
      await api<ProjectInvitee>(
        `/api/projects/${project.id}/invitees/lookup`,
        {
          method: 'POST',

          body: JSON.stringify({
            email,
          }),
        },
      )

    /*
     * Le champ peut avoir changé pendant
     * la requête. Ne jamais confirmer une
     * ancienne valeur dans ce cas.
     */
    if (
      (
        inviteEmails.value[
          project.id
        ] ?? ''
      ).trim() !== email
    ) {
      return
    }

    confirmedInvitees.value[
      project.id
    ] = invitee

    /*
     * Utilise l'adresse primaire canonique
     * retournée par Harpocrate.
     */
    inviteEmails.value[
      project.id
    ] = invitee.email
  } catch (exception) {
    clearConfirmedInvitee(
      project.id,
    )

    error.value =
      invitationErrorMessage(
        exception,
        'Impossible de vérifier ce compte.',
      )
  } finally {
    lookupBusyProjectId.value = null
  }
}

async function invite(
  project: Project,
): Promise<void> {
  const invitee =
    confirmedInvitees.value[
      project.id
    ]

  const email =
    (
      inviteEmails.value[
        project.id
      ] ?? ''
    ).trim()

  if (
    invitee === undefined
    || email === ''
    || email.toLowerCase()
      !== invitee.email
        .toLowerCase()
    || busyProjectId.value !== null
    || lookupBusyProjectId.value
      !== null
  ) {
    return
  }

  busyProjectId.value =
    project.id

  error.value = ''

  try {
    await api(
      `/api/projects/${project.id}/invitations`,
      {
        method: 'POST',

        body: JSON.stringify({
          email: invitee.email,
        }),
      },
    )

    inviteEmails.value[
      project.id
    ] = ''

    clearConfirmedInvitee(
      project.id,
    )
  } catch (exception) {
    error.value =
      invitationErrorMessage(
        exception,
        'Impossible d’envoyer l’invitation.',
      )
  } finally {
    busyProjectId.value = null
  }
}

async function acceptInvitation(
  invitation: ProjectInvitation,
): Promise<void> {
  if (
    busyInvitationId.value !== null
  ) {
    return
  }

  busyInvitationId.value =
    invitation.id

  error.value = ''

  try {
    await api(
      `/api/project-invitations/${invitation.id}/accept`,
      {
        method: 'POST',
      },
    )

    await load()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’accepter l’invitation.'
  } finally {
    busyInvitationId.value = null
  }
}

async function rejectInvitation(
  invitation: ProjectInvitation,
): Promise<void> {
  if (
    busyInvitationId.value !== null
  ) {
    return
  }

  busyInvitationId.value =
    invitation.id

  error.value = ''

  try {
    await api(
      `/api/project-invitations/${invitation.id}`,
      {
        method: 'DELETE',
      },
    )

    await load()
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de refuser l’invitation.'
  } finally {
    busyInvitationId.value = null
  }
}

function membersFor(
  projectId: number,
): ProjectMember[] {
  return projectMembers.value[
    projectId
  ] ?? []
}

function sameEmail(
  first: string,
  second: string,
): boolean {
  return (
    first.trim().toLowerCase()
    === second.trim().toLowerCase()
  )
}

function isCurrentMember(
  member: ProjectMember,
): boolean {
  return (
    currentEmail.value !== ''
    && sameEmail(
      member.email,
      currentEmail.value,
    )
  )
}

function memberBusyKey(
  project: Project,
  member: ProjectMember,
): string {
  return `${project.id}:${member.userId}`
}

function canChangeRole(
  project: Project,
  member: ProjectMember,
): boolean {
  return (
    project.role === 'owner'
    && member.role !== 'owner'
    && !isCurrentMember(member)
  )
}

function canRemoveMember(
  project: Project,
  member: ProjectMember,
): boolean {
  if (
    member.role === 'owner'
    || isCurrentMember(member)
  ) {
    return false
  }

  if (project.role === 'owner') {
    return true
  }

  return (
    project.role === 'admin'
    && member.role === 'member'
  )
}

async function loadMembers(
  projectId: number,
): Promise<boolean> {
  error.value = ''

  try {
    const response =
      await api<{
        members: ProjectMember[]
      }>(
        `/api/projects/${projectId}/members`,
      )

    projectMembers.value = {
      ...projectMembers.value,

      [projectId]:
        response.members,
    }

    return true
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de charger les membres.'

    return false
  }
}

function renderMembersModal(
  project: Project,
  container: HTMLElement,
): void {
  container.replaceChildren()

  container.style.display = 'grid'
  container.style.gap = '0.75rem'
  container.style.textAlign = 'left'

  const summary =
    document.createElement('p')

  summary.textContent =
    `${project.memberCount} membre`
    + (
      project.memberCount > 1
        ? 's'
        : ''
    )

  summary.style.margin = '0'
  summary.style.opacity = '0.72'

  container.appendChild(
    summary,
  )

  if (error.value !== '') {
    const errorMessage =
      document.createElement('p')

    errorMessage.textContent =
      error.value

    errorMessage.style.margin = '0'
    errorMessage.style.color =
      'var(--danger, #b3261e)'

    container.appendChild(
      errorMessage,
    )
  }

  for (
    const member
    of membersFor(project.id)
  ) {
    const row =
      document.createElement('div')

    row.style.display = 'grid'
    row.style.gridTemplateColumns =
      'minmax(0, 1fr) auto auto'
    row.style.gap = '0.65rem'
    row.style.alignItems = 'center'
    row.style.padding = '0.75rem'
    row.style.border =
      '1px solid var(--border-color, #dadce0)'
    row.style.borderRadius = '10px'

    const identity =
      document.createElement('div')

    identity.style.display = 'grid'
    identity.style.gap = '0.15rem'
    identity.style.minWidth = '0'

    const email =
      document.createElement('strong')

    email.textContent =
      member.email

    email.style.overflowWrap =
      'anywhere'

    identity.appendChild(
      email,
    )

    if (
      isCurrentMember(
        member,
      )
    ) {
      const current =
        document.createElement('small')

      current.textContent = 'Vous'
      current.style.opacity = '0.7'

      identity.appendChild(
        current,
      )
    }

    row.appendChild(
      identity,
    )

    if (
      canChangeRole(
        project,
        member,
      )
    ) {
      const select =
        document.createElement(
          'select',
        )

      select.setAttribute(
        'aria-label',
        `Rôle de ${member.email}`,
      )

      for (
        const [
          value,
          label,
        ] of [
          [
            'admin',
            'Administrateur',
          ],
          [
            'member',
            'Membre',
          ],
        ] as const
      ) {
        const option =
          document.createElement(
            'option',
          )

        option.value = value
        option.textContent = label

        select.appendChild(
          option,
        )
      }

      select.value =
        member.role

      select.disabled =
        memberActionBusy.value
        === memberBusyKey(
          project,
          member,
        )

      select.addEventListener(
        'change',
        async event => {
          select.disabled = true

          await changeMemberRole(
            project,
            member,
            event,
          )

          renderMembersModal(
            project,
            container,
          )
        },
      )

      row.appendChild(
        select,
      )
    } else {
      const role =
        document.createElement('span')

      role.textContent =
        roleLabel(member.role)

      role.style.fontSize =
        '0.82rem'

      row.appendChild(
        role,
      )
    }

    if (
      canRemoveMember(
        project,
        member,
      )
    ) {
      const remove =
        document.createElement(
          'button',
        )

      remove.type = 'button'
      remove.textContent = 'Retirer'

      remove.style.border =
        '1px solid currentColor'
      remove.style.borderRadius =
        '8px'
      remove.style.padding =
        '0.45rem 0.65rem'
      remove.style.background =
        'transparent'
      remove.style.color =
        'var(--danger, #b3261e)'
      remove.style.cursor =
        'pointer'

      remove.addEventListener(
        'click',
        async () => {
          remove.disabled = true

          await removeMember(
            project,
            member,
          )

          renderMembersModal(
            project,
            container,
          )
        },
      )

      row.appendChild(
        remove,
      )
    } else {
      const spacer =
        document.createElement('span')

      row.appendChild(
        spacer,
      )
    }

    container.appendChild(
      row,
    )
  }

  if (
    project.role !== 'owner'
  ) {
    const leave =
      document.createElement(
        'button',
      )

    leave.type = 'button'
    leave.textContent =
      'Quitter ce projet'

    leave.style.justifySelf =
      'start'
    leave.style.border =
      '1px solid currentColor'
    leave.style.borderRadius =
      '8px'
    leave.style.padding =
      '0.55rem 0.75rem'
    leave.style.background =
      'transparent'
    leave.style.color =
      'var(--danger, #b3261e)'
    leave.style.cursor =
      'pointer'

    leave.addEventListener(
      'click',
      async () => {
        await leaveProject(
          project,
        )

        if (
          !projects.value.some(
            candidate =>
              candidate.id
              === project.id,
          )
        ) {
          Swal.close()
        }
      },
    )

    container.appendChild(
      leave,
    )
  }
}

async function showMembers(
  project: Project,
): Promise<void> {
  if (
    memberActionBusy.value
    !== null
  ) {
    return
  }

  error.value = ''

  void Swal.fire({
    title: `Membres · ${project.name}`,
    text: 'Chargement des membres…',
    showConfirmButton: false,
    allowOutsideClick: false,

    didOpen: () => {
      Swal.showLoading()
    },
  })

  const loaded =
    await loadMembers(
      project.id,
    )

  if (!loaded) {
    Swal.close()
    return
  }

  const container =
    document.createElement(
      'div',
    )

  renderMembersModal(
    project,
    container,
  )

  await Swal.fire({
    title: `Membres · ${project.name}`,
    html: container,
    showConfirmButton: false,
    showCloseButton: true,
    width: 680,
  })
}

async function changeMemberRole(
  project: Project,
  member: ProjectMember,
  event: Event,
): Promise<void> {
  if (
    !canChangeRole(
      project,
      member,
    )
  ) {
    return
  }

  const select =
    event.target as HTMLSelectElement

  const role =
    select.value as
      | 'admin'
      | 'member'

  const key =
    memberBusyKey(
      project,
      member,
    )

  memberActionBusy.value = key
  error.value = ''

  try {
    await api(
      `/api/projects/${project.id}/members/${member.userId}/role`,
      {
        method: 'PUT',

        body: JSON.stringify({
          role,
        }),
      },
    )

    await loadMembers(
      project.id,
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de modifier le rôle.'

    await loadMembers(
      project.id,
    )
  } finally {
    memberActionBusy.value = null
  }
}

async function removeMember(
  project: Project,
  member: ProjectMember,
): Promise<void> {
  if (
    !canRemoveMember(
      project,
      member,
    )
  ) {
    return
  }

  const confirmed =
    window.confirm(
      `Retirer ${member.email} du projet « ${project.name} » ?`,
    )

  if (!confirmed) {
    return
  }

  const key =
    memberBusyKey(
      project,
      member,
    )

  memberActionBusy.value = key
  error.value = ''

  try {
    await api(
      `/api/projects/${project.id}/members/${member.userId}`,
      {
        method: 'DELETE',
      },
    )

    project.memberCount =
      Math.max(
        0,
        project.memberCount - 1,
      )

    await loadMembers(
      project.id,
    )
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de retirer le membre.'
  } finally {
    memberActionBusy.value = null
  }
}

async function deleteProject(
  project: Project,
): Promise<void> {
  if (
    project.role !== 'owner'
    || busyProjectId.value !== null
  ) {
    return
  }

  const result =
    await Swal.fire({
      icon: 'warning',
      title: 'Supprimer ce projet ?',
      text:
        `« ${project.name} » sera supprimé. `
        + 'Ses tâches de projet seront supprimées, '
        + 'mais ses notes resteront disponibles.',
      showCancelButton: true,
      confirmButtonText:
        'Supprimer le projet',
      cancelButtonText: 'Annuler',
      focusCancel: true,
    })

  if (!result.isConfirmed) {
    return
  }

  busyProjectId.value =
    project.id

  error.value = ''

  try {
    await api(
      `/api/projects/${project.id}`,
      {
        method: 'DELETE',
      },
    )

    projects.value =
      projects.value.filter(
        candidate =>
          candidate.id !== project.id,
      )

    const {
      [project.id]: _removed,
      ...remainingMembers
    } = projectMembers.value

    projectMembers.value =
      remainingMembers
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de supprimer le projet.'
  } finally {
    busyProjectId.value = null
  }
}

async function leaveProject(
  project: Project,
): Promise<void> {
  if (
    project.role === 'owner'
    || busyProjectId.value !== null
  ) {
    return
  }

  const confirmed =
    window.confirm(
      `Quitter le projet « ${project.name} » ?`,
    )

  if (!confirmed) {
    return
  }

  busyProjectId.value =
    project.id

  error.value = ''

  try {
    await api(
      `/api/projects/${project.id}/leave`,
      {
        method: 'POST',
      },
    )

    projects.value =
      projects.value.filter(
        candidate =>
          candidate.id !== project.id,
      )

    const {
      [project.id]: _removed,
      ...remainingMembers
    } = projectMembers.value

    projectMembers.value =
      remainingMembers
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible de quitter le projet.'
  } finally {
    busyProjectId.value = null
  }
}

function roleLabel(
  role: Project['role'],
): string {
  return {
    owner: 'Propriétaire',
    admin: 'Administrateur',
    member: 'Membre',
  }[role]
}

onMounted(
  () => void load(),
)
</script>

<template>
  <section class="page projects-page">
    <header class="page-header">
      <div>
        <h1>Projets</h1>

        <p class="muted">
          Organisez vos notes et tâches
          dans des espaces collaboratifs.
        </p>
      </div>
    </header>

    <section
      v-if="invitations.length > 0"
      class="project-section"
    >
      <div class="project-section-heading">
        <div>
          <h2>Invitations</h2>

          <p class="muted">
            Projets auxquels vous avez
            été invité.
          </p>
        </div>
      </div>

      <div class="project-grid">
        <article
          v-for="invitation in invitations"
          :key="invitation.id"
          class="project-card"
        >
          <div class="project-card-heading">
            <span
              class="project-dot"
              :style="{
                background:
                  invitation.color,
              }"
            />

            <div>
              <strong>
                {{ invitation.name }}
              </strong>

              <small
                v-if="
                  invitation.invitedByEmail
                "
                class="muted"
              >
                Invitation de
                {{
                  invitation.invitedByEmail
                }}
              </small>
            </div>
          </div>

          <p
            v-if="invitation.description"
            class="project-description"
          >
            {{ invitation.description }}
          </p>

          <div class="project-card-actions">
            <button
              class="ui-button ui-button--primary"
              type="button"
              :disabled="
                busyInvitationId !== null
              "
              @click="
                acceptInvitation(invitation)
              "
            >
              Accepter
            </button>

            <button
              class="ui-button ui-button--secondary"
              type="button"
              :disabled="
                busyInvitationId !== null
              "
              @click="
                rejectInvitation(invitation)
              "
            >
              Refuser
            </button>
          </div>
        </article>
      </div>
    </section>

    <section class="project-section">
      <div class="project-section-heading">
        <div>
          <h2>Nouveau projet</h2>

          <p class="muted">
            Créez un espace pour vos
            notes, tâches et collaborateurs.
          </p>
        </div>
      </div>

      <form
        class="project-create-form"
        @submit.prevent="createProject"
      >
        <input
          v-model.trim="name"
          maxlength="120"
          placeholder="Nom du projet"
          required
        />

        <textarea
          v-model="description"
          maxlength="4000"
          rows="2"
          placeholder="Description facultative"
        />

        <input
          v-model="color"
          type="color"
          class="color-input"
          aria-label="Couleur du projet"
        />

        <button
          class="ui-button ui-button--primary"
          :disabled="
            creating
            || !name.trim()
          "
        >
          <AppIcon
            name="plus"
            :size="17"
          />

          {{
            creating
              ? 'Création…'
              : 'Créer'
          }}
        </button>
      </form>
    </section>

    <p
      v-if="error"
      class="form-error"
    >
      {{ error }}
    </p>

    <div
      v-if="loading"
      class="empty-state"
    >
      Chargement des projets…
    </div>

    <div
      v-else-if="projects.length === 0"
      class="empty-state"
    >
      <strong>
        Aucun projet pour le moment.
      </strong>

      <p>
        Créez un projet ou acceptez
        une invitation pour commencer.
      </p>
    </div>

    <div
      v-else
      class="project-grid"
    >
      <article
        v-for="project in projects"
        :key="project.id"
        class="project-card"
      >
        <div class="project-card-heading">
          <span
            class="project-dot"
            :style="{
              background:
                project.color,
            }"
          />

          <div>
            <strong>
              {{ project.name }}
            </strong>

            <small class="muted">
              {{ roleLabel(project.role) }}
            </small>
          </div>
        </div>

        <p
          v-if="project.description"
          class="project-description"
        >
          {{ project.description }}
        </p>

        <div class="project-stats">
          <span>
            {{ project.noteCount }}
            note{{
              project.noteCount > 1
                ? 's'
                : ''
            }}
          </span>

          <span>
            {{ project.memberCount }}
            membre{{
              project.memberCount > 1
                ? 's'
                : ''
            }}
          </span>
        </div>

        <button
          class="ui-button ui-button--secondary project-members-toggle"
          type="button"
          @click="
            showMembers(project)
          "
        >
          <AppIcon
            name="users"
            :size="17"
          />

          Voir les membres
        </button>

        <form
          v-if="
            project.role === 'owner'
            || project.role === 'admin'
          "
          class="project-invite-form"
          @submit.prevent="
            invite(project)
          "
        >
          <div class="project-invite-field">
            <input
              v-model="
                inviteEmails[project.id]
              "
              type="email"
              maxlength="254"
              autocomplete="email"
              placeholder="Rechercher un compte Harpocrate"
              aria-label="Compte Harpocrate à rechercher"
              @input="
                clearConfirmedInvitee(
                  project.id,
                )
              "
            />

            <small
              v-if="
                confirmedInvitees[
                  project.id
                ]
              "
              class="project-invite-selected"
            >
              Compte sélectionné :
              <strong>
                {{
                  confirmedInvitees[
                    project.id
                  ]?.email
                }}
              </strong>
            </small>

            <small
              v-else
              class="muted"
            >
              Saisissez l’email exact
              d’un compte Harpocrate.
            </small>
          </div>

          <button
            class="ui-button ui-button--secondary"
            type="button"
            :disabled="
              lookupBusyProjectId !== null
              || busyProjectId !== null
              || !inviteEmails[
                project.id
              ]?.trim()
            "
            @click="
              lookupInvitee(project)
            "
          >
            {{
              lookupBusyProjectId
                === project.id
                ? 'Vérification…'
                : 'Vérifier'
            }}
          </button>

          <button
            class="ui-button ui-button--secondary"
            type="submit"
            :disabled="
              busyProjectId !== null
              || lookupBusyProjectId
                !== null
              || !confirmedInvitees[
                project.id
              ]
            "
          >
            Inviter
          </button>
        </form>

        <button
          v-if="project.role === 'owner'"
          class="
            ui-button
            ui-button--secondary
            project-delete
          "
          type="button"
          :disabled="
            busyProjectId !== null
          "
          @click="
            deleteProject(project)
          "
        >
          Supprimer le projet
        </button>

        <RouterLink
          class="ui-button ui-button--primary project-open"
          :to="
            `/projects/${project.id}`
          "
        >
          Ouvrir le projet
        </RouterLink>
      </article>
    </div>
  </section>
</template>

<style scoped>
.projects-page {
  display: grid;
  gap: 1.5rem;
}

.project-section {
  display: grid;
  gap: 1rem;
}

.project-section-heading h2 {
  margin-bottom: 0.25rem;
}

.project-create-form {
  display: grid;
  grid-template-columns:
    minmax(180px, 1fr)
    minmax(240px, 2fr)
    auto
    auto;
  gap: 0.75rem;
  align-items: center;
}

.project-create-form input,
.project-create-form textarea,
.project-invite-form input {
  min-width: 0;
}

.project-grid {
  display: grid;
  grid-template-columns:
    repeat(
      auto-fill,
      minmax(260px, 1fr)
    );
  gap: 1rem;
}

.project-card {
  display: grid;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid
    var(--border-color, #dadce0);
  border-radius: 14px;
  background:
    var(--surface, #fff);
}

.project-card-heading {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
}

.project-card-heading > div {
  display: grid;
  gap: 0.15rem;
}

.project-dot {
  width: 12px;
  height: 12px;
  margin-top: 0.3rem;
  flex: 0 0 auto;
  border-radius: 50%;
}

.project-description {
  margin: 0;
  line-height: 1.45;
}

.project-stats {
  display: flex;
  gap: 1rem;
  font-size: 0.9rem;
}

.project-invite-form {
  display: flex;
  gap: 0.5rem;
}

.project-invite-field {
  display: grid;
  gap: 0.25rem;
  flex: 1;
  min-width: 0;
}

.project-invite-field input {
  width: 100%;
  box-sizing: border-box;
}

.project-invite-selected {
  overflow-wrap: anywhere;
}

.project-card-actions {
  display: flex;
  gap: 0.5rem;
}

.project-open {
  justify-self: start;
  text-decoration: none;
}

.project-delete {
  justify-self: start;
  color:
    var(--danger, #b3261e);
  border-color:
    currentColor;
}

@media (max-width: 800px) {
  .project-create-form {
    grid-template-columns: 1fr;
  }

  .project-invite-form {
    flex-direction: column;
  }
}
</style>
