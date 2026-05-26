<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import ConfirmacionModal from './components/ConfirmacionModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    facturas: Array,
    filtroFecha: String,
})

const facturaSeleccionada = ref('')
const anulando = ref(false)
const mensaje = ref('')
const error = ref('')
const exito = ref(false)
const fecha = ref(props.filtroFecha || '')
const isMobile = ref(window.innerWidth < 768)

// 🔥 Estado del modal
const modalVisible = ref(false)
const modalCargando = ref(false)
const facturaParaAnular = ref(null)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

const aplicarFiltro = () => {
    const params = {}
    if (fecha.value) params.fecha = fecha.value
    router.get('/gestion/anular-factura', params, {
        preserveState: true,
        replace: true,
    })
}

const limpiarFiltro = () => {
    fecha.value = ''
    router.get('/gestion/anular-factura', {}, {
        preserveState: true,
        replace: true,
    })
}

const seleccionarFactura = (id) => {
    facturaSeleccionada.value = id
}

// 🔥 Abrir modal de confirmación
const abrirModalConfirmacion = () => {
    if (!facturaSeleccionada.value) {
        error.value = 'Seleccione una factura para anular'
        return
    }
    
    const factura = props.facturas.find(f => f.IdVentas === facturaSeleccionada.value)
    if (factura) {
        facturaParaAnular.value = factura
        modalVisible.value = true
    }
}

// 🔥 Ejecutar anulación
const ejecutarAnulacion = async () => {
    modalCargando.value = true
    
    try {
        const response = await axios.post('/gestion/anular-factura/anular', {
            IdVentas: facturaParaAnular.value.IdVentas
        })
        
        if (response.data.success) {
            modalVisible.value = false
            exito.value = true
            mensaje.value = response.data.message
            
            // 🔥 ABRIR EL PDF DE LA FACTURA ANULADA (con sello)
            // Usar el id_ventas devuelto por el servidor o el que ya tenemos
            const ventaId = response.data.id_ventas || facturaParaAnular.value.IdVentas
            const pdfUrl = `/venta-factura/factura-pdf/${ventaId}`
            window.open(pdfUrl, '_blank')
            
            setTimeout(() => {
                window.location.reload()
            }, 2000)
        }
    } catch (err) {
        modalVisible.value = false
        error.value = err.response?.data?.message || 'Error al anular la factura'
        setTimeout(() => {
            error.value = ''
        }, 3000)
    } finally {
        modalCargando.value = false
    }
}

// 🔥 Cerrar modal
const cerrarModal = () => {
    modalVisible.value = false
    facturaParaAnular.value = null
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}

const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-ban text-red-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Anular Factura</h1>
                            <p class="text-[10px] text-gray-500 hidden xs:block">Seleccione una factura no liquidada para anular</p>
                        </div>
                    </div>
                </div>

                <!-- Mensajes -->
                <div v-if="exito" class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle text-green-600 text-sm"></i>
                        <p class="text-xs text-green-700">{{ mensaje }}</p>
                    </div>
                </div>

                <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-circle text-red-600 text-sm"></i>
                        <p class="text-xs text-red-700">{{ error }}</p>
                    </div>
                </div>

                <!-- Filtro de fecha -->
                <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Fecha:</label>
                            <input type="date" v-model="fecha" class="border rounded-md px-2 py-1 text-xs">
                        </div>
                        <div class="flex gap-2">
                            <button @click="aplicarFiltro" class="px-3 py-1 bg-guindo-600 text-white rounded-md text-xs hover:bg-guindo-700 transition">
                                <i class="fas fa-search text-[10px] mr-1"></i> Filtrar
                            </button>
                            <button @click="limpiarFiltro" class="px-3 py-1 border border-gray-300 rounded-md text-xs text-gray-600 hover:bg-gray-100 transition">
                                <i class="fas fa-eraser text-[10px] mr-1"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Vista MÓVIL (tarjetas) -->
                <div v-if="isMobile" class="space-y-3">
                    <div 
                        v-for="factura in facturas" 
                        :key="factura.IdVentas"
                        @click="seleccionarFactura(factura.IdVentas)"
                        class="bg-white rounded-lg shadow-sm p-3 cursor-pointer transition-all"
                        :class="{ 'ring-2 ring-red-500 bg-red-50': facturaSeleccionada == factura.IdVentas }"
                    >
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="text-xs font-mono font-bold text-guindo-600 bg-guindo-50 px-2 py-0.5 rounded">
                                    N° {{ factura.NumeroFactura }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded-full border-2"
                                    :class="facturaSeleccionada == factura.IdVentas ? 'bg-red-500 border-red-500' : 'border-gray-300'">
                                    <i v-if="facturaSeleccionada == factura.IdVentas" class="fas fa-check text-white text-[8px] flex items-center justify-center h-full"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Fecha:</span>
                                <span class="font-medium">{{ formatearFecha(factura.FechaVenta) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Importe:</span>
                                <span class="font-bold text-guindo-600">{{ formatearNumero(factura.ImporteVenta) }} Bs</span>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="facturas.length === 0" class="bg-white rounded-lg shadow-sm p-8 text-center">
                        <i class="fas fa-receipt text-3xl text-gray-300 mb-2 block"></i>
                        <p class="text-xs text-gray-400">No hay facturas pendientes de anulación</p>
                    </div>
                </div>

                <!-- Vista ESCRITORIO (tabla) -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Factura</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Importe</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr 
                                    v-for="factura in facturas" 
                                    :key="factura.IdVentas"
                                    class="hover:bg-gray-50 transition cursor-pointer"
                                    :class="{ 'bg-red-50': facturaSeleccionada == factura.IdVentas }"
                                    @click="seleccionarFactura(factura.IdVentas)"
                                >
                                    <td class="px-4 py-2 text-sm font-mono text-gray-900">{{ factura.NumeroFactura }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ formatearFecha(factura.FechaVenta) }}</td>
                                    <td class="px-4 py-2 text-sm text-right font-semibold text-guindo-600">{{ formatearNumero(factura.ImporteVenta) }} Bs</td>
                                    <td class="px-4 py-2 text-center">
                                        <input 
                                            type="radio" 
                                            :value="factura.IdVentas"
                                            v-model="facturaSeleccionada"
                                            class="w-4 h-4 text-red-600 focus:ring-red-500 cursor-pointer"
                                            @click.stop
                                        >
                                    </td>
                                </tr>
                                <tr v-if="facturas.length === 0">
                                    <td colspan="4" class="px-4 py-10 text-center text-gray-500">
                                        <i class="fas fa-receipt text-3xl mb-2 block"></i>
                                        No hay facturas pendientes de anulación
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Botón Anular (fijo inferior en móvil) -->
                <div class="mt-4 flex justify-end" :class="{ 'fixed bottom-4 right-4 z-50': isMobile && facturas.length > 0 }">
                    <button 
                        @click="abrirModalConfirmacion"
                        :disabled="!facturaSeleccionada || anulando"
                        class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 text-sm"
                    >
                        <i v-if="anulando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-ban"></i>
                        {{ anulando ? 'Anulando...' : 'Anular Factura' }}
                    </button>
                </div>

                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Solo se pueden anular facturas que NO hayan sido liquidadas.
                </div>
            </div>
        </div>

        <!-- 🔥 MODAL DE CONFIRMACIÓN -->
        <ConfirmacionModal
            :visible="modalVisible"
            :cargando="modalCargando"
            titulo="Anular Factura"
            descripcion="¿Estás seguro de que deseas anular esta factura?"
            mensaje-adicional="Esta acción no se puede deshacer. La factura quedará marcada como ANULADA."
            boton-texto="Sí, Anular"
            accion="anular"
            :numero-factura="facturaParaAnular?.NumeroFactura"
            @confirmar="ejecutarAnulacion"
            @cerrar="cerrarModal"
        />
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .xs\:inline {
        display: inline;
    }
    .xs\:block {
        display: block;
    }
}
</style>