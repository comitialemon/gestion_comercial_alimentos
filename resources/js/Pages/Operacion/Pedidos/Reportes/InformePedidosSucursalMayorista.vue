<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { usePage } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    pedidos: {
        type: Array,
        default: () => []
    },
    sucursal: {
        type: Object,
        default: () => ({})
    },
    operador: {
        type: Object,
        default: () => ({})
    },
    totales: {
        type: Object,
        default: () => ({
            total_pedidos: 0,
            total_unidades: 0,
            total_productos: 0
        })
    },
    resumenPorFecha: {
        type: Array,
        default: () => []
    },
    filtros: {
        type: Object,
        default: () => ({
            fecha_inicio: '',
            fecha_fin: '',
            search: ''
        })
    }
})

const theme = computed(() => usePage().props?.theme || { primary: '#1f2937' })

// Estado de filtros
const fechaInicio = ref(props.filtros.fecha_inicio || '')
const fechaFin = ref(props.filtros.fecha_fin || '')
const search = ref(props.filtros.search || '')

// Estados
const exportandoExcel = ref(false)
const isMobile = ref(false)

const checkScreenSize = () => {
    isMobile.value = window.innerWidth < 640
}

// Formatear números
const formatNumber = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toLocaleString('es-BO')
}

// Formatear fecha
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha + 'T00:00:00').toLocaleDateString('es-BO')
}

const formatearFechaHora = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleString('es-BO')
}

// Aplicar filtros
const aplicarFiltros = () => {
    router.get('/operacion/pedidos/reportes/informe-sucursal-mayorista', {
        fecha_inicio: fechaInicio.value,
        fecha_fin: fechaFin.value,
        search: search.value || undefined
    }, { preserveState: true, replace: true })
}

// Limpiar filtros
const limpiarFiltros = () => {
    const hoy = new Date().toISOString().split('T')[0]
    fechaInicio.value = hoy
    const futuro = new Date()
    futuro.setDate(futuro.getDate() + 30)
    fechaFin.value = futuro.toISOString().split('T')[0]
    search.value = ''
    aplicarFiltros()
}

// Exportar Excel
const exportarExcel = () => {
    exportandoExcel.value = true
    try {
        const params = new URLSearchParams({
            fecha_inicio: fechaInicio.value,
            fecha_fin: fechaFin.value,
            search: search.value || ''
        })
        window.location.href = '/operacion/pedidos/reportes/informe-sucursal-mayorista/exportar-excel?' + params.toString()
    } catch (error) {
        console.error('Error exportando Excel:', error)
        alert('Error al exportar Excel')
    } finally {
        exportandoExcel.value = false
    }
}

// Debounce para búsqueda
let timeout
watch(search, () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        aplicarFiltros()
    }, 500)
})

onMounted(() => {
    checkScreenSize()
    window.addEventListener('resize', checkScreenSize)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkScreenSize)
    if (timeout) clearTimeout(timeout)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-3 px-2 sm:py-4 sm:px-4 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex flex-col items-center text-center mb-4 sm:mb-6">
                    <div class="inline-flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 lg:w-14 lg:h-14 bg-purple-100 rounded-xl sm:rounded-2xl mb-1 sm:mb-2">
                        <i class="fas fa-store text-base sm:text-lg lg:text-xl text-purple-600"></i>
                    </div>
                    <h1 class="text-base sm:text-lg lg:text-xl font-bold text-gray-900">Pedidos - Sucursal Mayorista</h1>
                    <p class="text-[10px] sm:text-xs text-gray-500">Listado de pedidos de la sucursal y operador actual</p>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-lg sm:rounded-xl shadow-sm p-3 sm:p-4 mb-4 sm:mb-6">
                    <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-end gap-3 sm:gap-4">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                            <input 
                                type="date" 
                                v-model="fechaInicio" 
                                @change="aplicarFiltros"
                                class="w-full sm:w-40 border border-gray-300 rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm focus:ring-purple-500 focus:border-purple-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                            <input 
                                type="date" 
                                v-model="fechaFin" 
                                @change="aplicarFiltros"
                                class="w-full sm:w-40 border border-gray-300 rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm focus:ring-purple-500 focus:border-purple-500"
                            />
                        </div>
                        <div class="flex-1 min-w-[120px]">
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Buscar Producto</label>
                            <input 
                                type="text" 
                                v-model="search" 
                                placeholder="Código o descripción..."
                                class="w-full border border-gray-300 rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm focus:ring-purple-500 focus:border-purple-500"
                            />
                        </div>
                        <div class="flex gap-2">
                            <button 
                                @click="aplicarFiltros"
                                class="px-3 sm:px-4 py-1.5 sm:py-2 bg-purple-600 text-white rounded-lg text-xs sm:text-sm hover:bg-purple-700 transition flex items-center gap-1 sm:gap-2"
                            >
                                <i class="fas fa-search text-[10px] sm:text-xs"></i>
                                <span>Buscar</span>
                            </button>
                            <button 
                                @click="limpiarFiltros"
                                class="px-3 sm:px-4 py-1.5 sm:py-2 bg-gray-200 text-gray-700 rounded-lg text-xs sm:text-sm hover:bg-gray-300 transition flex items-center gap-1 sm:gap-2"
                            >
                                <i class="fas fa-eraser text-[10px] sm:text-xs"></i>
                                <span>Limpiar</span>
                            </button>
                            <button 
                                @click="exportarExcel"
                                :disabled="exportandoExcel || pedidos.length === 0"
                                class="px-3 sm:px-4 py-1.5 sm:py-2 bg-green-600 text-white rounded-lg text-xs sm:text-sm hover:bg-green-700 transition flex items-center gap-1 sm:gap-2 disabled:opacity-50"
                            >
                                <i v-if="exportandoExcel" class="fas fa-spinner fa-spin text-[10px] sm:text-xs"></i>
                                <i v-else class="fas fa-file-excel text-[10px] sm:text-xs"></i>
                                <span>{{ exportandoExcel ? '...' : 'Excel' }}</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Información de cabecera -->
                    <div v-if="pedidos.length > 0" class="mt-3 pt-3 border-t grid grid-cols-1 xs:grid-cols-3 gap-1 text-[10px] sm:text-xs text-gray-600">
                        <div>
                            <span class="font-medium">Sucursal:</span> {{ sucursal?.Nombre || '-' }}
                        </div>
                        <div>
                            <span class="font-medium">Operador:</span> {{ operador?.nombre || '-' }}
                        </div>
                        <div>
                            <span class="font-medium">Total pedidos:</span> {{ formatNumber(totales.total_pedidos) }}
                        </div>
                    </div>
                </div>

                <!-- Tarjetas de resumen -->
                <div v-if="pedidos.length > 0" class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mb-4 sm:mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-2 sm:p-3">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-purple-600 text-[10px] sm:text-xs"></i>
                            </div>
                            <div>
                                <p class="text-[8px] sm:text-[10px] text-gray-500">Pedidos</p>
                                <p class="text-sm sm:text-base font-bold text-gray-800">{{ formatNumber(totales.total_pedidos) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-2 sm:p-3">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-boxes text-green-600 text-[10px] sm:text-xs"></i>
                            </div>
                            <div>
                                <p class="text-[8px] sm:text-[10px] text-gray-500">Unidades</p>
                                <p class="text-sm sm:text-base font-bold text-gray-800">{{ formatNumber(totales.total_unidades) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-2 sm:p-3">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-box text-blue-600 text-[10px] sm:text-xs"></i>
                            </div>
                            <div>
                                <p class="text-[8px] sm:text-[10px] text-gray-500">Productos</p>
                                <p class="text-sm sm:text-base font-bold text-gray-800">{{ formatNumber(totales.total_productos) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-2 sm:p-3">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar-day text-yellow-600 text-[10px] sm:text-xs"></i>
                            </div>
                            <div>
                                <p class="text-[8px] sm:text-[10px] text-gray-500">Días</p>
                                <p class="text-sm sm:text-base font-bold text-gray-800">{{ resumenPorFecha.length }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de pedidos -->
                <div v-if="pedidos.length > 0" class="bg-white rounded-lg sm:rounded-xl shadow-sm overflow-hidden">
                    <div class="px-3 sm:px-4 py-2 sm:py-3 bg-gray-50 border-b flex items-center justify-between">
                        <h3 class="text-xs sm:text-sm font-semibold text-gray-700">
                            <i class="fas fa-list text-purple-500 mr-1 sm:mr-2"></i>
                            Lista de Pedidos
                        </h3>
                        <span class="text-[10px] sm:text-xs text-gray-500">{{ pedidos.length }} registro(s)</span>
                    </div>

                    <div class="overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="pedido in pedidos" :key="pedido.IdPedidos" class="bg-gray-50 rounded-lg p-2 border border-gray-100">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-xs font-medium text-purple-600">#{{ pedido.IdPedidos }}</span>
                                    <span class="text-[10px] text-gray-400">{{ formatearFecha(pedido.FechaDelPedido) }}</span>
                                </div>
                                <p class="text-xs font-medium text-gray-800">{{ pedido.DestalleProducto || '-' }}</p>
                                <p class="text-[10px] text-gray-500">Código: {{ pedido.CodigoProducto || '-' }}</p>
                                <div class="flex justify-between items-center mt-1 pt-1 border-t border-gray-200">
                                    <span class="text-[10px] text-gray-500">{{ pedido.NombreSucursal || '-' }}</span>
                                    <span class="text-sm font-bold text-purple-600">{{ formatNumber(pedido.Unidades) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- VISTA ESCRITORIO (tabla) -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-3 py-2 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase">Fecha Realiza</th>
                                    <th class="px-3 py-2 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase">Fecha Pedido</th>
                                    <th class="px-3 py-2 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-3 py-2 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase">Código</th>
                                    <th class="px-3 py-2 text-center text-[10px] sm:text-xs font-medium text-gray-500 uppercase">Unidades</th>
                                    <th class="px-3 py-2 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase">Sucursal</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(pedido, idx) in pedidos" :key="pedido.IdPedidos" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ idx + 1 }}</td>
                                    <td class="px-3 py-2 text-xs">{{ formatearFechaHora(pedido.FechaRealiza) }}</td>
                                    <td class="px-3 py-2 text-xs font-medium">{{ formatearFecha(pedido.FechaDelPedido) }}</td>
                                    <td class="px-3 py-2 text-xs">
                                        <span class="font-medium text-gray-800">{{ pedido.DestalleProducto || '-' }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-xs font-mono text-gray-500">{{ pedido.CodigoProducto || '-' }}</td>
                                    <td class="px-3 py-2 text-center text-xs font-mono font-bold">{{ formatNumber(pedido.Unidades) }}</td>
                                    <td class="px-3 py-2 text-xs">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-purple-50 text-purple-700">
                                            <i class="fas fa-store mr-1 text-[8px]"></i>
                                            {{ pedido.NombreSucursal || '-' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="pedidos.length === 0">
                                    <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                                        <i class="fas fa-inbox text-3xl mb-2 block"></i>
                                        No hay pedidos para los filtros seleccionados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sin datos -->
                <div v-else class="bg-white rounded-lg sm:rounded-xl shadow-sm p-8 sm:p-12 text-center text-gray-400">
                    <i class="fas fa-inbox text-3xl sm:text-4xl mb-2 sm:mb-3 block"></i>
                    <p class="text-sm sm:text-base lg:text-lg font-medium">No hay pedidos registrados</p>
                    <p class="text-xs sm:text-sm mt-1">Ajusta los filtros para ver más resultados</p>
                </div>

                <!-- Footer -->
                <div v-if="pedidos.length > 0" class="mt-4 text-center text-[9px] sm:text-xs text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Reporte generado el {{ new Date().toLocaleString('es-BO') }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 480px) {
    .xs\:grid-cols-3 {
        grid-template-columns: repeat(3, 1fr);
    }
}

.overflow-x-auto {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

.overflow-x-auto::-webkit-scrollbar {
    height: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
}
</style>