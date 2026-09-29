<script setup lang="ts">
import {
  computed,
} from 'vue'

import {
  FontAwesomeIcon,
} from '@fortawesome/vue-fontawesome'

import type {
  IconDefinition,
} from '@fortawesome/fontawesome-svg-core'

import {
  faBars,
  faBoxArchive,
  faChartColumn,
  faCheck,
  faCopy,
  faFileLines,
  faImage,
  faList,
  faLock,
  faMagnifyingGlass,
  faPen,
  faPlus,
  faRightFromBracket,
  faTableCellsLarge,
  faTableColumns,
  faTag,
  faThumbtack,
  faTrashCan,
  faUser,
  faUsers,
  faXmark,
  faStopwatch,
} from '@fortawesome/free-solid-svg-icons'

type AppIconName =
  | 'menu'
  | 'note'
  | 'image'
  | 'tag'
  | 'timer'
  | 'chart'
  | 'archive'
  | 'trash'
  | 'search'
  | 'lock'
  | 'logout'
  | 'edit'
  | 'user'
  | 'users'
  | 'plus'
  | 'grid'
  | 'list'
  | 'whiteboard'
  | 'copy'
  | 'pin'
  | 'check'
  | 'close'

const props = defineProps<{
  name: AppIconName
  size?: number
}>()

const icons:
Record<AppIconName, IconDefinition> = {
  menu: faBars,
  note: faFileLines,
  image: faImage,
  tag: faTag,
  timer: faStopwatch,
  chart: faChartColumn,
  archive: faBoxArchive,
  trash: faTrashCan,
  search: faMagnifyingGlass,
  lock: faLock,
  logout: faRightFromBracket,
  edit: faPen,
  user: faUser,
  users: faUsers,
  plus: faPlus,
  grid: faTableCellsLarge,
  list: faList,
  whiteboard: faTableColumns,
  copy: faCopy,
  pin: faThumbtack,
  check: faCheck,
  close: faXmark,
}

const resolvedIcon =
  computed(
    () => icons[props.name],
  )

const iconSize =
  computed(
    () => `${props.size ?? 20}px`,
  )
</script>

<template>
  <FontAwesomeIcon
    class="app-icon"
    :icon="resolvedIcon"
    fixed-width
    aria-hidden="true"
    :style="{
      fontSize: iconSize,
    }"
  />
</template>
