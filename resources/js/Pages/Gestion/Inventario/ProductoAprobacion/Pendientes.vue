<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, inject, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    solicitudes: Array
})

// ==================== ESTADO ====================
const modalRechazoOpen = ref(false)
const solicitudSeleccionada = ref(null)
const comentario = ref('')
const loading = ref(false)
const isMobile = ref(false)

// ==================== DETECTAR RESPONSIVE ====================
const handleResize = () => {
    isMobile.value = window.innerWidth < 640
}

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

// ==================== FUNCIONES ====================

// 🔥 Aprobar directamente (sin modal)
const aprobarProducto = async (solicitud) => {
    const voto = solicitud.votos[0]
    if (!voto) return
    
    loading.value = true
    try {
        await axios.post(`/gestion/inventario/productos-aprobacion/votar/${voto.IdProductoAprobacionVoto}`, {
            estado: 'aprobado',
            comentario: ''
        })
        toast?.success('✅ Producto aprobado', `"${solicitud.producto?.Detalle}" ha sido aprobado correctamente`)
        setTimeout(() => {
            window.location.reload()
        }, 800)
    } catch (err) {
        toast?.error('❌ Error', err.response?.data?.message || 'Error al aprobar el producto')
    } finally {
        loading.value = false
    }
}

// 🔥 Abrir modal para rechazar
const abrirModalRechazo = (solicitud) => {
    solicitudSeleccionada.value = solicitud
    comentario.value = ''
    modalRechazoOpen.value = true
}

// 🔥 Procesar rechazo
const procesarRechazo = async () => {
    const voto = solicitudSeleccionada.value.votos[0]
    if (!voto) return
    
    loading.value = true
    try {
        await axios.post(`/gestion/inventario/productos-aprobacion/votar/${voto.IdProductoAprobacionVoto}`, {
            estado: 'rechazado',
            comentario: comentario.value
        })
        modalRechazoOpen.value = false
        toast?.error('❌ Producto rechazado', `"${solicitudSeleccionada.value.producto?.Detalle}" ha sido rechazado`)
        setTimeout(() => {
            window.location.reload()
        }, 800)
    } catch (err) {
        toast?.error('❌ Error', err.response?.data?.message || 'Error al rechazar el producto')
    } finally {
        loading.value = false
    }
}

const verDetalle = (id) => {
    router.get(`/gestion/inventario/productos-aprobacion/ver/${id}`)
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: `var(--color-primary-50)` }">
        <div class="py-2 px-2 sm:py-3 sm:px-3 lg:py-4 lg:px-6">
            <div class="max-w-5xl mx-auto">
                <!-- Header Responsive -->
                <div class="flex items-center gap-2 sm:gap-3 mb-3 sm:mb-6">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                         :style="{ backgroundColor: `var(--color-primary-100)` }">
                        <i class="fas fa-clock text-primary-600 text-sm sm:text-xl"
                           :style="{ color: `var(--color-primary-600)` }"></i>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-sm sm:text-xl font-bold text-gray-800 truncate">Productos Pendientes de Aprobación</h1>
                        <p class="text-[9px] sm:text-xs text-gray-500 truncate">Revisa y aprueba o rechaza los productos nuevos</p>
                    </div>
                </div>

                <!-- Lista de solicitudes -->
                <div class="space-y-3 sm:space-y-4">
                    <div v-for="solicitud in solicitudes" :key="solicitud.IdProductoAprobacionSolicitud" 
                         class="bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition">
                        <div class="p-3 sm:p-5">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-3">
                                <!-- Info principal -->
                                <div class="flex-1 min-w-0 w-full">
                                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mb-1.5 sm:mb-2">
                                        <i class="fas fa-box text-primary-500 text-[10px] sm:text-sm"></i>
                                        <h2 class="text-sm sm:text-lg font-semibold text-gray-800 truncate flex-1">
                                            {{ solicitud.producto?.Detalle || 'Sin nombre' }}
                                        </h2>
                                        <span class="px-1.5 sm:px-2 py-0.5 text-[8px] sm:text-xs rounded-full bg-yellow-100 text-yellow-800 flex-shrink-0">
                                            Pendiente
                                        </span>
                                    </div>
                                    
                                    <!-- Grid de información -->
                                    <div class="grid grid-cols-1 xs:grid-cols-2 gap-1 sm:gap-2 text-[10px] sm:text-sm text-gray-600 mb-2 sm:mb-3">
                                        <div class="truncate">
                                            <span class="font-medium">Código:</span> 
                                            {{ solicitud.producto?.Codigo || '-' }}
                                        </div>
                                        <div class="truncate">
                                            <span class="font-medium">Precio:</span> 
                                            <span class="font-semibold" :style="{ color: `var(--color-primary-600)` }">
                                                {{ Number(solicitud.producto?.PrecioVenta).toFixed(2) }} Bs
                                            </span>
                                        </div>
                                        <div class="truncate col-span-1 xs:col-span-2">
                                            <span class="font-medium">Creado por:</span> 
                                            {{ solicitud.solicitante?.identificador?.Nombre || '-' }}
                                        </div>
                                        <div class="truncate col-span-1 xs:col-span-2">
                                            <span class="font-medium">Fecha solicitud:</span> 
                                            {{ formatearFecha(solicitud.FechaSolicitud) }}
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Botones de acción -->
                                <div class="flex flex-wrap gap-1.5 sm:gap-2 w-full sm:w-auto flex-shrink-0">
                                    <button @click="verDetalle(solicitud.producto.IdDetalleProducto)" 
                                            class="flex-1 sm:flex-none px-2 sm:px-3 py-1 sm:py-1.5 text-[9px] sm:text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center justify-center gap-0.5 sm:gap-1">
                                        <i class="fas fa-eye text-[8px] sm:text-xs"></i>
                                        <span class="hidden xs:inline">Ver</span>
                                    </button>
                                    
                                    <!-- 🔥 Botón Aprobar (sin modal) -->
                                    <button @click="aprobarProducto(solicitud)" 
                                            :disabled="loading"
                                            class="flex-1 sm:flex-none px-2 sm:px-3 py-1 sm:py-1.5 text-[9px] sm:text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50 flex items-center justify-center gap-0.5 sm:gap-1">
                                        <i v-if="loading" class="fas fa-spinner fa-spin text-[8px] sm:text-xs"></i>
                                        <i v-else class="fas fa-check text-[8px] sm:text-xs"></i>
                                        <span class="hidden xs:inline">Aprobar</span>
                                    </button>
                                    
                                    <!-- 🔥 Botón Rechazar (abre modal) -->
                                    <button @click="abrirModalRechazo(solicitud)" 
                                            class="flex-1 sm:flex-none px-2 sm:px-3 py-1 sm:py-1.5 text-[9px] sm:text-xs bg-red-600 text-white rounded-lg hover:bg-red-700 transition flex items-center justify-center gap-0.5 sm:gap-1">
                                        <i class="fas fa-times text-[8px] sm:text-xs"></i>
                                        <span class="hidden xs:inline">Rechazar</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sin solicitudes -->
                    <div v-if="solicitudes.length === 0" class="bg-white rounded-lg shadow-sm p-6 sm:p-8 text-center">
                        <i class="fas fa-check-circle text-3xl sm:text-4xl text-green-500 mb-2 sm:mb-3 block"></i>
                        <p class="text-[10px] sm:text-sm text-gray-500">No hay productos pendientes de aprobación</p>
                        <p class="text-[8px] sm:text-xs text-gray-400 mt-1">Todos los productos han sido procesados</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🔥 Modal SOLO PARA RECHAZAR -->
        <div v-if="modalRechazoOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="modalRechazoOpen = false">
            <div class="flex items-center justify-center min-h-screen p-3 sm:p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="modalRechazoOpen = false"></div>
                
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-2 sm:mx-0 transform transition-all">
                    <!-- Header - Rojo para rechazo -->
                    <div class="flex items-center justify-between px-3 sm:px-5 py-2.5 sm:py-3 border-b rounded-t-lg bg-red-600">
                        <h3 class="text-[10px] sm:text-sm font-semibold text-white flex items-center gap-2">
                            <i class="fas fa-times-circle"></i>
                            Rechazar Producto
                        </h3>
                        <button @click="modalRechazoOpen = false" class="text-white/80 hover:text-white transition text-sm">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <!-- Body -->
                    <div class="p-3 sm:p-5">
                        <div class="mb-2 sm:mb-3">
                            <p class="text-[10px] sm:text-sm text-gray-700">
                                <span class="font-medium">Producto:</span>
                                <span class="font-semibold text-gray-800">{{ solicitudSeleccionada?.producto?.Detalle }}</span>
                            </p>
                            <p class="text-[10px] sm:text-sm text-gray-700 mt-0.5">
                                <span class="font-medium">Código:</span>
                                <span class="font-mono text-gray-600">{{ solicitudSeleccionada?.producto?.Codigo }}</span>
                            </p>
                            <p class="text-[10px] sm:text-sm text-gray-700 mt-0.5">
                                <span class="font-medium">Precio:</span>
                                <span class="font-semibold" :style="{ color: `var(--color-primary-600)` }">
                                    {{ Number(solicitudSeleccionada?.producto?.PrecioVenta).toFixed(2) }} Bs
                                </span>
                            </p>
                        </div>
                        
                        <div class="mb-3 sm:mb-4">
                            <label class="block text-[10px] sm:text-sm font-medium text-gray-700 mb-0.5 sm:mb-1">
                                Comentario <span class="text-gray-400 text-[8px] sm:text-xs">(opcional)</span>
                            </label>
                            <textarea v-model="comentario" rows="3" 
                                      class="w-full border rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-[10px] sm:text-sm focus:ring-2 focus:outline-none"
                                      :style="{ borderColor: `var(--color-primary-300)`, '--tw-ring-color': `var(--color-primary-500)` }"
                                      placeholder="Motivo del rechazo..."></textarea>
                        </div>
                        
                        <!-- Botones -->
                        <div class="flex flex-col xs:flex-row justify-end gap-2">
                            <button @click="modalRechazoOpen = false" 
                                    class="w-full xs:w-auto px-3 sm:px-4 py-1.5 sm:py-2 border rounded-lg text-[10px] sm:text-sm text-gray-700 hover:bg-gray-50 transition order-2 xs:order-1">
                                Cancelar
                            </button>
                            <button @click="procesarRechazo" :disabled="loading" 
                                    class="w-full xs:w-auto px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-[10px] sm:text-sm text-white transition disabled:opacity-50 flex items-center justify-center gap-1.5 order-1 xs:order-2 bg-red-600 hover:bg-red-700">
                                <i v-if="loading" class="fas fa-spinner fa-spin text-[10px] sm:text-sm"></i>
                                <i v-else class="fas fa-times"></i>
                                {{ loading ? 'Procesando...' : 'Rechazar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Estilos para pantallas muy pequeñas */
@media (min-width: 480px) {
    .xs\:inline {
        display: inline !important;
    }
    .xs\:hidden {
        display: none !important;
    }
    .xs\:col-span-2 {
        grid-column: span 2 / span 2;
    }
    .xs\:flex-row {
        flex-direction: row !important;
    }
    .xs\:order-1 {
        order: 1 !important;
    }
    .xs\:order-2 {
        order: 2 !important;
    }
}

@media (max-width: 479px) {
    .xs\:inline {
        display: none !important;
    }
    .xs\:hidden {
        display: inline !important;
    }
    .xs\:col-span-2 {
        grid-column: span 1 / span 1;
    }
    .xs\:flex-row {
        flex-direction: column !important;
    }
    .xs\:order-1 {
        order: 1 !important;
    }
    .xs\:order-2 {
        order: 2 !important;
    }
}

/* Scroll suave */
* {
    scroll-behavior: smooth;
}

/* Focus para inputs */
textarea:focus {
    --tw-ring-offset-width: 0px;
    --tw-ring-offset-color: #fff;
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    outline: 2px solid transparent;
    outline-offset: 2px;
}
</style>