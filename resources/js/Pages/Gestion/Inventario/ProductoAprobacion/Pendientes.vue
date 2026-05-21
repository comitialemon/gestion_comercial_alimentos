<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    solicitudes: Array
})

const modalOpen = ref(false)
const solicitudSeleccionada = ref(null)
const comentario = ref('')
const accion = ref('')
const loading = ref(false)

const abrirModal = (solicitud, tipo) => {
    solicitudSeleccionada.value = solicitud
    accion.value = tipo
    comentario.value = ''
    modalOpen.value = true
}

const procesarVoto = async () => {
    const voto = solicitudSeleccionada.value.votos[0]
    if (!voto) return
    
    loading.value = true
    try {
        await axios.post(`/gestion/productos-aprobacion/votar/${voto.IdProductoAprobacionVoto}`, {
            estado: accion.value,
            comentario: comentario.value
        })
        modalOpen.value = false
        window.location.reload()
    } catch (err) {
        alert(err.response?.data?.message || 'Error al procesar')
    } finally {
        loading.value = false
    }
}

const verDetalle = (id) => {
    router.get(`/gestion/productos-aprobacion/ver/${id}`)
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-5xl mx-auto">
                <!-- Header -->
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-clock text-guindo-600 text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Productos Pendientes de Aprobación</h1>
                        <p class="text-xs text-gray-500">Revisa y aprueba o rechaza los productos nuevos</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div v-for="solicitud in solicitudes" :key="solicitud.IdProductoAprobacionSolicitud" class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-md transition">
                        <div class="p-5">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="fas fa-box text-guindo-500"></i>
                                        <h2 class="text-lg font-semibold text-gray-800">{{ solicitud.producto?.Detalle || 'Sin nombre' }}</h2>
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-800">Pendiente</span>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600 mb-3">
                                        <div><span class="font-medium">Código:</span> {{ solicitud.producto?.Codigo || '-' }}</div>
                                        <div><span class="font-medium">Precio:</span> {{ Number(solicitud.producto?.PrecioVenta).toFixed(2) }} Bs</div>
                                        <div><span class="font-medium">Creado por:</span> {{ solicitud.solicitante?.identificador?.Nombre || '-' }}</div>
                                        <div><span class="font-medium">Fecha solicitud:</span> {{ formatearFecha(solicitud.FechaSolicitud) }}</div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="verDetalle(solicitud.producto.IdDetalleProducto)" class="px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                        <i class="fas fa-eye mr-1"></i> Ver
                                    </button>
                                    <button @click="abrirModal(solicitud, 'aprobado')" class="px-3 py-1.5 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700">
                                        <i class="fas fa-check mr-1"></i> Aprobar
                                    </button>
                                    <button @click="abrirModal(solicitud, 'rechazado')" class="px-3 py-1.5 text-xs bg-red-600 text-white rounded-lg hover:bg-red-700">
                                        <i class="fas fa-times mr-1"></i> Rechazar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="solicitudes.length === 0" class="bg-white rounded-xl shadow-sm p-8 text-center">
                        <i class="fas fa-check-circle text-4xl text-green-500 mb-3 block"></i>
                        <p class="text-gray-500">No hay productos pendientes de aprobación</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para aprobar/rechazar -->
        <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="modalOpen = false">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="modalOpen = false"></div>
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full">
                    <div class="flex items-center justify-between px-5 py-3 border-b" :class="accion === 'aprobado' ? 'bg-green-600' : 'bg-red-600'">
                        <h3 class="text-sm font-semibold text-white">{{ accion === 'aprobado' ? 'Aprobar Producto' : 'Rechazar Producto' }}</h3>
                        <button @click="modalOpen = false" class="text-white/80 hover:text-white">✕</button>
                    </div>
                    <div class="p-5">
                        <p class="text-sm text-gray-700 mb-3">
                            <strong>Producto:</strong> {{ solicitudSeleccionada?.producto?.Detalle }}
                        </p>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Comentario (opcional)</label>
                            <textarea v-model="comentario" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Escribe un comentario..."></textarea>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button @click="modalOpen = false" class="px-4 py-2 border rounded-lg text-gray-700">Cancelar</button>
                            <button @click="procesarVoto" :disabled="loading" class="px-4 py-2 rounded-lg text-white" :class="accion === 'aprobado' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'">
                                {{ loading ? 'Procesando...' : (accion === 'aprobado' ? 'Aprobar' : 'Rechazar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>