<script setup lang="ts">
import {
  computed,
} from 'vue'

import MarkdownIt from 'markdown-it'

const props =
  defineProps<{
    source: string
  }>()

const markdown =
  new MarkdownIt({
    html: false,
    breaks: true,
    linkify: true,
    typographer: true,
  })

const rendered =
  computed(
    () =>
      props.source.trim() === ''
        ? ''
        : markdown.render(
            props.source,
          ),
  )
</script>

<template>
  <div
    class="markdown-preview"
    v-html="rendered"
  />
</template>

<style scoped>
.markdown-preview {
  overflow-wrap: anywhere;
  line-height: 1.65;
}

.markdown-preview :deep(
  > :first-child
) {
  margin-top: 0;
}

.markdown-preview :deep(
  > :last-child
) {
  margin-bottom: 0;
}

.markdown-preview :deep(h1),
.markdown-preview :deep(h2),
.markdown-preview :deep(h3),
.markdown-preview :deep(h4) {
  margin:
    1.25rem 0
    0.6rem;
  line-height: 1.25;
}

.markdown-preview :deep(p) {
  margin: 0.65rem 0;
}

.markdown-preview :deep(
  ul,
),
.markdown-preview :deep(
  ol
) {
  padding-left: 1.5rem;
}

.markdown-preview :deep(
  blockquote
) {
  margin:
    0.85rem 0;
  padding:
    0.15rem 0
    0.15rem 1rem;
  border-left:
    4px solid
    var(--g-border);
  color:
    var(--g-muted);
}

.markdown-preview :deep(
  code
) {
  padding:
    0.12rem 0.3rem;
  border-radius: 5px;
  background:
    var(--g-surface-alt);
  font-family:
    ui-monospace,
    SFMono-Regular,
    Menlo,
    Monaco,
    Consolas,
    monospace;
}

.markdown-preview :deep(
  pre
) {
  overflow-x: auto;
  padding: 1rem;
  border-radius: 10px;
  background:
    var(--g-surface-alt);
}

.markdown-preview :deep(
  pre code
) {
  padding: 0;
  background: transparent;
}

.markdown-preview :deep(a) {
  color:
    var(--g-blue-strong);
}
</style>
