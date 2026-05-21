<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import ModalDetalleProducto from './components/ModalDetalleProducto.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    reporte: Object,
    operadores: Array,
    filtros: Object,
    tieneFiltros: Boolean,
})

// Filtros
const fecha = ref(props.filtros?.fecha || '')
const fechaDesde = ref(props.filtros?.fecha_desde || '')
const fechaHasta = ref(props.filtros?.fecha_hasta || '')
const operador = ref(props.filtros?.operador || '')
const tipoBusqueda = ref(props.filtros?.fecha ? 'dia' : (props.filtros?.fecha_desde ? 'rango' : 'dia'))

// Modal
const modalOpen = ref(false)
const productoSeleccionado = ref('')

const aplicarFiltros = () => {
    const params = {}
    
    if (tipoBusqueda.value === 'dia') {
        if (fecha.value) params.fecha = fecha.value
    } else {
        if (fechaDesde.value) params.fecha_desde = fechaDesde.value
        if (fechaHasta.value) params.fecha_hasta = fechaHasta.value
    }
    
    if (operador.value) params.operador = operador.value
    
    router.get('/gestion/reporte-ventas-sucursal', params, {
        preserveState: true,
        replace: true,
    })
}

const limpiarFiltros = () => {
    fecha.value = ''
    fechaDesde.value = ''
    fechaHasta.value = ''
    operador.value = ''
    tipoBusqueda.value = 'dia'
    aplicarFiltros()
}

const verDetalle = (producto) => {
    productoSeleccionado.value = producto
    modalOpen.value = true
}

const formatearNumero = (value, decimals = 2) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    })
}

const formatearFechaCabecera = (fecha) => {
    if (!fecha) return '-'
    const date = new Date(fecha)
    return date.toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric' })
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
                            <i class="fas fa-store text-guindo-600"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-800">Reporte de Ventas por Sucursal</h1>
                            <p class="text-xs text-gray-500">Productos vendidos agrupados por fecha</p>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
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

                        <div v-if="tipoBusqueda === 'dia'">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fecha</label>
                            <input type="date" v-model="fecha" class="w-40 border rounded-md px-2 py-1.5 text-sm">
                        </div>

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

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Vendedor</label>
                            <select v-model="operador" class="w-full border rounded-md px-2 py-1.5 text-sm">
                                <option value="">Todos los vendedores</option>
                                <option v-for="op in operadores" :key="op.id" :value="op.id">{{ op.nombre }}</option>
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

                <!-- Tabla - Modo Día Único -->
                <div v-else-if="reporte.tipo === 'dia'" class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-guindo-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-guindo-700 uppercase">Producto</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-guindo-700 uppercase">Unidades</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-guindo-700 uppercase">Total Bs</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr 
                                    v-for="item in reporte.productos" 
                                    :key="item.Producto"
                                    @click="verDetalle(item.Producto)"
                                    class="hover:bg-gray-50 cursor-pointer transition"
                                >
                                    <td class="px-4 py-2 text-sm font-medium text-guindo-700">
                                        <i class="fas fa-box text-guindo-400 mr-1 text-xs"></i>
                                        {{ item.Producto }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-700">
                                        {{ formatearNumero(item.Unidades, 4) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right font-semibold text-guindo-600">
                                        {{ formatearNumero(item.Total, 2) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr class="border-t border-gray-200">
                                    <td class="px-4 py-2 text-sm font-bold text-gray-800">TOTAL ACUMULADO</td>
                                    <td class="px-4 py-2 text-sm text-right font-bold text-gray-800">
                                        {{ formatearNumero(reporte.totalGeneralUnidades, 4) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right font-bold text-guindo-700">
                                        {{ formatearNumero(reporte.totalGeneralBs, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Tabla - Modo Rango de Fechas -->
                <div v-else-if="reporte.tipo === 'rango'" class="bg-white rounded-lg shadow-sm overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-guindo-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-guindo-700 uppercase sticky left-0 bg-guindo-50 z-10" style="min-width: 180px">Producto</th>
                                <th 
                                    v-for="fecha in reporte.fechas" 
                                    :key="fecha" 
                                    class="px-3 py-2 text-center text-xs font-medium text-guindo-700 uppercase"
                                    style="min-width: 110px"
                                >
                                    {{ formatearFechaCabecera(fecha) }}
                                </th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-guindo-700 uppercase bg-guindo-100" style="min-width: 100px">TOTAL</th>
                            </tr>
                            <tr class="bg-guindo-100">
                                <th class="px-4 py-1 text-left text-[10px] font-medium text-guindo-800 sticky left-0 bg-guindo-100"></th>
                                <th 
                                    v-for="fecha in reporte.fechas" 
                                    :key="fecha" 
                                    class="px-3 py-1 text-center text-[10px] font-medium text-guindo-800"
                                >
                                    <span class="block text-emerald-700">Unidades</span>
                                    <span class="block text-blue-700">Total Bs</span>
                                </th>
                                <th class="px-4 py-1 text-center text-[10px] font-medium text-guindo-800 bg-guindo-100">
                                    <span class="block text-emerald-700">Unidades</span>
                                    <span class="block text-blue-700">Total Bs</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <tr 
                                v-for="item in reporte.productos" 
                                :key="item.Producto"
                                @click="verDetalle(item.Producto)"
                                class="hover:bg-gray-50 cursor-pointer transition"
                            >
                                <td class="px-4 py-3 text-sm font-medium text-guindo-700 sticky left-0 bg-white z-10 border-r">
                                    <i class="fas fa-box text-guindo-400 mr-2 text-xs"></i>
                                    {{ item.Producto }}
                                </td>
                                <td 
                                    v-for="(detalle, idx) in item.detalles" 
                                    :key="idx" 
                                    class="px-3 py-3 text-center border-r"
                                >
                                    <div class="text-xs font-semibold text-emerald-700">{{ formatearNumero(detalle.unidades, 4) }}</div>
                                    <div class="text-xs font-semibold text-blue-700 mt-1">{{ formatearNumero(detalle.total, 2) }}</div>
                                </td>
                                <td class="px-4 py-3 text-center bg-gray-50">
                                    <div class="text-xs font-bold text-emerald-800">{{ formatearNumero(item.totalUnidades, 4) }}</div>
                                    <div class="text-xs font-bold text-blue-800 mt-1">{{ formatearNumero(item.totalBs, 2) }}</div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-100">
                            <tr>
                                <td class="px-4 py-3 text-sm font-bold text-gray-800 sticky left-0 bg-gray-100 border-r">TOTAL ACUMULADO</td>
                                <td 
                                    v-for="(total, idx) in reporte.totalesPorFecha" 
                                    :key="idx" 
                                    class="px-3 py-3 text-center border-r"
                                >
                                    <div class="text-xs font-bold text-emerald-800">{{ formatearNumero(total.unidades, 4) }}</div>
                                    <div class="text-xs font-bold text-blue-800 mt-1">{{ formatearNumero(total.total, 2) }}</div>
                                </td>
                                <td class="px-4 py-3 text-center bg-gray-200">
                                    <div class="text-sm font-bold text-emerald-800">{{ formatearNumero(reporte.totalGeneralUnidades, 4) }}</div>
                                    <div class="text-sm font-bold text-blue-800 mt-1">{{ formatearNumero(reporte.totalGeneralBs, 2) }}</div>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
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
                operador: operador,
                tipoBusqueda: tipoBusqueda
            }"
        />
    </div>
</template>