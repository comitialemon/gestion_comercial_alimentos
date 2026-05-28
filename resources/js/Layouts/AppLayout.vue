<script setup>
import AppNavbar from '@/Components/Nav/AppNavbar.vue'
import Sidebar from '@/Components/Menu/Sidebar.vue'
import SimpleToast from '@/Components/SimpleToast.vue'
import { usePage, router } from '@inertiajs/vue3'
import { useUiStore } from '@/stores/ui'
import { computed, ref, provide, onMounted, onBeforeUnmount, watch } from 'vue'

const page = usePage()
const ui = useUiStore()

// Asegurar que ui store existe y tiene sidebarOpen
if (!ui.sidebarOpen) {
    ui.sidebarOpen = false
}

// 🔥 TEMA DINÁMICO DEL CLIENTE (con los dos tipos de texto)
const theme = computed(() => page.props.theme || {
    primary: '#1f2937',
    primary_rgb: '31, 41, 55',
    secondary: '#4b5563',
    secondary_rgb: '75, 85, 99',
    accent: '#6b7280',
    accent_rgb: '107, 114, 128',
    background: '#ffffff',
    text_dark: '#111827',   // Texto sobre fondos claros
    text_light: '#ffffff',   // Texto sobre fondos oscuros
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
    root.style.setProperty('--color-accent', theme.value.accent)
    root.style.setProperty('--color-accent-rgb', theme.value.accent_rgb)
    root.style.setProperty('--color-background', theme.value.background)
    
    // 🔥 DOS TIPOS DE TEXTO
    root.style.setProperty('--color-text-dark', theme.value.text_dark)
    root.style.setProperty('--color-text-light', theme.value.text_light)
    
    // Generar variantes del color primario
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

// 🔥 CONTROL DE HISTORIAL
let historialBloqueado = false

const bloquearHistorial = () => {
    if (historialBloqueado) return
    
    if (tieneContexto.value && tienePuntoVenta.value) {
        window.history.pushState({ pagina: 'bloqueo' }, '', window.location.href)
        historialBloqueado = true
    }
}

const handlePopState = () => {
    if (tieneContexto.value && tienePuntoVenta.value) {
        const path = window.location.pathname
        
        if (path === '/contexto' || path === '/contexto/pdv' || path.startsWith('/contexto/')) {
            router.get('/oficial')
            return
        }
        
        window.history.pushState({ pagina: 'bloqueo' }, '', '/oficial')
    }
}

const prevenirNavegacionTeclado = (event) => {
    if (tieneContexto.value && tienePuntoVenta.value) {
        if (event.key === 'Backspace') {
            event.preventDefault()
            return false
        }
        if (event.altKey && event.key === 'ArrowLeft') {
            event.preventDefault()
            return false
        }
    }
}

onMounted(() => {
    applyTheme()
    bloquearHistorial()
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
            color: 'var(--color-text-dark)' 
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
    --color-accent: #6b7280;
    --color-accent-rgb: 107, 114, 128;
    --color-background: #ffffff;
    --color-text-dark: #111827;
    --color-text-light: #ffffff;
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
.bg-primary-950 {
    background-color: var(--color-primary-950);
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

/* 🔥 CLASES PARA TEXTOS */
.text-dark {
    color: var(--color-text-dark);
}
.text-light {
    color: var(--color-text-light);
}

/* Hovers */
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
.border-primary-200 {
    border-color: var(--color-primary-200);
}
.border-primary-300 {
    border-color: var(--color-primary-300);
}

/* Focus */
.focus\:ring-primary-500:focus {
    --tw-ring-color: var(--color-primary-500);
}
.focus\:border-primary-500:focus {
    border-color: var(--color-primary-500);
}

/* Scrollbar personalizada para el sidebar */
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
aside::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}
</style>