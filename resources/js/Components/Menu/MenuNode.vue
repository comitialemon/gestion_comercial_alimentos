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

// 🔥 TEMA DINÁMICO
const theme = computed(() => page.props?.theme || {
    primary: '#1f2937',
    hasCustomTheme: false
})

const rawHref = computed(() =>
  props.node?.href ?? props.node?.link ?? props.node?.Link ?? ''
)

const isExternal = computed(() =>
  /^https?:\/\//i.test(rawHref.value || '') ||
  rawHref.value?.startsWith('mailto:') ||
  rawHref.value?.startsWith('#')
)

const href = computed(() => {
  const h = String(rawHref.value || '').trim()
  if (!h) return ''
  if (isExternal.value) return h
  return '/' + h.replace(/^\/+/, '')
})

// Detectar si es un enlace no migrado (Scriptcase)
const isNoMigrado = computed(() => {
  if (!href.value || isExternal.value) return false
  
  const h = href.value.toLowerCase()
  
  // Excluir rutas que ya migramos
  const rutasMigradas = [
    '/gestion/inventario/ajustes',
    '/gestion/inventario/reporte-inventario',
    '/gestion/ingresos',
    '/gestion/egresos',
    '/gestion/compras',
    '/gestion/lugar-venta',
    '/gestion/comisionista',
    '/gestion/todos/identificador',
    '/facturacion/empresas',
    '/facturacion/sucursales',
    '/venta-tactil',
    '/gestion/inventario/categorias-producto',
  ]
  
  // Si es una ruta migrada, NO es no migrado
  if (rutasMigradas.some(ruta => h === ruta || h.startsWith(ruta + '/'))) {
    return false
  }
  
  // Detectar si tiene formato Scriptcase (con guiones bajos)
  const tieneGuionBajo = /[a-z]+_[a-z]+/.test(h)
  
  // Detectar si no tiene formato Laravel
  const noTieneFormatoLaravel = !h.startsWith('/gestion/') && 
                                 !h.startsWith('/facturacion/') && 
                                 !h.startsWith('/venta-') &&
                                 !h.startsWith('/contexto') &&
                                 !h.startsWith('/oficial')
  
  // Detectar si es una URL amigable de Scriptcase
  const esScriptcaseAmigable = /^\/[a-z]+[a-z\-]+$/.test(h) && h.length > 5 && !h.includes('gestion')
  
  return tieneGuionBajo || noTieneFormatoLaravel || esScriptcaseAmigable
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

// 🔥 CLASES DINÁMICAS BASADAS EN EL TEMA
const activeClass = computed(() => {
  if (theme.value.hasCustomTheme) {
    return 'bg-primary-800 text-white font-medium'
  }
  return 'bg-gray-800 text-white font-medium'
})

const hoverClass = computed(() => {
  if (theme.value.hasCustomTheme) {
    return 'hover:bg-primary-100 hover:text-primary-800'
  }
  return 'hover:bg-gray-100 hover:text-gray-800'
})

const iconColorClass = computed(() => {
  if (isNoMigrado.value) return 'text-amber-500'
  if (theme.value.hasCustomTheme) return 'text-primary-500'
  return 'text-gray-500'
})

const borderColorClass = computed(() => {
  if (isNoMigrado.value) return 'border-amber-400'
  if (theme.value.hasCustomTheme) return 'border-primary-200'
  return 'border-gray-200'
})

const activeIconClass = computed(() => {
  if (isNoMigrado.value) return 'text-amber-500'
  return 'text-white'
})
</script>

<template>
  <li>
    <component
      :is="href ? (isExternal ? 'a' : Link) : 'button'"
      :href="href || undefined"
      :target="isExternal ? '_blank' : undefined"
      :rel="isExternal ? 'noopener' : undefined"
      class="group flex w-full items-center justify-between rounded-md px-2 py-1.5 text-[13px] leading-4 transition-colors"
      :class="[
        isActive(href) ? activeClass : isNoMigrado
          ? 'text-amber-700 hover:bg-amber-50 hover:text-amber-800 border-l-2 border-amber-400'
          : `text-gray-700 ${hoverClass}`
      ]"
      @click="toggle"
    >
      <span class="truncate flex items-center gap-1.5" :title="label">
        <!-- Icono de advertencia para enlaces no migrados -->
        <i v-if="isNoMigrado && !collapsed" class="fas fa-exclamation-triangle text-amber-500 text-xs"></i>
        <i v-if="isNoMigrado && collapsed" class="fas fa-exclamation-triangle text-amber-500 text-xs"></i>
        
        <span v-if="!collapsed">{{ label }}</span>
        <span v-else class="font-semibold">{{ String(label || '').slice(0,1) }}</span>
      </span>
      
      <i 
        v-if="children.length" 
        class="fas fa-chevron-right ml-2 text-xs transition-all duration-200"
        :class="[
          open ? 'rotate-90' : '',
          isActive(href) ? activeIconClass : iconColorClass
        ]"
      ></i>
    </component>

    <transition name="fade">
      <ul v-if="children.length && open" class="ml-3 border-l pl-2" :class="borderColorClass">
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

<style scoped>
.fade-enter-active, .fade-leave-active { transition: all 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-4px); }
</style>