<script setup name="MenuNode">
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
  node: { type: Object, required: true },
  collapsed: { type: Boolean, default: false },
  depth: { type: Number, default: 0 },
  // Conjunto de ids visitados para cortar ciclos
  visited: { type: Object, default: () => new Set() },
})

const page = usePage()
const open = ref(false)

const external = (h) => /^https?:\/\//i.test(h || '')
const href = computed(() => props.node?.href ?? props.node?.link ?? '')
const label = computed(() => props.node?.title ?? props.node?.description ?? '')
const hasRawChildren = computed(() => Array.isArray(props.node?.children) && props.node.children.length > 0)

// Filtramos hijos cíclicos o inválidos
const children = computed(() => {
  const raw = Array.isArray(props.node?.children) ? props.node.children : []
  const currentId = props.node?.id ?? props.node?.Id
  return raw.filter((c) => {
    const cid = c?.id ?? c?.Id
    return c && cid != null && cid !== currentId && !props.visited.has(cid)
  })
})

const isActive = (h) => !!h && !external(h) && page.url === h

// Nuevo conjunto de visitados para los hijos
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
      :is="href ? (external(href) ? 'a' : Link) : 'button'"
      :href="href || undefined"
      :target="external(href) ? '_blank' : undefined"
      :rel="external(href) ? 'noopener' : undefined"
      class="group flex w-full items-center justify-between rounded px-3 py-2 hover:bg-slate-100"
      :class="{ 'bg-slate-100 font-medium': isActive(href) }"
      @click="toggle"
    >
      <span class="truncate" :title="label">
        <span v-if="!collapsed">{{ label }}</span>
        <span v-else class="font-semibold">{{ String(label || '').slice(0,1) }}</span>
      </span>
      <span v-if="children.length" class="ml-2 text-xs opacity-60">▸</span>
    </component>

    <transition name="fade">
      <ul
        v-if="children.length && open"
        class="ml-3 border-l pl-3"
      >
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
