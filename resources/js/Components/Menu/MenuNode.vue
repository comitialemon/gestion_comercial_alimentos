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

// 👀 DETECTAR SI ES UN ENLACE NO MIGRADO (100% DINÁMICO DESDE LA DB)
const isNoMigrado = computed(() => {
  if (!href.value || isExternal.value) return false
  
  const h = href.value.toLowerCase()
  
  // 1. Si tiene guiones bajos estilo "personal_datos_...", es formato antiguo
  const tieneGuionBajo = /[a-z]+_[a-z]+/.test(h)
  if (tieneGuionBajo) {
    return true
  }

  // 2. Si no contiene barras diagonales "/", es un script o módulo plano antiguo
  if (!h.includes('/')) {
    return true
  }
  
  // 3. Si es una URL estructurada con "/" (ej: /contabilidad/diario, /pdv/borrar), es Laravel
  return false
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

// 🔥 ESTILOS DINÁMICOS COMPLETOS
const activeClass = computed(() => {
  if (theme.value.hasCustomTheme) {
    return 'active-custom-theme'
  }
  return 'active-default-theme'
})

const parentClass = computed(() => {
  if (theme.value.hasCustomTheme) {
    return 'parent-custom-theme'
  }
  return 'parent-default-theme'
})

const childClass = computed(() => {
  if (theme.value.hasCustomTheme) {
    return 'child-custom-theme'
  }
  return 'child-default-theme'
})

const noMigradoClass = computed(() => {
  return 'no-migrado-item'
})

const activeStyle = computed(() => {
  if (theme.value.hasCustomTheme) {
    return {
      backgroundColor: `var(--color-primary-700)`,
      color: '#ffffff'
    }
  }
  return {
    backgroundColor: '#374151',
    color: '#ffffff'
  }
})

const parentStyle = computed(() => {
  if (theme.value.hasCustomTheme) {
    return {
      color: `var(--color-primary-200)`
    }
  }
  return {
    color: '#e5e5e5'
  }
})

const childStyle = computed(() => {
  if (theme.value.hasCustomTheme) {
    return {
      color: `var(--color-primary-300)`
    }
  }
  return {
    color: '#d1d5db'
  }
})

const hoverStyle = computed(() => {
  if (theme.value.hasCustomTheme) {
    return {
      backgroundColor: `var(--color-primary-800)`,
      color: `var(--color-primary-100)`
    }
  }
  return {
    backgroundColor: '#4b5563',
    color: '#ffffff'
  }
})

const noMigradoStyle = computed(() => {
  if (theme.value.hasCustomTheme) {
    return {
      color: `var(--color-secondary)`,
      borderLeftColor: `var(--color-secondary)`
    }
  }
  return {
    color: '#6b7280',
    borderLeftColor: '#6b7280'
  }
})

const iconStyle = computed(() => {
  if (isNoMigrado.value) {
    return noMigradoStyle.value
  }
  if (isActive(href.value)) {
    return { color: '#ffffff' }
  }
  if (hasRawChildren.value) {
    return parentStyle.value
  }
  return childStyle.value
})
</script>

<template>
  <li>
    <component
      :is="href ? (isExternal ? 'a' : Link) : 'button'"
      :href="href || undefined"
      :target="isExternal ? '_blank' : undefined"
      :rel="isExternal ? 'noopener' : undefined"
      class="group flex w-full items-center justify-between rounded-md px-2 py-1.5 text-[13px] leading-4 transition-all duration-200"
      :class="[
        isActive(href) ? 'active-item' : '',
        isNoMigrado ? 'no-migrado-item' : '',
        !isActive(href) && !isNoMigrado ? 'normal-item' : ''
      ]"
      :style="isActive(href) ? activeStyle : (isNoMigrado ? noMigradoStyle : (hasRawChildren ? parentStyle : childStyle))"
      @click="toggle"
    >
      <span class="truncate flex items-center gap-1.5" :title="label">
        <i v-if="isNoMigrado && !collapsed" class="fas fa-exclamation-triangle text-xs" :style="{ color: `var(--color-secondary)` }"></i>
        <i v-if="isNoMigrado && collapsed" class="fas fa-exclamation-triangle text-xs" :style="{ color: `var(--color-secondary)` }"></i>
        
        <span v-if="!collapsed">{{ label }}</span>
        <span v-else class="font-semibold">{{ String(label || '').slice(0,1) }}</span>
      </span>
      
      <i 
        v-if="children.length" 
        class="fas fa-chevron-right ml-2 text-xs transition-all duration-200"
        :class="[open ? 'rotate-90' : '']"
        :style="iconStyle"
      ></i>
    </component>

    <transition name="fade">
      <ul v-if="children.length && open" class="ml-3 border-l pl-2" :style="{ borderColor: `var(--color-primary-700)` }">
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
/* Transición */
.fade-enter-active, .fade-leave-active { 
  transition: all 0.15s ease; 
}
.fade-enter-from, .fade-leave-to { 
  opacity: 0; 
  transform: translateY(-4px); 
}

/* Estilos hover generales */
.normal-item:hover {
  background-color: rgba(255, 255, 255, 0.1);
}

/* Para tema default */
:root {
  --default-hover-bg: #4b5563;
}

/* Scrollbar personalizado */
aside::-webkit-scrollbar {
  width: 4px;
}
aside::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}
aside::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 4px;
}
</style>