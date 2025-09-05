<script setup>
import AppNavbar from '@/Components/Nav/AppNavbar.vue'
import Sidebar from '@/Components/Menu/Sidebar.vue'
import { usePage } from '@inertiajs/vue3'
import { useUiStore } from '@/stores/ui'
import { computed } from 'vue'

const page = usePage()
const ui   = useUiStore()

const ctxReady = computed(() => page.props?.ctx?.ready === true)
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-800">
    <!-- Barra superior fija -->
    <AppNavbar />

    <!-- Sidebar -->
    <Sidebar v-if="ctxReady" />

    <!-- Contenido -->
    <div
      :class="[
        'transition-all',
        // 👇 en desktop, solo deja margen cuando el sidebar está abierto
        ctxReady && ui.sidebarOpen ? 'lg:ml-72' : 'lg:ml-0',
      ]"
    >
      <!-- Asegura que el contenido arranca debajo de la barra (56px) -->
      <main class="p-4">
        <slot />
      </main>
    </div>
  </div>
</template>
