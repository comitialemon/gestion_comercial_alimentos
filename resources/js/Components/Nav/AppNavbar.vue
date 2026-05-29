<script setup>
import { computed, inject, ref, onMounted, onUnmounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useUiStore } from '@/stores/ui'
import Notificaciones from '@/Components/Nav/Notificaciones.vue'

const page = usePage()
const ui = useUiStore()
const toast = inject('toast')

// 🔥 Computed para saber si es vendedor de mostrador (tipo_detalle = 'VentaMostrador')
const esVendedorMostrador = computed(() => {
    return page.props?.auth?.operador?.tipo_detalle === 'VentaMostrador'
})

// Alternativa por ID (IdOperadorTipo = 6)
const esTipoVendedor = computed(() => {
    return page.props?.auth?.operador?.tipo_id === 6
})

// Puedes usar cualquiera de las dos, o combinarlas
const puedeMostrarBotonVenta = computed(() => {
    return ctxReady.value && (esVendedorMostrador.value || esTipoVendedor.value)
})

// 🔥 TEMA DINÁMICO
const theme = computed(() => page.props?.theme || {
    primary: '#1f2937',
    primary_rgb: '31, 41, 55',
    secondary: '#4b5563',
    hasCustomTheme: false,
    logo: null
})

// Estado para responsive
const isMobile = ref(window.innerWidth < 768)

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

const irANuevaVenta = () => {
    if (!ctxReady.value) {
        toast?.warning('Contexto requerido', 'Primero selecciona empresa y sucursal')
        return
    }
    router.get('/venta-tactil/nueva')
}

const navbarBgStyle = computed(() => {
    if (theme.value.hasCustomTheme) {
        return { backgroundColor: 'var(--color-primary-800)' }
    }
    return { backgroundColor: '#1f2937' }
})
</script>

<template>
    <header 
        class="sticky top-0 z-40 w-full shadow-md transition-colors duration-300"
        :style="navbarBgStyle"
    >
        <div class="mx-auto flex items-center justify-between gap-2 px-3 sm:px-4 py-2">
            
            <!-- 🔹 SECCIÓN IZQUIERDA: Botón menú + Logo + Info empresa -->
            <div class="flex items-center gap-2 flex-shrink-0 min-w-0">
                <!-- Botón menú -->
                <button
                    v-if="ctxReady"
                    class="lg:hidden p-2 rounded-lg transition flex-shrink-0 text-white"
                    :style="{ backgroundColor: 'rgba(255,255,255,0.15)' }"
                    @click="openMobileSidebar"
                    type="button"
                >
                    <i class="fas fa-bars text-base"></i>
                </button>

                <button
                    v-if="ctxReady"
                    class="hidden lg:inline-flex p-2 rounded-lg transition flex-shrink-0 text-white"
                    :style="{ backgroundColor: 'rgba(255,255,255,0.15)' }"
                    @click="toggleDesktopSidebar"
                    type="button"
                >
                    <i class="fas fa-bars text-lg"></i>
                </button>

                <!-- Logo -->
                <div v-if="theme.logo" class="flex-shrink-0">
                    <img 
                        :src="theme.logo" 
                        alt="Logo" 
                        class="h-10 md:h-12 w-auto object-contain"
                    />
                </div>
                
                <!-- Icono por defecto -->
                <div 
                    v-else
                    class="w-8 h-8 md:w-10 md:h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                    :style="{ backgroundColor: 'var(--color-secondary)' }"
                >
                    <i class="fas fa-store text-white text-sm md:text-base"></i>
                </div>

                <!-- Info empresa y sucursal -->
                <div class="leading-tight select-none text-white">
                    <div class="font-semibold uppercase tracking-wide text-xs sm:text-sm whitespace-nowrap">
                        {{ empresaNombre || 'SELECCIONE EMPRESA' }}
                    </div>
                    <div class="text-[10px] sm:text-xs opacity-90 whitespace-nowrap">
                        {{ sucursalNombre || 'SELECCIONE SUCURSAL' }}
                    </div>
                </div>
            </div>

            <!-- 🔹 SECCIÓN CENTRO: Botón Nueva Venta (SOLO para vendedores) -->
            <div class="flex-1 flex justify-center">
                <button
                    v-if="ctxReady && puedeMostrarBotonVenta"
                    @click="irANuevaVenta"
                    class="px-4 sm:px-6 py-2 rounded-xl transition text-white font-semibold text-xs sm:text-sm flex items-center gap-2 shadow-md hover:shadow-lg whitespace-nowrap"
                    :style="{ 
                        background: `linear-gradient(135deg, var(--color-secondary) 0%, var(--color-primary) 100%)`
                    }"
                    @mouseenter="e => e.target.style.background = `linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%)`"
                    @mouseleave="e => e.target.style.background = `linear-gradient(135deg, var(--color-secondary) 0%, var(--color-primary) 100%)`"
                    title="Nueva venta rápida"
                >
                    <i class="fas fa-cash-register text-sm sm:text-base"></i>
                    <span class="font-medium hidden sm:inline">Nueva Venta</span>
                    <span class="font-medium sm:hidden">Venta</span>
                </button>
            </div>

            <!-- 🔹 SECCIÓN DERECHA: Notificaciones + Operador + Logout -->
            <div class="flex items-center gap-2 flex-shrink-0">
                <Notificaciones v-if="ctxReady" />

                <!-- 🔥 OPERADOR - TEXTO MÁS PEQUEÑO Y MÁS ANCHO -->
                <div 
                    class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full text-white"
                    :style="{ backgroundColor: 'rgba(0,0,0,0.2)' }"
                >
                    <i class="fas fa-user-circle text-xs sm:text-sm"></i>
                    <span class="font-medium text-[11px] sm:text-xs max-w-[180px] md:max-w-[250px] truncate">
                        {{ operadorNombre || 'Operador' }}
                    </span>
                </div>
                
                <!-- Ícono de usuario en móvil -->
                <div 
                    class="flex sm:hidden items-center justify-center w-8 h-8 rounded-full text-white"
                    :style="{ backgroundColor: 'rgba(0,0,0,0.2)' }"
                    :title="operadorNombre"
                >
                    <i class="fas fa-user-circle text-sm"></i>
                </div>
                
                <button 
                    @click="logout"
                    class="p-2 rounded-lg transition text-white"
                    :style="{ backgroundColor: 'rgba(0,0,0,0.2)' }"
                    @mouseenter="e => e.target.style.backgroundColor = 'rgba(0,0,0,0.35)'"
                    @mouseleave="e => e.target.style.backgroundColor = 'rgba(0,0,0,0.2)'"
                    title="Cerrar sesión"
                >
                    <i class="fas fa-sign-out-alt text-sm sm:text-base"></i>
                </button>
            </div>
        </div>
        
        <!-- Indicador de tema default -->
        <div 
            v-if="!theme.hasCustomTheme && page.props?.auth?.operador?.tipo_id === 1"
            class="text-center text-[9px] py-0.5 text-white"
            :style="{ backgroundColor: 'rgba(0,0,0,0.3)' }"
        >
            <i class="fas fa-info-circle mr-1"></i>
            Esta empresa usa el tema por defecto. Personaliza los colores en Configuración → Tema.
        </div>
    </header>
</template>

<style scoped>
img {
    max-height: 48px;
    width: auto;
    object-fit: contain;
}

@media (max-width: 640px) {
    img {
        max-height: 40px;
    }
}

button {
    transition: all 0.2s ease;
}
</style>