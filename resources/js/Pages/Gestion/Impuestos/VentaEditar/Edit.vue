<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, inject } from 'vue'
import axios from 'axios'
import ModalConfirmacion from './components/ModalConfirmacion.vue'
import ModalResultadoReprocesar from './components/ModalResultadoReprocesar.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    venta: Object,
    productos: Array,
    estados: Array,
})

// ==================== ESTADO ====================
const reprocesando = ref(false)
const guardando = ref(false)

const mostrarModalConfirmacion = ref(false)
const mostrarModalResultado = ref(false)

const resultadoExito = ref(true)
const resultadoMensaje = ref('')
const resultadoFecha = ref('')
const resultadoDetalles = ref({
    eliminados: 0,
    insertados: 0,
    fecha_anterior: '',
    fecha_nueva: '',
    productos: []
})

const fechaVenta = ref(props.venta?.FechaVenta ? props.venta.FechaVenta.slice(0, 10) : '')

// ==================== COMPUTED ====================
const totalVenta = computed(() => {
    if (!props.venta?.detalles) return 0
    return props.venta.detalles.reduce((sum, item) => {
        return sum + (parseFloat(item.totalbolivianos) || 0)
    }, 0)
})

const getEstadoColor = (estadoId) => {
    const colors = {
        1: 'bg-emerald-100 text-emerald-700',
        2: 'bg-red-100 text-red-700',
        3: 'bg-yellow-100 text-yellow-700',
        4: 'bg-gray-100 text-gray-600',
        5: 'bg-orange-100 text-orange-700',
        6: 'bg-blue-100 text-blue-700',
    }
    return colors[estadoId] || 'bg-gray-100 text-gray-500'
}

const getEstadoNombre = (estadoId) => {
    const nombres = {
        1: 'VÁLIDA',
        2: 'ANULADA',
        3: 'EXTRAVIADA',
        4: 'NO UTILIZADA',
        5: 'CONTINGENCIA',
        6: 'LIBRE CONSIGNACION',
    }
    return nombres[estadoId] || 'Desconocido'
}

// ==================== FUNCIONES ====================
const volver = () => {
    router.get('/gestion/ventas-editar')
}

const guardarFecha = async () => {
    if (!fechaVenta.value) {
        toast?.warning('⚠️ Fecha requerida', 'La fecha de venta es obligatoria')
        return
    }
    
    guardando.value = true
    
    try {
        const response = await axios.put(`/gestion/ventas-editar/${props.venta.IdVentas}`, {
            FechaVenta: fechaVenta.value
        })
        
        if (response.data.success) {
            toast?.success('✅ Éxito', response.data.message || 'Fecha actualizada correctamente')
        } else {
            toast?.error('❌ Error', response.data.message || 'Error al guardar la fecha')
        }
    } catch (error) {
        console.error('Error:', error)
        toast?.error('❌ Error', error.response?.data?.message || 'Error al guardar la fecha')
    } finally {
        guardando.value = false
    }
}

const abrirModalConfirmacion = () => {
    mostrarModalConfirmacion.value = true
}

const ejecutarReprocesar = async () => {
    reprocesando.value = true
    mostrarModalConfirmacion.value = false
    
    try {
        const response = await axios.post(`/gestion/ventas-editar/${props.venta.IdVentas}/reprocesar`)
        
        if (response.data.success) {
            resultadoExito.value = true
            resultadoMensaje.value = response.data.message || '✅ La venta fue reprocesada correctamente'
            resultadoFecha.value = response.data.fecha || ''
            resultadoDetalles.value = {
                eliminados: response.data.detalles?.eliminados || 0,
                insertados: response.data.detalles?.insertados || 0,
                fecha_anterior: response.data.detalles?.fecha_anterior || '',
                fecha_nueva: response.data.detalles?.fecha_nueva || '',
                productos: response.data.detalles?.productos || []
            }
            toast?.success('✅ Éxito', `Reprocesado correctamente. ${response.data.movimientos || 0} movimientos creados.`)
        } else {
            resultadoExito.value = false
            resultadoMensaje.value = response.data.message || '❌ Error al reprocesar'
            toast?.error('❌ Error', response.data.message || 'Error al reprocesar')
        }
        mostrarModalResultado.value = true
    } catch (error) {
        console.error('Error:', error)
        resultadoExito.value = false
        resultadoMensaje.value = error.response?.data?.message || 'Error al reprocesar'
        toast?.error('❌ Error', resultadoMensaje.value)
        mostrarModalResultado.value = true
    } finally {
        reprocesando.value = false
    }
}

const cerrarModalResultado = () => {
    mostrarModalResultado.value = false
    if (resultadoExito.value) {
        router.reload()
    }
}

const formatDate = (date) => {
    if (!date) return '-'
    const d = new Date(date)
    return d.toLocaleDateString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                
                <!-- ==================== HEADER ==================== -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <button @click="volver" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
                            <i class="fas fa-arrow-left text-base"></i>
                        </button>
                        <div>
                            <h1 class="text-base lg:text-lg font-bold text-gray-800">Editar Venta</h1>
                            <p class="text-xs text-gray-500">
                                Venta N° {{ venta?.NumeroFactura || 'Sin número' }}
                                <span :class="['ml-2 inline-flex px-2 py-0.5 text-[10px] font-medium rounded-full', getEstadoColor(venta?.IdEstado)]">
                                    {{ getEstadoNombre(venta?.IdEstado) }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button @click="guardarFecha" :disabled="guardando"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 disabled:opacity-50">
                            <i v-if="guardando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-save text-[10px]"></i>
                            {{ guardando ? 'Guardando...' : 'Guardar' }}
                        </button>
                        <button @click="abrirModalConfirmacion" :disabled="reprocesando"
                            class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 rounded-md text-xs font-medium transition flex items-center gap-1.5 disabled:opacity-50">
                            <i v-if="reprocesando" class="fas fa-spinner fa-spin text-[10px]"></i>
                            <i v-else class="fas fa-boxes text-[10px]"></i>
                            {{ reprocesando ? 'Reprocesando...' : 'Reprocesar' }}
                        </button>
                    </div>
                </div>

                <!-- ==================== DATOS DE LA VENTA ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-3">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                        <div>
                            <label class="text-[8px] text-gray-400 uppercase tracking-wider block">Fecha Venta</label>
                            <input v-model="fechaVenta" type="date"
                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none" />
                        </div>
                        <div>
                            <label class="text-[8px] text-gray-400 uppercase tracking-wider block">N° Factura</label>
                            <div class="w-full bg-gray-50 border border-gray-200 rounded-md px-2 py-1 text-sm text-gray-700">{{ venta?.NumeroFactura || '-' }}</div>
                        </div>
                        <div>
                            <label class="text-[8px] text-gray-400 uppercase tracking-wider block">Importe</label>
                            <div class="w-full bg-gray-50 border border-gray-200 rounded-md px-2 py-1 text-sm font-bold text-primary-700">{{ Number(venta?.ImporteVenta || 0).toFixed(2) }}</div>
                        </div>
                        <div>
                            <label class="text-[8px] text-gray-400 uppercase tracking-wider block">N° Autorización</label>
                            <div class="w-full bg-gray-50 border border-gray-200 rounded-md px-2 py-1 text-sm text-gray-700">{{ venta?.NumeroAutorizacion || '-' }}</div>
                        </div>
                        <div>
                            <label class="text-[8px] text-gray-400 uppercase tracking-wider block">Estado</label>
                            <div class="w-full bg-gray-50 border border-gray-200 rounded-md px-2 py-1 text-sm text-gray-700">
                                <span :class="['inline-flex px-2 py-0.5 text-[10px] font-medium rounded-full', getEstadoColor(venta?.IdEstado)]">
                                    {{ getEstadoNombre(venta?.IdEstado) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== DETALLE DE PRODUCTOS ==================== -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-xs font-bold text-gray-700 flex items-center gap-2">
                            <i class="fas fa-list text-gray-400"></i> Detalle
                        </h2>
                        <span class="text-[9px] text-gray-500">{{ venta?.detalles?.length || 0 }} productos</span>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-2 py-1.5 text-left text-[8px] font-medium text-gray-500 uppercase">Código</th>
                                    <th class="px-2 py-1.5 text-left text-[8px] font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-2 py-1.5 text-right text-[8px] font-medium text-gray-500 uppercase w-16">Unidades</th>
                                    <th class="px-2 py-1.5 text-right text-[8px] font-medium text-gray-500 uppercase w-20">Precio</th>
                                    <th class="px-2 py-1.5 text-right text-[8px] font-medium text-gray-500 uppercase w-20">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(detalle, index) in (venta?.detalles || [])" :key="index" class="hover:bg-gray-50 transition">
                                    <td class="px-2 py-2 text-xs text-gray-600">{{ detalle.producto_codigo || '-' }}</td>
                                    <td class="px-2 py-2 text-xs text-gray-700">{{ detalle.producto_nombre || 'Producto' }}</td>
                                    <td class="px-2 py-2 text-xs text-right font-medium">{{ Number(detalle.unidades || 0).toFixed(2) }}</td>
                                    <td class="px-2 py-2 text-xs text-right">{{ Number(detalle.preciounidades || 0).toFixed(2) }}</td>
                                    <td class="px-2 py-2 text-xs text-right font-bold text-primary-700">{{ Number(detalle.totalbolivianos || 0).toFixed(2) }}</td>
                                </tr>
                                <tr class="bg-gray-50 font-bold border-t-2 border-gray-300">
                                    <td colspan="4" class="px-2 py-2 text-right text-xs text-gray-700">TOTAL:</td>
                                    <td class="px-2 py-2 text-right text-xs font-bold text-primary-700">{{ Number(totalVenta).toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== FOOTER ==================== -->
                <div class="mt-3 flex justify-end">
                    <button @click="volver" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-50 transition flex items-center gap-1.5">
                        <i class="fas fa-arrow-left text-[10px]"></i> Volver
                    </button>
                </div>

                <!-- ==================== MODALES ==================== -->
                <ModalConfirmacion
                    :visible="mostrarModalConfirmacion"
                    titulo="⚠️ Reprocesar Inventario"
                    mensaje="Se eliminarán todos los movimientos de inventario anteriores y se crearán nuevos según los productos actuales de la venta."
                    detalle="Esta acción no se puede deshacer."
                    botonConfirmar="Reprocesar"
                    :cargando="reprocesando"
                    @confirm="ejecutarReprocesar"
                    @close="mostrarModalConfirmacion = false"
                />

                <ModalResultadoReprocesar
                    :visible="mostrarModalResultado"
                    :exito="resultadoExito"
                    :mensaje="resultadoMensaje"
                    :fecha="resultadoFecha"
                    :detalles="resultadoDetalles"
                    @close="cerrarModalResultado"
                />

                <!-- Overlays de carga -->
                <div v-if="reprocesando" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-xl p-5 flex flex-col items-center gap-3 shadow-xl max-w-xs w-full mx-3">
                        <i class="fas fa-spinner fa-spin text-2xl text-amber-500"></i>
                        <p class="text-sm text-gray-700 font-medium text-center">Reprocesando inventario...</p>
                        <p class="text-[10px] text-gray-400 text-center">Eliminando movimientos anteriores y creando nuevos</p>
                    </div>
                </div>

                <div v-if="guardando" class="fixed inset-0 bg-black/40 flex items-center justify-center z-40">
                    <div class="bg-white rounded-xl p-4 flex items-center gap-3 shadow-xl">
                        <i class="fas fa-spinner fa-spin text-lg text-emerald-600"></i>
                        <span class="text-xs text-gray-700">Guardando fecha...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}
</style>