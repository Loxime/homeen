<script setup lang="ts">
defineProps<{
  name:
    | 'menu'
    | 'note'
    | 'tag'
    | 'timer'
    | 'chart'
    | 'archive'
    | 'trash'
    | 'search'
    | 'lock'
    | 'plus'
    | 'grid'
    | 'list'
    | 'whiteboard'
    | 'copy'
    | 'check'
    | 'close'
  size?: number
}>()
</script>

<template>
  <svg
    class="app-icon"
    :width="size ?? 20"
    :height="size ?? 20"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <template v-if="name === 'menu'">
      <path d="M4 7h16M4 12h16M4 17h16" />
    </template>

    <template v-else-if="name === 'note'">
      <path d="M6 3h9l3 3v15H6z" />
      <path d="M15 3v4h4M9 11h6M9 15h6" />
    </template>

    <template v-else-if="name === 'tag'">
      <path d="M3 12V5a2 2 0 0 1 2-2h7l9 9-9 9z" />
      <circle cx="8.5" cy="8.5" r="1.25" />
    </template>

    <template v-else-if="name === 'timer'">
      <circle cx="12" cy="13" r="8" />
      <path d="M9 2h6M12 5v2M12 13l3-2" />
    </template>

    <template v-else-if="name === 'chart'">
      <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
    </template>

    <template v-else-if="name === 'archive'">
      <path d="M4 7h16v13H4zM3 3h18v4H3zM9 12h6" />
    </template>

    <template v-else-if="name === 'trash'">
      <path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14M10 11v6M14 11v6" />
    </template>

    <template v-else-if="name === 'search'">
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-4-4" />
    </template>

    <template v-else-if="name === 'lock'">
      <rect x="5" y="10" width="14" height="11" rx="2" />
      <path d="M8 10V7a4 4 0 0 1 8 0v3" />
    </template>

    <template v-else-if="name === 'plus'">
      <path d="M12 5v14M5 12h14" />
    </template>

    <template v-else-if="name === 'grid'">
      <rect x="4" y="4" width="6" height="6" rx="1" />
      <rect x="14" y="4" width="6" height="6" rx="1" />
      <rect x="4" y="14" width="6" height="6" rx="1" />
      <rect x="14" y="14" width="6" height="6" rx="1" />
    </template>

    <template v-else-if="name === 'list'">
      <path d="M9 6h11M9 12h11M9 18h11" />
      <circle cx="4.5" cy="6" r="1" />
      <circle cx="4.5" cy="12" r="1" />
      <circle cx="4.5" cy="18" r="1" />
    </template>

    <template v-else-if="name === 'whiteboard'">
      <rect x="3" y="4" width="18" height="15" rx="2" />
      <path d="M8 8h4v3H8zM14 12h4v3h-4zM9 19v2M15 19v2" />
    </template>

    <template v-else-if="name === 'copy'">
      <rect x="8" y="8" width="11" height="11" rx="2" />
      <path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3" />
    </template>

    <template v-else-if="name === 'check'">
      <path d="m5 12 4 4L19 6" />
    </template>

    <template v-else-if="name === 'close'">
      <path d="M6 6l12 12M18 6 6 18" />
    </template>
  </svg>
</template>
