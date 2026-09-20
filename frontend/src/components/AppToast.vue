<script setup lang="ts">
import {
  useToast,
} from '../composables/useToast'

const {
  toast,
  hide,
} = useToast()
</script>

<template>
  <Teleport to="body">
    <Transition name="toast">
      <div
        v-if="toast.visible"
        class="app-toast"
        :class="
          `app-toast--${toast.kind}`
        "
        role="status"
        aria-live="polite"
      >
        <span class="app-toast-dot" />

        <span class="app-toast-message">
          {{ toast.message }}
        </span>

        <button
          type="button"
          class="app-toast-close"
          aria-label="Fermer la notification"
          @click="hide"
        >
          ×
        </button>
      </div>
    </Transition>
  </Teleport>
</template>
