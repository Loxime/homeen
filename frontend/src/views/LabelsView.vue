<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../services/api'
import type { Label } from '../types/domain'

const labels = ref<Label[]>([])
const name = ref('')
const color = ref('#5B67F1')
const editingId = ref<number | null>(null)
const editName = ref('')
const editColor = ref('#5B67F1')
const error = ref('')

async function load(): Promise<void> {
  const response = await api<{ labels: Label[] }>('/api/labels')
  labels.value = response.labels
}

async function create(): Promise<void> {
  error.value = ''
  try {
    await api<Label>('/api/labels', {
      method: 'POST',
      body: JSON.stringify({ name: name.value, color: color.value }),
    })
    name.value = ''
    await load()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Impossible de créer le libellé.'
  }
}

function beginEdit(label: Label): void {
  editingId.value = label.id
  editName.value = label.name
  editColor.value = label.color
}

async function saveEdit(id: number): Promise<void> {
  error.value = ''
  try {
    await api<Label>(`/api/labels/${id}`, {
      method: 'PUT',
      body: JSON.stringify({ name: editName.value, color: editColor.value }),
    })
    editingId.value = null
    await load()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Impossible de modifier le libellé.'
  }
}

async function remove(label: Label): Promise<void> {
  if (!confirm(`Supprimer le libellé « ${label.name} » ? Les notes associées n’auront plus de libellé.`)) return
  await api(`/api/labels/${label.id}`, { method: 'DELETE' })
  await load()
}

onMounted(() => void load())
</script>

<template>
  <section class="page narrow-page">
    <header class="page-header">
      <div><p class="eyebrow">ORGANISATION</p><h1>Libellés</h1><p class="muted">Une note peut utiliser un seul libellé.</p></div>
    </header>

    <section class="panel create-label-panel">
      <div><p class="eyebrow">NOUVEAU LIBELLÉ</p><h2>Créer un libellé</h2></div>
      <form class="label-form" @submit.prevent="create">
        <input v-model="name" maxlength="80" placeholder="Nom du libellé" required />
        <input v-model="color" class="color-input" type="color" aria-label="Couleur du libellé" />
        <button class="primary">Créer</button>
      </form>
      <p v-if="error" class="form-error">{{ error }}</p>
    </section>

    <section class="panel labels-panel">
      <div class="table-header"><span>Libellé</span><span>Notes</span><span>Actions</span></div>
      <div v-for="label in labels" :key="label.id" class="label-row">
        <template v-if="editingId === label.id">
          <div class="label-edit"><input v-model="editColor" class="color-input" type="color" /><input v-model="editName" maxlength="80" /></div>
          <span>{{ label.noteCount }}</span>
          <div class="row-actions"><button class="primary small-button" @click="saveEdit(label.id)">Enregistrer</button><button class="ghost" @click="editingId = null">Annuler</button></div>
        </template>
        <template v-else>
          <div class="label-name"><span class="label-dot" :style="{ background: label.color }" /><strong>{{ label.name }}</strong><code>{{ label.color }}</code></div>
          <span>{{ label.noteCount }}</span>
          <div class="row-actions"><button class="ghost" @click="beginEdit(label)">Modifier</button><button class="danger-ghost" @click="remove(label)">Supprimer</button></div>
        </template>
      </div>
      <div v-if="labels.length === 0" class="empty-state compact">Aucun libellé pour le moment.</div>
    </section>
  </section>
</template>
