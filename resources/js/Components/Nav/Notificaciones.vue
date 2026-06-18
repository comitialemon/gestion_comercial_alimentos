<script setup>
import { ref, onMounted, onUnmounted, computed, inject } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const toast = inject('toast')

// 🔥 TEMA DINÁMICO
const theme = computed(() => page.props?.theme || {
    primary: '#1f2937',
    primary_rgb: '31, 41, 55',
    secondary: '#4b5563',
    hasCustomTheme: false,
    text_light: '#ffffff'
})

const notificaciones = ref([])
const cargando = ref(false)
const abierto = ref(false)
const isMobile = ref(window.innerWidth < 768)

// Detectar cambios de tamaño
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

// Contador de notificaciones pendientes
const totalPendientes = computed(() => {
    return notificaciones.value.length
})

// Verificar si hay notificaciones no vistas
const hayNotificaciones = computed(() => {
    return totalPendientes.value > 0
})

// 🔥 ESTILOS DINÁMICOS
const buttonStyle = computed(() => ({
    backgroundColor: 'rgba(255,255,255,0.1)',
    color: 'var(--color-text-light)'
}))

const buttonActiveStyle = computed(() => ({
    backgroundColor: 'rgba(255,255,255,0.25)',
    color: 'var(--color-text-light)'
}))

const headerStyle = computed(() => ({
    backgroundColor: `rgba(var(--color-primary-rgb), 0.1)`,
    borderBottomColor: `rgba(var(--color-primary-rgb), 0.2)`
}))

const headerTextStyle = computed(() => ({
    color: `var(--color-primary)`
}))

const badgeStyle = computed(() => ({
    backgroundColor: `var(--color-primary)`,
    color: `var(--color-text-light)`
}))

const badgePendienteStyle = computed(() => ({
    backgroundColor: `var(--color-primary)`,
    color: `var(--color-text-light)`
}))

const precioStyle = computed(() => ({
    color: `var(--color-primary)`
}))

const footerLinkStyle = computed(() => ({
    color: `var(--color-primary)`
}))

const spinnerStyle = computed(() => ({
    color: `var(--color-primary)`
}))

// Cargar notificaciones pendientes
const cargarNotificaciones = async () => {
    cargando.value = true
    try {
        const response = await axios.get('/api/notificaciones/pendientes')
        notificaciones.value = response.data
    } catch (error) {
        console.error('Error cargando notificaciones:', error)
    } finally {
        cargando.value = false
    }
}

// Aprobar producto
const aprobarProducto = async (votoId) => {
    try {
        await axios.post(`/gestion/inventario/productos-aprobacion/votar/${votoId}`, {
            estado: 'aprobado'
        })
        await cargarNotificaciones()
        if (notificaciones.value.length === 0) {
            abierto.value = false
        }
        toast?.success('✅ Aprobado', 'Producto aprobado correctamente')
    } catch (error) {
        console.error('Error al aprobar:', error)
        toast?.error('❌ Error', 'Error al aprobar el producto')
    }
}

// Rechazar producto
const rechazarProducto = async (votoId) => {
    const comentario = prompt('Ingrese un motivo de rechazo (opcional):')
    try {
        await axios.post(`/gestion/inventario/productos-aprobacion/votar/${votoId}`, {
            estado: 'rechazado',
            comentario: comentario || ''
        })
        await cargarNotificaciones()
        if (notificaciones.value.length === 0) {
            abierto.value = false
        }
        toast?.success('✅ Rechazado', 'Producto rechazado correctamente')
    } catch (error) {
        console.error('Error al rechazar:', error)
        toast?.error('❌ Error', 'Error al rechazar el producto')
    }
}

// Alternar dropdown
const toggleDropdown = () => {
    if (!abierto.value) {
        cargarNotificaciones()
    }
    abierto.value = !abierto.value
}

// Cerrar dropdown al hacer clic fuera
const handleClickOutside = (event) => {
    const element = document.querySelector('.notificaciones-dropdown')
    if (element && !element.contains(event.target)) {
        abierto.value = false
    }
}

// Formatear fecha relativa
const formatearFecha = (fecha) => {
    if (!fecha) return ''
    const date = new Date(fecha)
    const hoy = new Date()
    const diff = hoy - date
    const dias = Math.floor(diff / (1000 * 60 * 60 * 24))
    
    if (dias === 0) return 'Hoy'
    if (dias === 1) return 'Ayer'
    if (dias < 7) return `Hace ${dias} días`
    return date.toLocaleDateString('es-BO')
}

// Recargar notificaciones cada 30 segundos
let intervalo
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    window.addEventListener('resize', handleResize)
    cargarNotificaciones()
    intervalo = setInterval(() => {
        cargarNotificaciones()
    }, 30000)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    window.removeEventListener('resize', handleResize)
    if (intervalo) clearInterval(intervalo)
})
</script>

<template>
    <div class="relative notificaciones-dropdown">
        <!-- Botón campanita -->
        <button 
            @click="toggleDropdown"
            class="relative p-1.5 sm:p-2 rounded-lg transition flex-shrink-0"
            :class="{
                'animate-pulse ring-2 ring-yellow-400 ring-opacity-75': hayNotificaciones,
                'hover:bg-white/10': true
            }"
            :style="hayNotificaciones ? buttonActiveStyle : buttonStyle"
            :title="isMobile ? 'Notificaciones' : 'Notificaciones de aprobación'"
        >
            <i class="fas fa-bell text-xs sm:text-sm"></i>
            <span 
                v-if="totalPendientes > 0" 
                class="absolute -top-1 -right-1 text-white text-[9px] sm:text-[10px] font-bold rounded-full min-w-[16px] sm:min-w-[18px] h-[16px] sm:h-[18px] flex items-center justify-center px-0.5 sm:px-1 animate-bounce"
                :style="badgePendienteStyle"
            >
                {{ totalPendientes > 99 ? '99+' : totalPendientes }}
            </span>
        </button>

        <!-- Dropdown -->
        <div 
            v-if="abierto"
            class="fixed sm:absolute top-16 sm:top-auto sm:right-0 sm:mt-2 w-[calc(100vw-2rem)] sm:w-96 bg-white rounded-lg shadow-xl z-50 overflow-hidden border border-gray-200"
            :class="isMobile ? 'left-1/2 -translate-x-1/2' : 'absolute right-0 mt-2'"
        >
            <!-- Header con colores dinámicos -->
            <div class="px-3 sm:px-4 py-2.5 sm:py-3 border-b" :style="headerStyle">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs sm:text-sm font-semibold" :style="headerTextStyle">
                        <i class="fas fa-bell mr-1 sm:mr-2 text-xs sm:text-sm"></i>
                        Notificaciones
                    </h3>
                    <span v-if="totalPendientes > 0" class="text-[10px] sm:text-xs text-white px-1.5 sm:px-2 py-0.5 rounded-full" :style="badgeStyle">
                        {{ totalPendientes }} pendiente(s)
                    </span>
                </div>
            </div>

            <!-- Lista de notificaciones -->
            <div class="max-h-80 sm:max-h-96 overflow-y-auto">
                <!-- Cargando -->
                <div v-if="cargando" class="flex justify-center py-6 sm:py-8">
                    <i class="fas fa-spinner fa-spin text-base sm:text-lg" :style="spinnerStyle"></i>
                </div>

                <!-- Sin notificaciones -->
                <div v-else-if="totalPendientes === 0" class="text-center py-6 sm:py-8 text-gray-400">
                    <i class="fas fa-bell-slash text-2xl sm:text-3xl mb-1 sm:mb-2 block"></i>
                    <p class="text-[11px] sm:text-sm">No hay notificaciones pendientes</p>
                </div>

                <!-- Lista de productos pendientes -->
                <div v-else>
                    <div 
                        v-for="notif in notificaciones" 
                        :key="notif.IdProductoAprobacionSolicitud"
                        class="p-2.5 sm:p-3 border-b hover:bg-gray-50 transition"
                    >
                        <div class="flex justify-between items-start mb-1.5 sm:mb-2">
                            <div class="flex-1">
                                <p class="text-[12px] sm:text-sm font-semibold text-gray-800 line-clamp-2">
                                    {{ notif.producto?.Detalle || 'Producto' }}
                                </p>
                                <p class="text-[10px] sm:text-xs text-gray-500 mt-1">
                                    <i class="fas fa-box mr-1"></i> Código: {{ notif.producto?.Codigo }}
                                </p>
                                <p class="text-[10px] sm:text-xs text-gray-500 truncate max-w-[200px] sm:max-w-none">
                                    <i class="fas fa-user mr-1"></i> Solicitado por: {{ notif.solicitante?.identificador?.Nombre }}
                                </p>
                                <p class="text-[10px] sm:text-xs text-gray-400 mt-1">
                                    <i class="far fa-clock mr-1"></i> {{ formatearFecha(notif.FechaSolicitud) }}
                                </p>
                                <p class="text-[10px] sm:text-xs font-semibold mt-1" :style="precioStyle">
                                    Precio: {{ Number(notif.producto?.PrecioVenta).toFixed(2) }} Bs
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button 
                                @click="aprobarProducto(notif.IdProductoAprobacionVoto)" 
                                class="flex-1 px-2 sm:px-3 py-1 sm:py-1.5 bg-green-600 text-white text-[10px] sm:text-xs rounded-lg hover:bg-green-700 transition"
                            >
                                <i class="fas fa-check mr-0.5 sm:mr-1"></i> 
                                <span class="hidden xs:inline">Aprobar</span>
                            </button>
                            <button 
                                @click="rechazarProducto(notif.IdProductoAprobacionVoto)" 
                                class="flex-1 px-2 sm:px-3 py-1 sm:py-1.5 bg-red-600 text-white text-[10px] sm:text-xs rounded-lg hover:bg-red-700 transition"
                            >
                                <i class="fas fa-times mr-0.5 sm:mr-1"></i> 
                                <span class="hidden xs:inline">Rechazar</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer con enlace -->
            <div class="px-3 sm:px-4 py-2 sm:py-2.5 border-t bg-gray-50 text-center">
                <Link 
                    href="/gestion/inventario/productos-aprobacion/pendientes" 
                    class="text-[10px] sm:text-xs font-medium transition hover:underline"
                    :style="footerLinkStyle"
                >
                    Ver todos los pendientes 
                    <i class="fas fa-arrow-right ml-1 text-[8px] sm:text-[10px]"></i>
                </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Línea truncada para móvil */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Clase para pantallas extra pequeñas (menos de 480px) */
@media (min-width: 480px) {
    .xs\:inline {
        display: inline;
    }
}

/* 🔥 Animación de pulso para el botón cuando hay notificaciones */
@keyframes pulse-ring {
    0% {
        box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.7);
    }
    70% {
        box-shadow: 0 0 0 6px rgba(234, 179, 8, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(234, 179, 8, 0);
    }
}

.animate-pulse {
    animation: pulse-ring 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Ajuste para que el dropdown no se salga en móvil */
@media (max-width: 768px) {
    .notificaciones-dropdown .fixed {
        top: 56px;
        max-height: calc(100vh - 70px);
        overflow-y: auto;
    }
}
</style>