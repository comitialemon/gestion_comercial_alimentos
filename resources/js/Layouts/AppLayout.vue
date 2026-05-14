<script setup>
import AppNavbar from '@/Components/Nav/AppNavbar.vue'
import Sidebar from '@/Components/Menu/Sidebar.vue'
import SimpleToast from '@/Components/SimpleToast.vue'
import { usePage, router } from '@inertiajs/vue3'
import { useUiStore } from '@/stores/ui'
import { computed, ref, provide, onMounted, onBeforeUnmount } from 'vue'

const page = usePage()
const ui = useUiStore()

const rutaActual = computed(() => page.component)
const rutasSinMenu = [
    'Contexto/Index',
    'Contexto/PuntoVenta',
    'Gestion/Todos/Operador/Login'
]

const mostrarMenu = computed(() => {
    return page.props?.ctx?.ready === true && !rutasSinMenu.includes(rutaActual.value)
})

// Variables de contexto
const tieneContexto = computed(() => page.props?.ctx?.ready === true)
const tienePuntoVenta = computed(() => page.props?.ctx?.punto_venta_id > 0)
const tieneFacturacion = computed(() => page.props?.ctx?.tiene_facturacion === true)

// 🔥 CONTROL DE HISTORIAL - Evita que pueda ir atrás
let historialBloqueado = false

const bloquearHistorial = () => {
    if (historialBloqueado) return
    
    // Agregar una entrada falsa en el historial para evitar que salga de la app
    if (tieneContexto.value && tienePuntoVenta.value) {
        window.history.pushState({ pagina: 'bloqueo' }, '', window.location.href)
        historialBloqueado = true
    }
}

const handlePopState = (event) => {
    // Si ya tiene contexto completo
    if (tieneContexto.value && tienePuntoVenta.value) {
        const path = window.location.pathname
        
        // Si intenta ir a contexto o PDV, redirigir a oficial
        if (path === '/contexto' || path === '/contexto/pdv' || path.startsWith('/contexto/')) {
            router.get('/oficial')
            return
        }
        
        // Si intenta salir de la app (ir a página anterior fuera de la app)
        // Agregar una nueva entrada para mantenerlo dentro
        window.history.pushState({ pagina: 'bloqueo' }, '', '/oficial')
    }
}

// Prevenir navegación con teclas (backspace, Alt+Izquierda, etc.)
const prevenirNavegacionTeclado = (event) => {
    if (tieneContexto.value && tienePuntoVenta.value) {
        // Backspace
        if (event.key === 'Backspace') {
            event.preventDefault()
            return false
        }
        // Alt + Izquierda (atajo de navegador)
        if (event.altKey && event.key === 'ArrowLeft') {
            event.preventDefault()
            return false
        }
    }
}

onMounted(() => {
    // Agregar entrada de bloqueo al cargar
    bloquearHistorial()
    
    // Escuchar eventos
    window.addEventListener('popstate', handlePopState)
    window.addEventListener('keydown', prevenirNavegacionTeclado)
})

onBeforeUnmount(() => {
    window.removeEventListener('popstate', handlePopState)
    window.removeEventListener('keydown', prevenirNavegacionTeclado)
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
    <AppNavbar />
    <Sidebar v-if="mostrarMenu" />
    <div :class="['transition-all', mostrarMenu && ui.sidebarOpen ? 'lg:ml-72' : 'lg:ml-0']">
      <main class="p-4">
        <slot />
      </main>
    </div>
    <SimpleToast ref="toastRef" />
  </div>
</template>