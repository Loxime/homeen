<script setup lang="ts">
import {
  computed,
  ref,
  watch,
} from 'vue'

import AppIcon from './AppIcon.vue'

import type {
  Tag,
} from '../types/domain'

const props = withDefaults(
  defineProps<{
    tags: Tag[]
    modelValue: number[]
    title?: string
    disabled?: boolean
  }>(),
  {
    title: 'Labels',
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [
    value: number[],
  ]
}>()

const open = ref(false)
const query = ref('')

const selectedTags =
  computed(
    () =>
      props.tags.filter(
        tag =>
          props.modelValue.includes(
            tag.id,
          ),
      ),
  )

const filteredTags =
  computed(() => {
    const value =
      query.value
        .trim()
        .toLocaleLowerCase()

    if (!value) {
      return props.tags
    }

    return props.tags.filter(
      tag =>
        tag.name
          .toLocaleLowerCase()
          .includes(value),
    )
  })

watch(
  open,
  value => {
    if (!value) {
      query.value = ''
    }
  },
)

function isSelected(
  id: number,
): boolean {
  return props.modelValue.includes(id)
}

function toggle(
  id: number,
): void {
  if (props.disabled) {
    return
  }

  emit(
    'update:modelValue',
    isSelected(id)
      ? props.modelValue.filter(
          value => value !== id,
        )
      : Array.from(
          new Set([
            ...props.modelValue,
            id,
          ]),
        ),
  )
}

function remove(
  id: number,
): void {
  if (props.disabled) {
    return
  }

  emit(
    'update:modelValue',
    props.modelValue.filter(
      value => value !== id,
    ),
  )
}
</script>

<template>
  <div class="label-selector">
    <div class="label-selector-heading">
      <h2>
        {{ title }}
      </h2>

      <button
        type="button"
        class="label-selector-edit"
        :disabled="disabled"
        :aria-expanded="open"
        @click="open = !open"
      >
        <AppIcon
          name="edit"
          :size="14"
        />

        <span>
          Edit
        </span>
      </button>
    </div>

    <div
      v-if="selectedTags.length > 0"
      class="label-selector-selected"
    >
      <span
        v-for="tag in selectedTags"
        :key="tag.id"
        class="label-selector-chip"
        :style="{
          '--label-color':
            tag.color,
        }"
      >
        <span
          class="label-selector-chip-dot"
          aria-hidden="true"
        />

        <span>
          {{ tag.name }}
        </span>

        <button
          type="button"
          :disabled="disabled"
          :aria-label="
            `Retirer le label ${tag.name}`
          "
          @click="
            remove(tag.id)
          "
        >
          <AppIcon
            name="close"
            :size="11"
          />
        </button>
      </span>
    </div>

    <p
      v-else
      class="label-selector-empty"
    >
      Aucun label.
    </p>

    <div
      v-if="open"
      class="label-selector-panel"
    >
      <div class="label-selector-search">
        <AppIcon
          name="search"
          :size="14"
        />

        <input
          v-model="query"
          type="search"
          placeholder="Rechercher un label…"
          aria-label="Rechercher un label"
          autocomplete="off"
        />
      </div>

      <div
        v-if="filteredTags.length > 0"
        class="label-selector-options"
      >
        <button
          v-for="tag in filteredTags"
          :key="tag.id"
          type="button"
          class="label-selector-option"
          :class="{
            active:
              isSelected(tag.id),
          }"
          :disabled="disabled"
          @click="
            toggle(tag.id)
          "
        >
          <span
            class="label-selector-option-dot"
            :style="{
              background:
                tag.color,
            }"
            aria-hidden="true"
          />

          <span class="label-selector-option-name">
            {{ tag.name }}
          </span>

          <AppIcon
            v-if="isSelected(tag.id)"
            name="check"
            :size="13"
          />
        </button>
      </div>

      <p
        v-else
        class="label-selector-search-empty"
      >
        Aucun label correspondant.
      </p>

      <RouterLink
        class="label-selector-manage"
        to="/tags"
      >
        Gérer les labels
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.label-selector {
  min-width: 0;
  display: grid;
  gap: .8rem;
}

.label-selector-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .75rem;
}

.label-selector-heading h2 {
  margin: 0;
  color: var(--g-text);
  font-size: .9rem;
}

.label-selector-edit {
  min-height: 32px;
  padding: 0 .65rem;

  display: inline-flex;
  align-items: center;
  gap: .4rem;

  border: 1px solid var(--g-border);
  border-radius: 8px;

  background: transparent;
  color: var(--g-blue-strong);

  font: inherit;
  font-size: .78rem;
  font-weight: 650;
}

.label-selector-edit:hover:not(:disabled) {
  background: var(--g-surface-alt);
}

.label-selector-selected {
  display: flex;
  flex-wrap: wrap;
  gap: .4rem;
}

.label-selector-chip {
  --label-color: #6b7280;

  min-width: 0;
  padding: .3rem .35rem .3rem .5rem;

  display: inline-flex;
  align-items: center;
  gap: .35rem;

  border: 1px solid var(--g-border);
  border-radius: 999px;

  background: var(--g-surface);
  color: var(--g-text);

  font-size: .75rem;
  font-weight: 600;
}

.label-selector-chip-dot {
  width: 8px;
  height: 8px;
  flex: 0 0 8px;
  border-radius: 50%;
  background: var(--label-color);
}

.label-selector-chip button {
  width: 22px;
  height: 22px;
  padding: 0;

  display: grid;
  place-items: center;

  border: 0;
  border-radius: 50%;

  background: transparent;
  color: var(--g-muted);
}

.label-selector-chip button:hover:not(:disabled) {
  background: var(--g-surface-alt);
  color: var(--g-text);
}

.label-selector-empty,
.label-selector-search-empty {
  margin: 0;
  color: var(--g-muted);
  font-size: .78rem;
}

.label-selector-panel {
  display: grid;
  gap: .55rem;

  padding: .65rem;

  border: 1px solid var(--g-border);
  border-radius: 12px;

  background: var(--g-surface);
}

.label-selector-search {
  min-height: 36px;
  padding: 0 .65rem;

  display: flex;
  align-items: center;
  gap: .5rem;

  border: 1px solid var(--g-border);
  border-radius: 8px;

  background: var(--g-surface-alt);
  color: var(--g-muted);
}

.label-selector-search input {
  min-width: 0;
  width: 100%;
  padding: 0;

  border: 0;
  outline: 0;
  box-shadow: none;

  background: transparent;
  color: var(--g-text);
}

.label-selector-options {
  max-height: 240px;
  overflow-y: auto;

  display: grid;
  gap: 2px;
}

.label-selector-option {
  width: 100%;
  min-height: 36px;
  padding: .45rem .55rem;

  display: grid;
  grid-template-columns:
    10px
    minmax(0, 1fr)
    auto;
  align-items: center;
  gap: .55rem;

  border: 0;
  border-radius: 7px;

  background: transparent;
  color: var(--g-text);

  text-align: left;
}

.label-selector-option:hover:not(:disabled),
.label-selector-option.active {
  background: var(--g-surface-alt);
}

.label-selector-option-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
}

.label-selector-option-name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.label-selector-manage {
  padding-top: .5rem;

  border-top: 1px solid var(--g-border);

  color: var(--g-blue-strong);
  font-size: .76rem;
  font-weight: 600;
  text-decoration: none;
}

.label-selector-manage:hover {
  text-decoration: underline;
}
</style>
