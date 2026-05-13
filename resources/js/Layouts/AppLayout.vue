<script setup>
import AppNavbar from '@/Components/Nav/AppNavbar.vue'
import Sidebar from '@/Components/Menu/Sidebar.vue'
import SimpleToast from '@/Components/SimpleToast.vue'  // 👈 IMPORTAR TOAST
import { usePage } from '@inertiajs/vue3'
import { useUiStore } from '@/stores/ui'
import { computed, ref, provide } from 'vue'  // 👈 AGREGAR ref y provide
  
const page = usePage()
const ui   = useUiStore()

const ctxReady = computed(() => page.props?.ctx?.ready === true)

// 👇 AGREGAR ESTO PARA EL TOAST
const toastRef = ref(null)

provide('toast', {
  success: (title, message) => toastRef.value?.success(title, message),
  error: (title, message) => toastRef.value?.error(title, message),
  warning: (title, message) => toastRef.value?.warning(title, message),
  info: (title, message) => toastRef.value?.info(title, message)
})
// 👆 HASTA AQUÍ

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
        ctxReady && ui.sidebarOpen ? 'lg:ml-72' : 'lg:ml-0',
      ]"
    >
      <main class="p-4">
        <slot />
      </main>
    </div>

    <!-- 👇 AGREGAR EL TOAST AQUÍ (al final, fuera del main) -->
    <SimpleToast ref="toastRef" />
  </div>
</template>