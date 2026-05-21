<script setup>
import { computed, inject } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useUiStore } from '@/stores/ui'
import Notificaciones from '@/Components/Nav/Notificaciones.vue'

const page = usePage()
const ui = useUiStore()
const toast = inject('toast')

// Usar los valores directamente de page.props
const empresaNombre  = computed(() => page.props?.empresaNombre || sessionStorage.getItem('empresaNombre') || '')
const sucursalNombre = computed(() => page.props?.sucursalNombre || sessionStorage.getItem('sucursalNombre') || '')
const operadorNombre = computed(() => page.props?.operadorNombre || sessionStorage.getItem('operadorNombre') || '')
const ctxReady       = computed(() => page.props?.ctx?.ready === true)

// Guardar en sessionStorage para persistencia
const setStorage = () => {
    if (page.props?.empresaNombre) sessionStorage.setItem('empresaNombre', page.props.empresaNombre)
    if (page.props?.sucursalNombre) sessionStorage.setItem('sucursalNombre', page.props.sucursalNombre)
    if (page.props?.operadorNombre) sessionStorage.setItem('operadorNombre', page.props.operadorNombre)
}
setStorage()

const openMobileSidebar = () => ui.openMobile()
const toggleDesktopSidebar = () => ui.toggleSidebar()

const logout = () => {
    if (toast) toast.info('Cerrando sesión', 'Hasta luego')
    sessionStorage.clear()
    router.post('/logout')
}
</script>

<template>
  <!-- Color guindo más oscuro: #61131a (guindo-900) -->
  <header class="sticky top-0 z-40 w-full shadow-md" style="background-color: #61131a;">
    <div class="mx-auto flex items-center gap-2 px-3 py-2">

      <!-- Botón móvil -->
      <button
        v-if="ctxReady"
        class="lg:hidden -ml-1 p-2 rounded hover:bg-opacity-80 transition"
        style="color: white; background-color: rgba(255,255,255,0.1);"
        @click="openMobileSidebar"
        type="button"
      >
        <i class="fas fa-bars text-lg"></i>
      </button>

      <!-- Botón desktop -->
      <button
        v-if="ctxReady"
        class="hidden lg:inline-flex p-2 rounded hover:bg-opacity-80 transition"
        style="color: white; background-color: rgba(255,255,255,0.1);"
        @click="toggleDesktopSidebar"
        type="button"
      >
        <i class="fas fa-bars text-lg"></i>
      </button>

      <!-- Información de empresa y sucursal -->
      <div class="leading-tight select-none text-white">
        <div class="font-semibold uppercase tracking-wide text-sm sm:text-base">
          {{ empresaNombre || 'SELECCIONE EMPRESA' }}
        </div>
        <div class="text-xs opacity-90">
          Sucursal: {{ sucursalNombre || 'SELECCIONE SUCURSAL' }}
        </div>
      </div>

      <!-- Spacer -->
      <div class="flex-1"></div>

      <!-- 🔥 NOTIFICACIONES (solo cuando hay contexto) -->
      <Notificaciones v-if="ctxReady" />

      <!-- Operador y Logout -->
      <div class="flex items-center gap-3">
        <div class="text-xs sm:text-sm flex items-center gap-2 px-3 py-1.5 rounded-full text-white" style="background-color: #4a0f14;">
          <i class="fas fa-user-circle"></i>
          <span class="hidden sm:inline font-medium">{{ operadorNombre || 'SIN OPERADOR' }}</span>
        </div>
        
        <button 
          @click="logout"
          class="px-3 py-1.5 rounded-lg transition text-white text-sm flex items-center gap-2"
          style="background-color: #4a0f14;"
          @mouseenter="e => e.target.style.backgroundColor = '#3a0a0f'"
          @mouseleave="e => e.target.style.backgroundColor = '#4a0f14'"
          title="Cerrar sesión"
        >
          <i class="fas fa-sign-out-alt"></i>
          <span class="hidden sm:inline">Salir</span>
        </button>
      </div>
    </div>
  </header>
</template>