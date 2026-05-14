<script setup>
import AppNavbar from '@/Components/Nav/AppNavbar.vue'
import Sidebar from '@/Components/Menu/Sidebar.vue'
import SimpleToast from '@/Components/SimpleToast.vue'
import { usePage } from '@inertiajs/vue3'
import { useUiStore } from '@/stores/ui'
import { computed, ref, provide } from 'vue'

const page = usePage()
const ui = useUiStore()

// Verificar si la ruta actual es de contexto o login
const rutaActual = computed(() => page.component)
const rutasSinMenu = [
  'Contexto/Index',
  'Contexto/PuntoVenta',
  'Gestion/Todos/Operador/Login'
]

const mostrarMenu = computed(() => {
  return page.props?.ctx?.ready === true && !rutasSinMenu.includes(rutaActual.value)
})

// Toast
const toastRef = ref(null)

provide('toast', {
  success: (title, message) => toastRef.value?.success(title, message),
  error: (title, message) => toastRef.value?.error(title, message),
  warning: (title, message) => toastRef.value?.warning(title, message),
  info: (title, message) => toastRef.value?.info(title, message)
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-800">
    <!-- Barra superior fija -->
    <AppNavbar />

    <!-- Sidebar (solo en páginas con contexto) -->
    <Sidebar v-if="mostrarMenu" />

    <!-- Contenido -->
    <div
      :class="[
        'transition-all',
        mostrarMenu && ui.sidebarOpen ? 'lg:ml-72' : 'lg:ml-0',
      ]"
    >
      <main class="p-4">
        <slot />
      </main>
    </div>

    <!-- Toast -->
    <SimpleToast ref="toastRef" />
  </div>
</template>