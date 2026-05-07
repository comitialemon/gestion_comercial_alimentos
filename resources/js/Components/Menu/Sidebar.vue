<!-- resources/js/Components/Menu/Sidebar.vue -->
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
    <div
      v-if="ui.sidebarMobileOpen"
      class="fixed inset-0 z-40 bg-black/30 lg:hidden"
      @click="ui.closeMobile()"
    />
    <aside
      class="fixed top-[56px] z-50 h-[calc(100vh-56px)] w-64 overflow-y-auto bg-white shadow
             transition-transform duration-200 ease-out
             lg:top-[56px] lg:h-[calc(100vh-56px)] lg:w-64 lg:shadow"
      :class="[
        ui.sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full',
        ui.sidebarOpen ? 'lg:translate-x-0' : 'lg:-translate-x-full',
      ]"
    >
      <nav class="p-1.5">
        <ul class="space-y-0.5">
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