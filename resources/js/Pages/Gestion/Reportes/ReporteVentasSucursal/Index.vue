<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import ModalDetalleProducto from './components/ModalDetalleProducto.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    reporte: Object,
    operadores: Array,
    filtros: Object,
    tieneFiltros: Boolean,
})

// ==================== DETECTAR MÓVIL ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== FILTROS ====================
const fecha = ref(props.filtros?.fecha || '')
const fechaDesde = ref(props.filtros?.fecha_desde || '')
const fechaHasta = ref(props.filtros?.fecha_hasta || '')
const operador = ref(props.filtros?.operador || '')
const tipoBusqueda = ref(props.filtros?.fecha ? 'dia' : (props.filtros?.fecha_desde ? 'rango' : 'dia'))

// ==================== MODAL ====================
const modalOpen = ref(false)
const productoSeleccionado = ref('')

// ==================== COMPUTED ====================
const hayFiltrosAplicados = computed(() => {
    if (tipoBusqueda.value === 'dia') {
        return !!fecha.value
    }
    return !!fechaDesde.value && !!fechaHasta.value
})

// ==================== MÉTODOS ====================
const aplicarFiltros = () => {
    const params = {}
    
    if (tipoBusqueda.value === 'dia') {
        if (fecha.value) params.fecha = fecha.value
    } else {
        if (fechaDesde.value) params.fecha_desde = fechaDesde.value
        if (fechaHasta.value) params.fecha_hasta = fechaHasta.value
    }
    
    if (operador.value) params.operador = operador.value
    
    router.get('/gestion/reportes/ventas-sucursal', params, {
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
    router.get('/gestion/reportes/ventas-sucursal', {}, {
        preserveState: true,
        replace: true,
    })
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

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-store text-primary-600 text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-lg lg:text-xl font-bold text-gray-800">Reporte de Ventas por Sucursal</h1>
                        <p class="text-xs text-gray-500">Productos vendidos agrupados por fecha</p>
                    </div>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-5">
                    <!-- Fila 1: Filtros principales -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de búsqueda</label>
                            <div class="flex gap-4 pt-1">
                                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                    <input 
                                        type="radio" 
                                        v-model="tipoBusqueda" 
                                        value="dia" 
                                        class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    > 
                                    <span>Un día</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                    <input 
                                        type="radio" 
                                        v-model="tipoBusqueda" 
                                        value="rango" 
                                        class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    > 
                                    <span>Rango</span>
                                </label>
                            </div>
                        </div>

                        <div v-if="tipoBusqueda === 'dia'">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha</label>
                            <input 
                                type="date" 
                                v-model="fecha" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm lg:text-[15px] focus:ring-primary-500 focus:border-primary-500"
                            >
                        </div>

                        <div v-if="tipoBusqueda === 'rango'" class="sm:col-span-2">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha Desde</label>
                                    <input 
                                        type="date" 
                                        v-model="fechaDesde" 
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm lg:text-[15px] focus:ring-primary-500 focus:border-primary-500"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha Hasta</label>
                                    <input 
                                        type="date" 
                                        v-model="fechaHasta" 
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm lg:text-[15px] focus:ring-primary-500 focus:border-primary-500"
                                    >
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Vendedor</label>
                            <select 
                                v-model="operador" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm lg:text-[15px] focus:ring-primary-500 focus:border-primary-500"
                            >
                                <option value="">Todos los vendedores</option>
                                <option v-for="op in operadores" :key="op.id" :value="op.id">{{ op.nombre }}</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Fila 2: Botones -->
                    <div class="flex flex-col sm:flex-row justify-end items-start sm:items-center gap-3">
                        <div class="flex gap-3 w-full sm:w-auto">
                            <button @click="limpiarFiltros" 
                                class="flex-1 sm:flex-initial px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm lg:text-[15px] font-medium hover:bg-gray-300 transition flex items-center justify-center gap-2">
                                <i class="fas fa-eraser text-sm"></i>
                                <span>Limpiar</span>
                            </button>
                            <button @click="aplicarFiltros" 
                                :disabled="!hayFiltrosAplicados"
                                class="flex-1 sm:flex-initial px-4 py-2 bg-primary-600 text-white rounded-lg text-sm lg:text-[15px] font-medium hover:bg-primary-700 transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-search text-sm"></i>
                                <span>Buscar</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ==================== MENSAJE SIN FILTROS ==================== -->
                <div v-if="!tieneFiltros" class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <i class="fas fa-calendar-alt text-5xl text-gray-300 mb-3 block"></i>
                    <p class="text-base font-medium text-gray-600">Seleccione fechas para ver el reporte</p>
                    <p class="text-sm text-gray-400 mt-1">Elija un día específico o un rango de fechas</p>
                </div>

                <!-- ==================== TABLA - MODO DÍA ÚNICO ==================== -->
                <div v-else-if="reporte.tipo === 'dia'" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-3 space-y-3">
                            <div v-for="item in reporte.productos" :key="item.Producto" 
                                @click="verDetalle(item.Producto)"
                                class="bg-gray-50 rounded-lg p-3 border border-gray-100 cursor-pointer hover:bg-gray-100 transition"
                            >
                                <div class="flex justify-between items-start mb-2">
                                    <p class="text-sm font-medium text-primary-700 flex-1">{{ item.Producto }}</p>
                                    <i class="fas fa-chevron-right text-primary-400 text-xs"></i>
                                </div>
                                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-200">
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Unidades</p>
                                        <p class="text-sm font-semibold text-gray-700">{{ formatearNumero(item.Unidades, 4) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Total Bs</p>
                                        <p class="text-sm font-bold text-primary-600">{{ formatearNumero(item.Total, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!reporte.productos || reporte.productos.length === 0" class="text-center text-gray-400 py-10">
                                <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                No hay ventas para esta fecha
                            </div>
                        </div>

                        <!-- VISTA TABLET (tabla compacta) -->
                        <div v-else-if="isTablet" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Producto</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Unidades</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Total Bs</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr 
                                        v-for="item in reporte.productos" 
                                        :key="item.Producto"
                                        @click="verDetalle(item.Producto)"
                                        class="hover:bg-gray-50 cursor-pointer transition"
                                    >
                                        <td class="px-3 py-2 text-xs text-primary-700 font-medium max-w-[180px] truncate">{{ item.Producto }}</td>
                                        <td class="px-3 py-2 text-right text-xs text-gray-700">{{ formatearNumero(item.Unidades, 4) }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-semibold text-primary-600">{{ formatearNumero(item.Total, 2) }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <i class="fas fa-chevron-right text-primary-400 text-xs"></i>
                                        </td>
                                    </tr>
                                    <tr v-if="!reporte.productos || reporte.productos.length === 0">
                                        <td colspan="4" class="px-4 py-10 text-center text-gray-400">No hay ventas para esta fecha</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 sticky bottom-0">
                                    <tr class="border-t border-gray-200">
                                        <td class="px-3 py-2 text-xs font-bold text-gray-800">TOTAL ACUMULADO</td>
                                        <td class="px-3 py-2 text-right text-xs font-bold text-gray-800">{{ formatearNumero(reporte.totalGeneralUnidades, 4) }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-bold text-primary-700">{{ formatearNumero(reporte.totalGeneralBs, 2) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- VISTA ESCRITORIO (tabla completa) -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-primary-700 uppercase">Producto</th>
                                        <th class="px-4 py-3 text-right text-sm font-medium text-primary-700 uppercase w-28">Unidades</th>
                                        <th class="px-4 py-3 text-right text-sm font-medium text-primary-700 uppercase w-28">Total Bs</th>
                                        <th class="px-4 py-3 text-center text-sm font-medium text-primary-700 uppercase w-12">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr 
                                        v-for="item in reporte.productos" 
                                        :key="item.Producto"
                                        @click="verDetalle(item.Producto)"
                                        class="hover:bg-gray-50 cursor-pointer transition"
                                    >
                                        <td class="px-4 py-3 text-sm text-primary-700 font-medium">
                                            <i class="fas fa-box text-primary-400 mr-2 text-xs"></i>
                                            {{ item.Producto }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700">{{ formatearNumero(item.Unidades, 4) }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold text-primary-600">{{ formatearNumero(item.Total, 2) }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <button class="text-primary-400 hover:text-primary-600 transition">
                                                <i class="fas fa-chevron-right text-sm"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!reporte.productos || reporte.productos.length === 0">
                                        <td colspan="4" class="px-4 py-12 text-center text-gray-400">
                                            <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                            No hay ventas para esta fecha
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 sticky bottom-0">
                                    <tr class="border-t border-gray-200">
                                        <td class="px-4 py-3 text-sm font-bold text-gray-800">TOTAL ACUMULADO</td>
                                        <td class="px-4 py-3 text-right text-sm font-bold text-gray-800">{{ formatearNumero(reporte.totalGeneralUnidades, 4) }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-bold text-primary-700">{{ formatearNumero(reporte.totalGeneralBs, 2) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ==================== TABLA - MODO RANGO DE FECHAS ==================== -->
                <div v-else-if="reporte.tipo === 'rango'" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-3 space-y-3">
                            <div v-for="item in reporte.productos" :key="item.Producto" 
                                @click="verDetalle(item.Producto)"
                                class="bg-gray-50 rounded-lg p-3 border border-gray-100 cursor-pointer hover:bg-gray-100 transition"
                            >
                                <div class="flex justify-between items-start mb-2">
                                    <p class="text-sm font-medium text-primary-700 flex-1">{{ item.Producto }}</p>
                                    <i class="fas fa-chevron-right text-primary-400 text-xs"></i>
                                </div>
                                <div class="space-y-1.5">
                                    <div v-for="detalle in item.detalles" :key="detalle.fecha" class="flex justify-between text-xs border-b border-gray-200/50 pb-1 last:border-0">
                                        <span class="text-gray-500">{{ formatearFechaCabecera(detalle.fecha) }}</span>
                                        <span class="text-emerald-700 font-medium">{{ formatearNumero(detalle.unidades, 4) }} und</span>
                                        <span class="text-blue-700 font-medium">{{ formatearNumero(detalle.total, 2) }} Bs</span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3 pt-2 mt-2 border-t border-gray-200">
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Total Unidades</p>
                                        <p class="text-sm font-bold text-emerald-700">{{ formatearNumero(item.totalUnidades, 4) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Total Bs</p>
                                        <p class="text-sm font-bold text-blue-700">{{ formatearNumero(item.totalBs, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!reporte.productos || reporte.productos.length === 0" class="text-center text-gray-400 py-10">
                                <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                No hay ventas en el rango seleccionado
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO (tabla) -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-primary-700 uppercase sticky left-0 bg-primary-50 z-10" style="min-width: 180px">Producto</th>
                                        <th 
                                            v-for="fecha in reporte.fechas" 
                                            :key="fecha" 
                                            class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase"
                                            style="min-width: 100px"
                                        >
                                            {{ formatearFechaCabecera(fecha) }}
                                        </th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-primary-700 uppercase bg-primary-100" style="min-width: 100px">TOTAL</th>
                                    </tr>
                                    <tr class="bg-primary-100">
                                        <th class="px-4 py-1 text-left text-[10px] font-medium text-primary-800 sticky left-0 bg-primary-100"></th>
                                        <th 
                                            v-for="fecha in reporte.fechas" 
                                            :key="fecha" 
                                            class="px-3 py-1 text-center text-[10px] font-medium text-primary-800"
                                        >
                                            <span class="block text-emerald-700">Unidades</span>
                                            <span class="block text-blue-700">Total Bs</span>
                                        </th>
                                        <th class="px-4 py-1 text-center text-[10px] font-medium text-primary-800 bg-primary-100">
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
                                        <td class="px-4 py-3 text-sm font-medium text-primary-700 sticky left-0 bg-white z-10 border-r">
                                            <i class="fas fa-box text-primary-400 mr-2 text-xs"></i>
                                            {{ item.Producto }}
                                        </td>
                                        <td 
                                            v-for="(detalle, idx) in item.detalles" 
                                            :key="idx" 
                                            class="px-3 py-3 text-center border-r"
                                        >
                                            <div class="text-xs font-semibold text-emerald-700">{{ formatearNumero(detalle.unidades, 4) }}</div>
                                            <div class="text-xs font-semibold text-blue-700 mt-0.5">{{ formatearNumero(detalle.total, 2) }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-center bg-gray-50">
                                            <div class="text-xs font-bold text-emerald-800">{{ formatearNumero(item.totalUnidades, 4) }}</div>
                                            <div class="text-xs font-bold text-blue-800 mt-0.5">{{ formatearNumero(item.totalBs, 2) }}</div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-100 sticky bottom-0">
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-bold text-gray-800 sticky left-0 bg-gray-100 border-r">TOTAL ACUMULADO</td>
                                        <td 
                                            v-for="(total, idx) in reporte.totalesPorFecha" 
                                            :key="idx" 
                                            class="px-3 py-3 text-center border-r"
                                        >
                                            <div class="text-xs font-bold text-emerald-800">{{ formatearNumero(total.unidades, 4) }}</div>
                                            <div class="text-xs font-bold text-blue-800 mt-0.5">{{ formatearNumero(total.total, 2) }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-center bg-gray-200">
                                            <div class="text-sm font-bold text-emerald-800">{{ formatearNumero(reporte.totalGeneralUnidades, 4) }}</div>
                                            <div class="text-sm font-bold text-blue-800 mt-0.5">{{ formatearNumero(reporte.totalGeneralBs, 2) }}</div>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ==================== PAGINACIÓN ==================== -->
                <!-- (No aplica para este reporte, pero se deja por si se agrega) -->
            </div>
        </div>

        <!-- ==================== MODAL DE DETALLE ==================== -->
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

<style scoped>
@media (min-width: 1024px) {
    input, select, button, .text-sm {
        font-size: 15px !important;
    }
}
</style>