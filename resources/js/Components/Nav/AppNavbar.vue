<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useUiStore } from '@/stores/ui'

const page = usePage()
const ui = useUiStore()

const empresaNombre  = computed(() => page.props?.empresaNombre ?? '')
const sucursalNombre = computed(() => page.props?.sucursalNombre ?? '')
const operadorNombre = computed(() => page.props?.operadorNombre ?? '')
const ctxReady       = computed(() => page.props?.ctx?.ready === true)

const openMobileSidebar = () => ui.openMobile()
const toggleDesktopSidebar = () => ui.toggleSidebar()
</script>

<template>
  <header class="sticky top-0 z-40 w-full bg-[#61131a] text-white">
    <div class="mx-auto flex items-center gap-2 px-3 py-2">

     <!-- Botón móvil -->
      <button
        v-if="ctxReady"
        class="lg:hidden -ml-1 p-2 rounded hover:bg-white/10"
        @click="openMobileSidebar"
        type="button"
      >☰</button>

      <!-- Botón desktop -->
      <button
        v-if="ctxReady"
        class="hidden lg:inline-flex p-2 rounded hover:bg-white/10"
        @click="toggleDesktopSidebar"
        type="button"
      >≡</button>


      <div class="leading-tight select-none">
        <div class="font-semibold uppercase tracking-wide">
          {{ empresaNombre || 'EMPRESA' }}
        </div>
        <div class="text-xs opacity-90">SUC. {{ sucursalNombre || '-' }}</div>
      </div>

      <div class="ml-auto text-xs sm:text-sm opacity-90 select-none">
        👤 {{ operadorNombre || '' }}
      </div>
    </div>
  </header>
</template>
