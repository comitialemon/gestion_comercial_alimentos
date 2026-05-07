<!-- resources/js/Components/Menu/MenuNode.vue -->
<script setup name="MenuNode">
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
  node: { type: Object, required: true },
  collapsed: { type: Boolean, default: false },
  depth: { type: Number, default: 0 },
  visited: { type: Object, default: () => new Set() },
})

const page = usePage()
const open = ref(false)

// Helpers
const rawHref = computed(() =>
  props.node?.href ?? props.node?.link ?? props.node?.Link ?? ''
)

const isExternal = computed(() =>
  /^https?:\/\//i.test(rawHref.value || '') ||
  (rawHref.value || '').startsWith('mailto:') ||
  (rawHref.value || '').startsWith('#')
)

// Normaliza: si es interno, asegura "/" inicial y quita duplicados
const href = computed(() => {
  const h = String(rawHref.value || '').trim()
  if (!h) return ''
  if (isExternal.value) return h
  return '/' + h.replace(/^\/+/, '')
})

const label = computed(() =>
  props.node?.title ?? props.node?.description ?? props.node?.Description ?? ''
)

const hasRawChildren = computed(
  () => Array.isArray(props.node?.children) && props.node.children.length > 0
)

const children = computed(() => {
  const raw = Array.isArray(props.node?.children) ? props.node.children : []
  const currentId = props.node?.id ?? props.node?.Id
  return raw.filter((c) => {
    const cid = c?.id ?? c?.Id
    return c && cid != null && cid !== currentId && !props.visited.has(cid)
  })
})

const isActive = (h) => !!h && !isExternal.value && page.url === h

const nextVisited = computed(() => {
  const s = new Set(props.visited)
  const currentId = props.node?.id ?? props.node?.Id
  if (currentId != null) s.add(currentId)
  return s
})

const toggle = () => {
  if (!href.value && hasRawChildren.value) open.value = !open.value
}
</script>

<template>
  <li>
    <component
      :is="href ? (isExternal ? 'a' : Link) : 'button'"
      :href="href || undefined"
      :target="isExternal ? '_blank' : undefined"
      :rel="isExternal ? 'noopener' : undefined"
      class="group flex w-full items-center justify-between rounded px-2 py-1.5 hover:bg-slate-100 text-[13px] leading-4"
      :class="{ 'bg-slate-100 font-medium': isActive(href) }"
      @click="toggle"
    >
      <span class="truncate" :title="label">
        <span v-if="!collapsed">{{ label }}</span>
        <span v-else class="font-semibold">{{ String(label || '').slice(0,1) }}</span>
      </span>
      <span v-if="children.length" class="ml-2 text-[18px] opacity-60">▸</span>
    </component>

    <transition name="fade">
      <ul v-if="children.length && open" class="ml-2 border-l pl-2">
        <MenuNode
          v-for="c in children"
          :key="c.id ?? c.Id"
          :node="c"
          :collapsed="collapsed"
          :depth="depth + 1"
          :visited="nextVisited"
        />
      </ul>
    </transition>
  </li>
</template>

<style>
.fade-enter-active, .fade-leave-active { transition: all .15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-2px); }
</style>