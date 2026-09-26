<script setup lang="ts">
import {
  onMounted,
  ref,
} from 'vue'

import AppIcon from '../components/AppIcon.vue'
import { api } from '../services/api'

import type {
  Project,
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

const projects =
  ref<Project[]>([])

const invitations =
  ref<ProjectInvitation[]>([])

const loading = ref(true)
const error = ref('')

const name = ref('')
const description = ref('')
const color = ref('#1A73E8')
const creating = ref(false)

const inviteEmails =
  ref<Record<number, string>>({})

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
    ])

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

async function invite(
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
    || busyProjectId.value !== null
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
          email,
        }),
      },
    )

    inviteEmails.value[
      project.id
    ] = ''
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : 'Impossible d’envoyer l’invitation.'
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
          <input
            v-model="
              inviteEmails[project.id]
            "
            type="email"
            maxlength="254"
            placeholder="Inviter par email"
          />

          <button
            class="ui-button ui-button--secondary"
            :disabled="
              busyProjectId !== null
            "
          >
            Inviter
          </button>
        </form>

        <RouterLink
          class="ui-button ui-button--primary project-open"
          :to="{
            path: '/notes',
            query: {
              projectId:
                String(project.id),
            },
          }"
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

.project-invite-form input {
  flex: 1;
}

.project-card-actions {
  display: flex;
  gap: 0.5rem;
}

.project-open {
  justify-self: start;
  text-decoration: none;
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
