<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    producto: String,
    filtros: Object,
})

const emit = defineEmits(['update:modelValue'])

const modalOpen = ref(props.modelValue)
const detalles = ref([])
const cargando = ref(false)
const totalUnidades = ref(0)
const totalBolivianos = ref(0)

watch(() => props.modelValue, (newVal) => {
    modalOpen.value = newVal
    if (newVal && props.producto) {
        cargarDetalle()
    }
})

watch(modalOpen, (newVal) => {
    emit('update:modelValue', newVal)
})

const cargarDetalle = async () => {
    cargando.value = true
    try {
        const params = {
            producto: props.producto,
        }
        
        if (props.filtros?.tipoBusqueda === 'dia' && props.filtros?.fecha) {
            params.fecha = props.filtros.fecha
        } else {
            if (props.filtros?.fecha_desde) params.fecha_desde = props.filtros.fecha_desde
            if (props.filtros?.fecha_hasta) params.fecha_hasta = props.filtros.fecha_hasta
        }
        if (props.filtros?.operador) params.operador = props.filtros.operador
        
        const response = await axios.get('/gestion/reporte-ventas-sucursal/detalle-producto', { params })
        
        if (response.data.success) {
            detalles.value = response.data.detalles
            totalUnidades.value = response.data.totalUnidades
            totalBolivianos.value = response.data.totalBolivianos
        }
    } catch (error) {
        console.error('Error cargando detalle:', error)
    } finally {
        cargando.value = false
    }
}

const cerrarModal = () => {
    modalOpen.value = false
}

const formatearNumero = (value, decimals = 2) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    })
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}
</script>

<template>
    <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="cerrarModal">
        <div class="flex items-center justify-center min-h-screen p-3">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="cerrarModal"></div>
            
            <div class="relative bg-white rounded-lg shadow-xl max-w-6xl w-full mx-auto transform transition-all duration-300">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-2.5 border-b bg-guindo-600 rounded-t-lg">
                    <h3 class="text-sm font-semibold text-white">
                        <i class="fas fa-box mr-1 text-xs"></i> Detalle de Ventas - {{ producto }}
                    </h3>
                    <button @click="cerrarModal" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-4">
                    <div v-if="cargando" class="flex justify-center py-10">
                        <i class="fas fa-spinner fa-spin text-guindo-500 text-xl"></i>
                    </div>

                    <div v-else>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Factura</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vendedor</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Producto Venta</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Producto Inventario</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unidades</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Precio Unit.</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <tr v-for="detalle in detalles" :key="detalle.NumeroFactura" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 text-sm text-gray-600">{{ formatearFecha(detalle.FechaVenta) }}</td>
                                        <td class="px-3 py-2 text-sm font-mono text-gray-900">{{ detalle.NumeroFactura }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-600">{{ detalle.IdOperadorIngresa }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-800">{{ detalle.ProductoVenta }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-600">{{ detalle.ProductoInventario }}</td>
                                        <td class="px-3 py-2 text-sm text-right text-gray-700">{{ formatearNumero(detalle.unidades, 4) }}</td>
                                        <td class="px-3 py-2 text-sm text-right text-gray-700">{{ formatearNumero(detalle.PrecioUnidades, 2) }}</td>
                                        <td class="px-3 py-2 text-sm text-right font-semibold text-guindo-600">{{ formatearNumero(detalle.Total, 2) }}</td>
                                    </tr>
                                    <tr v-if="detalles.length === 0">
                                        <td colspan="8" class="px-3 py-10 text-center text-gray-500">
                                            <p class="text-sm">No hay ventas para este producto</p>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="detalles.length > 0" class="bg-gray-50">
                                    <tr class="border-t border-gray-200">
                                        <td colspan="5" class="px-3 py-2 text-sm font-bold text-gray-800">TOTAL ACUMULADO</td>
                                        <td class="px-3 py-2 text-sm text-right font-bold text-gray-800">{{ formatearNumero(totalUnidades, 4) }}</td>
                                        <td class="px-3 py-2 text-sm"></td>
                                        <td class="px-3 py-2 text-sm text-right font-bold text-guindo-700">{{ formatearNumero(totalBolivianos, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 py-2 border-t bg-gray-50 flex justify-end rounded-b-lg">
                    <button @click="cerrarModal" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300 transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>