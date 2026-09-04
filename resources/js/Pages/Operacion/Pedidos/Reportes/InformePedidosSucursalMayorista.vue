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

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const fechaInicio = ref(props.filtros.fecha_inicio || '')
const fechaFin = ref(props.filtros.fecha_fin || '')
const search = ref(props.filtros.search || '')
const exportandoExcel = ref(false)

// ==================== COMPUTED ====================
const theme = computed(() => usePage().props?.theme || { primary: '#1f2937' })

const hayDatos = computed(() => props.pedidos && props.pedidos.length > 0)

// ==================== FUNCIONES ====================
const formatNumber = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toLocaleString('es-BO')
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha + 'T00:00:00').toLocaleDateString('es-BO')
}

const formatearFechaHora = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleString('es-BO')
}

const aplicarFiltros = () => {
    router.get('/operacion/pedidos/reportes/informe-sucursal-mayorista', {
        fecha_inicio: fechaInicio.value,
        fecha_fin: fechaFin.value,
        search: search.value || undefined
    }, { preserveState: true, replace: true })
}

const limpiarFiltros = () => {
    const hoy = new Date().toISOString().split('T')[0]
    fechaInicio.value = hoy
    const futuro = new Date()
    futuro.setDate(futuro.getDate() + 30)
    fechaFin.value = futuro.toISOString().split('T')[0]
    search.value = ''
    aplicarFiltros()
}

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

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    if (timeout) clearTimeout(timeout)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-4 px-4 sm:py-5 sm:px-6 lg:py-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-purple-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-store text-purple-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Pedidos - Sucursal Mayorista</h1>
                        <p class="text-xs text-gray-500">Listado de pedidos de la sucursal y operador actual</p>
                    </div>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Fecha Inicio</label>
                            <input type="date" v-model="fechaInicio" @change="aplicarFiltros"
                                class="w-36 border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Fecha Fin</label>
                            <input type="date" v-model="fechaFin" @change="aplicarFiltros"
                                class="w-36 border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                        </div>
                        <div class="flex-1 min-w-[120px] max-w-[200px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar Producto</label>
                            <input type="text" v-model="search" placeholder="Código o descripción..."
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            />
                        </div>
                        <div class="flex gap-1.5">
                            <button @click="aplicarFiltros"
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition flex items-center gap-1.5"
                            >
                                <i class="fas fa-search text-[10px]"></i> Buscar
                            </button>
                            <button @click="limpiarFiltros"
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1.5"
                            >
                                <i class="fas fa-eraser text-[10px]"></i> Limpiar
                            </button>
                            <button @click="exportarExcel" :disabled="exportandoExcel || !hayDatos"
                                class="px-3 py-1.5 bg-emerald-600 text-white rounded-md text-xs font-medium hover:bg-emerald-700 transition flex items-center gap-1.5 disabled:opacity-50"
                            >
                                <i v-if="exportandoExcel" class="fas fa-spinner fa-spin text-[10px]"></i>
                                <i v-else class="fas fa-file-excel text-[10px]"></i>
                                Excel
                            </button>
                        </div>
                    </div>

                    <!-- Información de cabecera -->
                    <div v-if="hayDatos" class="mt-2 pt-2 border-t border-gray-200 flex flex-wrap gap-3 text-[10px] text-gray-600">
                        <span><span class="font-medium">Sucursal:</span> {{ sucursal?.Nombre || '-' }}</span>
                        <span><span class="font-medium">Operador:</span> {{ operador?.nombre || '-' }}</span>
                        <span><span class="font-medium">Total pedidos:</span> {{ formatNumber(totales.total_pedidos) }}</span>
                    </div>
                </div>

                <!-- ==================== RESÚMENES ==================== -->
                <div v-if="hayDatos" class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                    <div class="bg-white rounded-xl shadow-sm p-2.5 border border-gray-200">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-purple-600 text-[10px]"></i>
                            </div>
                            <div>
                                <p class="text-[8px] text-gray-400 uppercase tracking-wide">Pedidos</p>
                                <p class="text-base font-bold text-gray-800">{{ formatNumber(totales.total_pedidos) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-2.5 border border-gray-200">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-emerald-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-boxes text-emerald-600 text-[10px]"></i>
                            </div>
                            <div>
                                <p class="text-[8px] text-gray-400 uppercase tracking-wide">Unidades</p>
                                <p class="text-base font-bold text-gray-800">{{ formatNumber(totales.total_unidades) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-2.5 border border-gray-200">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-box text-blue-600 text-[10px]"></i>
                            </div>
                            <div>
                                <p class="text-[8px] text-gray-400 uppercase tracking-wide">Productos</p>
                                <p class="text-base font-bold text-gray-800">{{ formatNumber(totales.total_productos) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-2.5 border border-gray-200">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar-day text-yellow-600 text-[10px]"></i>
                            </div>
                            <div>
                                <p class="text-[8px] text-gray-400 uppercase tracking-wide">Días</p>
                                <p class="text-base font-bold text-gray-800">{{ resumenPorFecha.length }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== TABLA ==================== -->
                <div v-if="hayDatos" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                            <i class="fas fa-list text-purple-500 text-[10px]"></i>
                            Lista de Pedidos
                        </h3>
                        <span class="text-[10px] text-gray-500">{{ pedidos.length }} registro(s)</span>
                    </div>

                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="pedido in pedidos" :key="pedido.IdPedidos" class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-xs font-medium text-purple-600">#{{ pedido.IdPedidos }}</span>
                                    <span class="text-[10px] text-gray-400">{{ formatearFecha(pedido.FechaDelPedido) }}</span>
                                </div>
                                <p class="text-xs font-medium text-gray-800 truncate">{{ pedido.DestalleProducto || '-' }}</p>
                                <p class="text-[9px] text-gray-500">Código: {{ pedido.CodigoProducto || '-' }}</p>
                                <div class="flex justify-between items-center mt-1.5 pt-1.5 border-t border-gray-200">
                                    <span class="text-[10px] text-gray-500 truncate">{{ pedido.NombreSucursal || '-' }}</span>
                                    <span class="text-sm font-bold text-purple-600">{{ formatNumber(pedido.Unidades) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-[8px] font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-3 py-1.5 text-left text-[8px] font-medium text-gray-500 uppercase">Fecha Realiza</th>
                                    <th class="px-3 py-1.5 text-left text-[8px] font-medium text-gray-500 uppercase">Fecha Pedido</th>
                                    <th class="px-3 py-1.5 text-left text-[8px] font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-3 py-1.5 text-left text-[8px] font-medium text-gray-500 uppercase">Código</th>
                                    <th class="px-3 py-1.5 text-center text-[8px] font-medium text-gray-500 uppercase w-20">Unidades</th>
                                    <th class="px-3 py-1.5 text-left text-[8px] font-medium text-gray-500 uppercase">Sucursal</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(pedido, idx) in pedidos" :key="pedido.IdPedidos" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-1.5 text-xs text-gray-500">{{ idx + 1 }}</td>
                                    <td class="px-3 py-1.5 text-xs">{{ formatearFechaHora(pedido.FechaRealiza) }}</td>
                                    <td class="px-3 py-1.5 text-xs font-medium">{{ formatearFecha(pedido.FechaDelPedido) }}</td>
                                    <td class="px-3 py-1.5 text-xs max-w-[150px] truncate" :title="pedido.DestalleProducto">
                                        <span class="font-medium text-gray-800">{{ pedido.DestalleProducto || '-' }}</span>
                                    </td>
                                    <td class="px-3 py-1.5 text-xs font-mono text-gray-500">{{ pedido.CodigoProducto || '-' }}</td>
                                    <td class="px-3 py-1.5 text-center text-xs font-mono font-bold">{{ formatNumber(pedido.Unidades) }}</td>
                                    <td class="px-3 py-1.5 text-xs">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] bg-purple-50 text-purple-700">
                                            <i class="fas fa-store mr-1 text-[7px]"></i>
                                            {{ pedido.NombreSucursal || '-' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="pedidos.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                                        <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                        No hay pedidos para los filtros seleccionados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== SIN DATOS ==================== -->
                <div v-else class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2 block"></i>
                    <p class="text-sm font-medium">No hay pedidos registrados</p>
                    <p class="text-xs mt-1">Ajusta los filtros para ver más resultados</p>
                </div>

                <!-- ==================== FOOTER ==================== -->
                <div v-if="hayDatos" class="mt-3 text-center text-[8px] text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Reporte generado el {{ new Date().toLocaleString('es-BO') }}
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

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>