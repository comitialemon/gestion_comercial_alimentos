<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'

const notificaciones = ref([])
const cargando = ref(false)
const abierto = ref(false)

// Contador de notificaciones pendientes
const totalPendientes = computed(() => {
    return notificaciones.value.length
})

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
        await axios.post(`/gestion/productos-aprobacion/votar/${votoId}`, {
            estado: 'aprobado'
        })
        await cargarNotificaciones()
        window.location.reload()
    } catch (error) {
        console.error('Error al aprobar:', error)
        alert('Error al aprobar el producto')
    }
}

// Rechazar producto
const rechazarProducto = async (votoId) => {
    const comentario = prompt('Ingrese un motivo de rechazo (opcional):')
    try {
        await axios.post(`/gestion/productos-aprobacion/votar/${votoId}`, {
            estado: 'rechazado',
            comentario: comentario || ''
        })
        await cargarNotificaciones()
        window.location.reload()
    } catch (error) {
        console.error('Error al rechazar:', error)
        alert('Error al rechazar el producto')
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

// Escuchar clics fuera del dropdown
onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    // Cargar notificaciones al montar
    cargarNotificaciones()
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

// Recargar notificaciones cada 60 segundos
let intervalo
onMounted(() => {
    intervalo = setInterval(() => {
        if (abierto.value) {
            cargarNotificaciones()
        }
    }, 60000)
})

onUnmounted(() => {
    if (intervalo) clearInterval(intervalo)
})
</script>

<template>
    <div class="relative notificaciones-dropdown">
        <!-- Botón campanita -->
        <button 
            @click="toggleDropdown"
            class="relative p-2 rounded-lg transition"
            style="color: white; background-color: rgba(255,255,255,0.1);"
            :class="{ 'bg-white/20': abierto }"
            title="Notificaciones"
        >
            <i class="fas fa-bell text-sm"></i>
            <span 
                v-if="totalPendientes > 0" 
                class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"
            >
                {{ totalPendientes > 99 ? '99+' : totalPendientes }}
            </span>
        </button>

        <!-- Dropdown de notificaciones -->
        <div 
            v-if="abierto"
            class="absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-xl z-50 overflow-hidden border border-gray-200"
        >
            <!-- Header -->
            <div class="px-4 py-3 border-b" style="background-color: #fdf2f2;">
                <h3 class="text-sm font-semibold" style="color: #61131a;">
                    <i class="fas fa-bell mr-2"></i>
                    Notificaciones
                    <span v-if="totalPendientes > 0" class="ml-auto text-xs text-white px-2 py-0.5 rounded-full" style="background-color: #61131a;">
                        {{ totalPendientes }} pendiente(s)
                    </span>
                </h3>
            </div>

            <!-- Lista de notificaciones -->
            <div class="max-h-96 overflow-y-auto">
                <!-- Cargando -->
                <div v-if="cargando" class="flex justify-center py-8">
                    <i class="fas fa-spinner fa-spin" style="color: #61131a;"></i>
                </div>

                <!-- Sin notificaciones -->
                <div v-else-if="totalPendientes === 0" class="text-center py-8 text-gray-400">
                    <i class="fas fa-bell-slash text-3xl mb-2 block"></i>
                    <p class="text-sm">No hay notificaciones pendientes</p>
                </div>

                <!-- Lista de productos pendientes -->
                <div v-else>
                    <div 
                        v-for="notif in notificaciones" 
                        :key="notif.IdProductoAprobacionSolicitud"
                        class="p-3 border-b hover:bg-gray-50 transition"
                    >
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-800">
                                    {{ notif.producto?.Detalle || 'Producto' }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-box mr-1"></i> Código: {{ notif.producto?.Codigo }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    <i class="fas fa-user mr-1"></i> Solicitado por: {{ notif.solicitante?.identificador?.Nombre }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <i class="far fa-clock mr-1"></i> {{ formatearFecha(notif.FechaSolicitud) }}
                                </p>
                                <p class="text-xs font-semibold mt-1" style="color: #61131a;">
                                    Precio: {{ Number(notif.producto?.PrecioVenta).toFixed(2) }} Bs
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button 
                                @click="aprobarProducto(notif.IdProductoAprobacionVoto)" 
                                class="flex-1 px-3 py-1 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700 transition"
                            >
                                <i class="fas fa-check mr-1"></i> Aprobar
                            </button>
                            <button 
                                @click="rechazarProducto(notif.IdProductoAprobacionVoto)" 
                                class="flex-1 px-3 py-1 bg-red-600 text-white text-xs rounded-lg hover:bg-red-700 transition"
                            >
                                <i class="fas fa-times mr-1"></i> Rechazar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer con enlace a lista completa -->
            <div class="px-4 py-2 border-t bg-gray-50 text-center">
                <Link 
                    href="/gestion/productos-aprobacion/pendientes" 
                    class="text-xs font-medium transition"
                    style="color: #61131a;"
                >
                    Ver todos los pendientes <i class="fas fa-arrow-right ml-1"></i>
                </Link>
            </div>
        </div>
    </div>
</template>