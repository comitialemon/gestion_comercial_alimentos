<script setup>
import AppNavbar from '@/Components/Nav/AppNavbar.vue'
import Sidebar from '@/Components/Menu/Sidebar.vue'
import SimpleToast from '@/Components/SimpleToast.vue'
import { usePage, router } from '@inertiajs/vue3'
import { useUiStore } from '@/stores/ui'
import { computed, ref, provide, onMounted, onBeforeUnmount, watch } from 'vue'

const page = usePage()
const ui = useUiStore()

// 🔥 TEMA DINÁMICO DEL CLIENTE
const theme = computed(() => page.props.theme || {
    primary: '#1f2937',
    primary_rgb: '31, 41, 55',
    secondary: '#4b5563',
    secondary_rgb: '75, 85, 99',
    background: '#ffffff',
    text: '#000000',
    accent: '#6b7280',
    accent_rgb: '107, 114, 128',
    systemName: 'Sistema Gestion',
    hasCustomTheme: false
})

// Aplicar CSS variables dinámicas
const applyTheme = () => {
    const root = document.documentElement
    
    // Colores principales
    root.style.setProperty('--color-primary', theme.value.primary)
    root.style.setProperty('--color-primary-rgb', theme.value.primary_rgb)
    root.style.setProperty('--color-secondary', theme.value.secondary)
    root.style.setProperty('--color-secondary-rgb', theme.value.secondary_rgb)
    root.style.setProperty('--color-background', theme.value.background)
    root.style.setProperty('--color-text', theme.value.text)
    root.style.setProperty('--color-accent', theme.value.accent)
    root.style.setProperty('--color-accent-rgb', theme.value.accent_rgb)
    
    // Generar variantes del color primario (si no tiene tema custom, usar grises)
    if (theme.value.hasCustomTheme) {
        root.style.setProperty('--color-primary-50', lightenColor(theme.value.primary, 0.95))
        root.style.setProperty('--color-primary-100', lightenColor(theme.value.primary, 0.9))
        root.style.setProperty('--color-primary-200', lightenColor(theme.value.primary, 0.7))
        root.style.setProperty('--color-primary-300', lightenColor(theme.value.primary, 0.5))
        root.style.setProperty('--color-primary-400', lightenColor(theme.value.primary, 0.3))
        root.style.setProperty('--color-primary-500', theme.value.primary)
        root.style.setProperty('--color-primary-600', darkenColor(theme.value.primary, 0.1))
        root.style.setProperty('--color-primary-700', darkenColor(theme.value.primary, 0.2))
        root.style.setProperty('--color-primary-800', darkenColor(theme.value.primary, 0.3))
        root.style.setProperty('--color-primary-900', darkenColor(theme.value.primary, 0.4))
        root.style.setProperty('--color-primary-950', darkenColor(theme.value.primary, 0.5))
    } else {
        // Tema por defecto (grises)
        root.style.setProperty('--color-primary-50', '#f9fafb')
        root.style.setProperty('--color-primary-100', '#f3f4f6')
        root.style.setProperty('--color-primary-200', '#e5e7eb')
        root.style.setProperty('--color-primary-300', '#d1d5db')
        root.style.setProperty('--color-primary-400', '#9ca3af')
        root.style.setProperty('--color-primary-500', '#6b7280')
        root.style.setProperty('--color-primary-600', '#4b5563')
        root.style.setProperty('--color-primary-700', '#374151')
        root.style.setProperty('--color-primary-800', '#1f2937')
        root.style.setProperty('--color-primary-900', '#111827')
        root.style.setProperty('--color-primary-950', '#030712')
    }
}

// Funciones auxiliares para claros/oscuros
function lightenColor(hex, percent) {
    // Simplificado - convierte HEX a RGB y aplica porcentaje
    const rgb = hexToRgb(hex)
    if (!rgb) return hex
    
    const r = Math.min(255, Math.floor(rgb.r + (255 - rgb.r) * percent))
    const g = Math.min(255, Math.floor(rgb.g + (255 - rgb.g) * percent))
    const b = Math.min(255, Math.floor(rgb.b + (255 - rgb.b) * percent))
    
    return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`
}

function darkenColor(hex, percent) {
    const rgb = hexToRgb(hex)
    if (!rgb) return hex
    
    const r = Math.max(0, Math.floor(rgb.r * (1 - percent)))
    const g = Math.max(0, Math.floor(rgb.g * (1 - percent)))
    const b = Math.max(0, Math.floor(rgb.b * (1 - percent)))
    
    return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`
}

function hexToRgb(hex) {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
    } : null
}

// Aplicar tema cuando cambie
watch(() => theme.value, () => {
    applyTheme()
}, { deep: true, immediate: true })

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
    // Aplicar tema al montar
    applyTheme()
    
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
    <div 
        class="min-h-screen"
        :style="{ 
            backgroundColor: 'var(--color-background)', 
            color: 'var(--color-text)' 
        }"
    >
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

<style>
/* 🔥 CSS GLOBAL con variables dinámicas */
:root {
    --color-primary: #1f2937;
    --color-primary-rgb: 31, 41, 55;
    --color-secondary: #4b5563;
    --color-secondary-rgb: 75, 85, 99;
    --color-background: #ffffff;
    --color-text: #000000;
    --color-accent: #6b7280;
    --color-accent-rgb: 107, 114, 128;
}

/* Clases utilitarias usando CSS variables */
.bg-primary {
    background-color: var(--color-primary);
}
.bg-primary-50 {
    background-color: var(--color-primary-50);
}
.bg-primary-100 {
    background-color: var(--color-primary-100);
}
.bg-primary-200 {
    background-color: var(--color-primary-200);
}
.bg-primary-300 {
    background-color: var(--color-primary-300);
}
.bg-primary-400 {
    background-color: var(--color-primary-400);
}
.bg-primary-500 {
    background-color: var(--color-primary-500);
}
.bg-primary-600 {
    background-color: var(--color-primary-600);
}
.bg-primary-700 {
    background-color: var(--color-primary-700);
}
.bg-primary-800 {
    background-color: var(--color-primary-800);
}
.bg-primary-900 {
    background-color: var(--color-primary-900);
}

.text-primary {
    color: var(--color-primary);
}
.text-primary-500 {
    color: var(--color-primary-500);
}
.text-primary-600 {
    color: var(--color-primary-600);
}
.text-primary-700 {
    color: var(--color-primary-700);
}

.hover\:bg-primary-600:hover {
    background-color: var(--color-primary-600);
}
.hover\:bg-primary-700:hover {
    background-color: var(--color-primary-700);
}
.hover\:text-primary-600:hover {
    color: var(--color-primary-600);
}

/* Border */
.border-primary {
    border-color: var(--color-primary);
}
.focus\:ring-primary-500:focus {
    --tw-ring-color: var(--color-primary-500);
}
.focus\:border-primary-500:focus {
    border-color: var(--color-primary-500);
}
</style>