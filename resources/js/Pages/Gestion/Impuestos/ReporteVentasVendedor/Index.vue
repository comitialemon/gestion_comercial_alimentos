<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import ModalDetalleProducto from './components/ModalDetalleProducto.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    ventasAgrupadas: Array,
    totalUnidades: Number,
    totalBolivianos: Number,
    grupos: Array,
    metodosPago: Array,
    filtros: Object,
    tieneFiltros: Boolean,
})

// Filtros
const fecha = ref(props.filtros?.fecha || '')
const fechaDesde = ref(props.filtros?.fecha_desde || '')
const fechaHasta = ref(props.filtros?.fecha_hasta || '')
const grupo = ref(props.filtros?.grupo || '')
const metodoPago = ref(props.filtros?.metodo_pago || '')
const tipoBusqueda = ref(props.filtros?.fecha ? 'dia' : (props.filtros?.fecha_desde ? 'rango' : 'dia'))

// Modal
const modalOpen = ref(false)
const productoSeleccionado = ref('')
const detallesCargando = ref(false)

// Aplicar filtros
const aplicarFiltros = () => {
    const params = {}
    
    if (tipoBusqueda.value === 'dia') {
        if (fecha.value) params.fecha = fecha.value
    } else {
        if (fechaDesde.value) params.fecha_desde = fechaDesde.value
        if (fechaHasta.value) params.fecha_hasta = fechaHasta.value
    }
    
    if (grupo.value) params.grupo = grupo.value
    if (metodoPago.value) params.metodo_pago = metodoPago.value
    
    router.get('/gestion/reporte-ventas-vendedor', params, {
        preserveState: true,
        replace: true,
    })
}

// Limpiar filtros
const limpiarFiltros = () => {
    fecha.value = ''
    fechaDesde.value = ''
    fechaHasta.value = ''
    grupo.value = ''
    metodoPago.value = ''
    tipoBusqueda.value = 'dia'
    aplicarFiltros()
}

// Abrir modal con detalle del producto
const verDetalle = async (producto) => {
    productoSeleccionado.value = producto
    modalOpen.value = true
}

// Formatear números
const formatearNumero = (value, decimals = 2) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-guindo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-chart-simple text-guindo-600"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-800">Reporte de Ventas por Vendedor</h1>
                            <p class="text-xs text-gray-500">Productos agrupados por fecha y método de pago</p>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <!-- Tipo de búsqueda -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tipo de búsqueda</label>
                            <div class="flex gap-3">
                                <label class="flex items-center gap-1">
                                    <input type="radio" v-model="tipoBusqueda" value="dia" class="w-3.5 h-3.5 text-guindo-600"> 
                                    <span class="text-xs">Un día</span>
                                </label>
                                <label class="flex items-center gap-1">
                                    <input type="radio" v-model="tipoBusqueda" value="rango" class="w-3.5 h-3.5 text-guindo-600"> 
                                    <span class="text-xs">Rango</span>
                                </label>
                            </div>
                        </div>

                        <!-- Fecha única -->
                        <div v-if="tipoBusqueda === 'dia'">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fecha</label>
                            <input type="date" v-model="fecha" class="w-40 border rounded-md px-2 py-1.5 text-sm">
                        </div>

                        <!-- Rango de fechas (inputs más compactos) -->
                        <div v-if="tipoBusqueda === 'rango'" class="sm:col-span-2">
                            <div class="flex gap-2">
                                <div class="w-40">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Desde</label>
                                    <input type="date" v-model="fechaDesde" class="w-full border rounded-md px-2 py-1.5 text-sm">
                                </div>
                                <div class="w-40">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Hasta</label>
                                    <input type="date" v-model="fechaHasta" class="w-full border rounded-md px-2 py-1.5 text-sm">
                                </div>
                            </div>
                        </div>

                        <!-- Grupo -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Grupo</label>
                            <select v-model="grupo" class="w-full border rounded-md px-2 py-1.5 text-sm">
                                <option value="">Todos los grupos</option>
                                <option v-for="g in grupos" :key="g.id" :value="g.id">{{ g.nombre }}</option>
                            </select>
                        </div>

                        <!-- Método de pago -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Método de pago</label>
                            <select v-model="metodoPago" class="w-full border rounded-md px-2 py-1.5 text-sm">
                                <option value="">Todos los métodos</option>
                                <option v-for="m in metodosPago" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button @click="limpiarFiltros" class="px-3 py-1.5 text-xs text-gray-600 hover:text-gray-800 border rounded-md transition">
                            <i class="fas fa-eraser mr-1 text-[11px]"></i> Limpiar
                        </button>
                        <button @click="aplicarFiltros" class="px-3 py-1.5 text-xs bg-guindo-600 text-white rounded-md hover:bg-guindo-700 transition">
                            <i class="fas fa-search mr-1 text-[11px]"></i> Buscar
                        </button>
                    </div>
                </div>

                <!-- Mensaje sin filtros -->
                <div v-if="!tieneFiltros" class="bg-blue-50 rounded-lg p-8 text-center mb-4">
                    <i class="fas fa-calendar-alt text-blue-400 text-2xl mb-2 block"></i>
                    <p class="text-sm text-blue-700">Seleccione fechas y presione "Buscar" para ver el reporte</p>
                </div>

                <!-- Tabla agrupada -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-guindo-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-guindo-700 uppercase tracking-wider">Producto</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-guindo-700 uppercase tracking-wider">Unidades</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-guindo-700 uppercase tracking-wider">Total Bs</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr 
                                    v-for="venta in ventasAgrupadas" 
                                    :key="venta.ProductoVenta" 
                                    @click="verDetalle(venta.ProductoVenta)"
                                    class="hover:bg-gray-50 cursor-pointer transition"
                                >
                                    <td class="px-4 py-2 text-sm font-medium text-guindo-700">
                                        <i class="fas fa-box text-guindo-400 mr-1 text-xs"></i>
                                        {{ venta.ProductoVenta }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-700">
                                        {{ formatearNumero(venta.TotalUnidades, 4) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right font-semibold text-guindo-600">
                                        {{ formatearNumero(venta.TotalBolivianos, 2) }}
                                    </td>
                                </tr>
                                <tr v-if="ventasAgrupadas.length === 0">
                                    <td colspan="3" class="px-4 py-10 text-center text-gray-500">
                                        <i class="fas fa-chart-line text-2xl mb-2 block"></i>
                                        <p class="text-sm">No hay ventas registradas con los filtros seleccionados</p>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="ventasAgrupadas.length > 0" class="bg-gray-50">
                                <tr class="border-t border-gray-200">
                                    <td class="px-4 py-2 text-sm font-bold text-gray-800">TOTAL ACUMULADO</td>
                                    <td class="px-4 py-2 text-sm text-right font-bold text-gray-800">
                                        {{ formatearNumero(totalUnidades, 4) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right font-bold text-guindo-700">
                                        {{ formatearNumero(totalBolivianos, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de detalle -->
        <ModalDetalleProducto
            v-model="modalOpen"
            :producto="productoSeleccionado"
            :filtros="{
                fecha: fecha,
                fecha_desde: fechaDesde,
                fecha_hasta: fechaHasta,
                grupo: grupo,
                metodo_pago: metodoPago,
                tipoBusqueda: tipoBusqueda
            }"
        />
    </div>
</template>