<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useUiStore } from '@/stores/ui'
import MenuNode from '@/Components/Menu/MenuNode.vue'

const page = usePage()
const ui = useUiStore()

const props = defineProps({
  items: { type: Array, default: () => [] },
})

const ctxReady  = computed(() => page.props?.ctx?.ready === true)
const menuItems = computed(() => props.items.length ? props.items : (page.props?.menu ?? []))
</script>

<template>
  <template v-if="ctxReady">
    <!-- Overlay móvil -->
    <div
      v-if="ui.sidebarMobileOpen"
      class="fixed inset-0 z-40 bg-black/30 lg:hidden"
      @click="ui.closeMobile()"
    />

    <!-- Sidebar: móvil como drawer, desktop visible solo si ui.sidebarOpen -->
    <aside
      class="fixed top-[56px] z-50 h-[calc(100vh-56px)] w-72 overflow-y-auto bg-white shadow
             transition-transform duration-200 ease-out
             lg:top-[56px] lg:h-[calc(100vh-56px)] lg:w-72 lg:shadow"
      :class="[
        // MÓVIL: abierto = translate-x-0, cerrado = -translate-x-full
        ui.sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full',
        // DESKTOP: si está cerrado, lo sacamos de pantalla; si está abierto, lo mostramos
        ui.sidebarOpen ? 'lg:translate-x-0' : 'lg:-translate-x-full',
      ]"
    >
      <nav class="p-2">
        <ul class="space-y-1">
          <MenuNode
            v-for="it in menuItems"
            :key="it.id ?? it.Id"
            :node="it"
            :collapsed="false"
            :depth="0"
            :visited="new Set()"
          />
        </ul>
      </nav>
    </aside>
  </template>
</template>
