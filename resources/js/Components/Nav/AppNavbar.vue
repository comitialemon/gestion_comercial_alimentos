<script setup>
import { computed, inject, ref, onMounted, onUnmounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useUiStore } from '@/stores/ui'
import Notificaciones from '@/Components/Nav/Notificaciones.vue'

const page = usePage()
const ui = useUiStore()
const toast = inject('toast')

// 🔥 TEMA DINÁMICO
const theme = computed(() => page.props?.theme || {
    primary: '#1f2937',
    secondary: '#4b5563',
    hasCustomTheme: false
})

// Estado para responsive
const isMobile = ref(window.innerWidth < 768)

// Detectar cambios de tamaño
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

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

// Navegar a nueva venta táctil
const irANuevaVenta = () => {
    if (!ctxReady.value) {
        toast?.warning('Contexto requerido', 'Primero selecciona empresa y sucursal')
        return
    }
    router.get('/venta-tactil/nueva')
}
</script>

<template>
    <header 
        class="sticky top-0 z-40 w-full shadow-md transition-colors duration-300"
        :style="{ backgroundColor: 'var(--color-primary)' }"
    >
        <div class="mx-auto flex items-center gap-2 px-2 sm:px-3 py-2">
            
            <!-- Botón menú móvil -->
            <button
                v-if="ctxReady"
                class="lg:hidden -ml-1 p-2 rounded transition flex-shrink-0 text-white"
                :style="{ backgroundColor: 'rgba(255,255,255,0.15)' }"
                @click="openMobileSidebar"
                type="button"
            >
                <i class="fas fa-bars text-base sm:text-lg"></i>
            </button>

            <!-- Botón menú desktop -->
            <button
                v-if="ctxReady"
                class="hidden lg:inline-flex p-2 rounded transition flex-shrink-0 text-white"
                :style="{ backgroundColor: 'rgba(255,255,255,0.15)' }"
                @click="toggleDesktopSidebar"
                type="button"
            >
                <i class="fas fa-bars text-lg"></i>
            </button>

            <!-- Logo / Icono -->
            <div 
                class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                :style="{ backgroundColor: 'var(--color-secondary)' }"
            >
                <i class="fas fa-store text-white text-sm"></i>
            </div>

            <!-- Información de empresa y sucursal -->
            <div class="leading-tight select-none text-white min-w-0 flex-shrink">
                <div class="font-semibold uppercase tracking-wide text-xs sm:text-sm lg:text-base truncate max-w-[120px] sm:max-w-[200px] lg:max-w-[300px]">
                    {{ empresaNombre || 'SELECCIONE EMPRESA' }}
                </div>
                <div class="text-[10px] sm:text-xs opacity-90 truncate max-w-[120px] sm:max-w-[200px] lg:max-w-[300px]">
                    Sucursal: {{ sucursalNombre || 'SELECCIONE SUCURSAL' }}
                </div>
            </div>

            <!-- BOTÓN NUEVA VENTA (centrado) -->
            <button
                v-if="ctxReady"
                @click="irANuevaVenta"
                class="mx-auto px-3 sm:px-4 py-1.5 rounded-lg transition text-primary-900 font-bold text-xs sm:text-sm flex items-center gap-1 sm:gap-2 flex-shrink-0 shadow-md hover:shadow-lg"
                :style="{ 
                    background: theme.hasCustomTheme 
                        ? `linear-gradient(135deg, var(--color-secondary) 0%, var(--color-primary) 100%)` 
                        : 'linear-gradient(135deg, #facc15 0%, #eab308 100%)'
                }"
                @mouseenter="e => e.target.style.background = theme.hasCustomTheme 
                    ? `linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%)`
                    : 'linear-gradient(135deg, #eab308 0%, #ca8a04 100%)'"
                @mouseleave="e => e.target.style.background = theme.hasCustomTheme 
                    ? `linear-gradient(135deg, var(--color-secondary) 0%, var(--color-primary) 100%)`
                    : 'linear-gradient(135deg, #facc15 0%, #eab308 100%)'"
                title="Nueva venta rápida"
            >
                <i class="fas fa-cash-register text-sm sm:text-base"></i>
                <span class="hidden xs:inline font-medium">Nueva Venta</span>
            </button>

            <!-- Spacer para mantener equilibrio (solo en desktop) -->
            <div class="hidden lg:block w-10"></div>

            <!-- NOTIFICACIONES -->
            <Notificaciones v-if="ctxReady" />

            <!-- Operador y Logout -->
            <div class="flex items-center gap-1 sm:gap-2 flex-shrink-0">
                <div 
                    class="text-[10px] sm:text-xs lg:text-sm flex items-center gap-1 sm:gap-2 px-2 sm:px-3 py-1 sm:py-1.5 rounded-full text-white whitespace-nowrap transition-colors"
                    :style="{ backgroundColor: 'rgba(0,0,0,0.2)' }"
                >
                    <i class="fas fa-user-circle text-xs sm:text-sm"></i>
                    <span class="hidden sm:inline font-medium max-w-[80px] lg:max-w-[150px] truncate">{{ operadorNombre || 'SIN OPERADOR' }}</span>
                </div>
                
                <button 
                    @click="logout"
                    class="px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg transition text-white text-xs sm:text-sm flex items-center gap-1 sm:gap-2 whitespace-nowrap"
                    :style="{ backgroundColor: 'rgba(0,0,0,0.2)' }"
                    @mouseenter="e => e.target.style.backgroundColor = 'rgba(0,0,0,0.35)'"
                    @mouseleave="e => e.target.style.backgroundColor = 'rgba(0,0,0,0.2)'"
                    title="Cerrar sesión"
                >
                    <i class="fas fa-sign-out-alt text-xs sm:text-sm"></i>
                    <span class="hidden sm:inline">Salir</span>
                </button>
            </div>
        </div>
        
        <!-- 🔥 Indicador de tema default (solo supervisores) -->
        <div 
            v-if="!theme.hasCustomTheme && page.props?.auth?.operador?.tipo_id === 1"
            class="text-center text-[9px] py-0.5 text-white"
            :style="{ backgroundColor: 'rgba(0,0,0,0.3)' }"
        >
            <i class="fas fa-info-circle mr-1"></i>
            Esta empresa usa el tema por defecto (gris/negro/blanco). 
            Personaliza los colores desde Configuración → Tema.
        </div>
    </header>
</template>

<style scoped>
/* Clase para pantallas extra pequeñas (menos de 480px) */
@media (min-width: 480px) {
    .xs\:inline {
        display: inline;
    }
}
</style>