<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { usePage } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    datos: {
        type: Array,
        default: () => []
    },
    totales: {
        type: Object,
        default: () => ({
            total_sucursales: 0,
            total_productos: 0,
            total_pedidos: 0,
            total_unidades: 0
        })
    },
    sucursales: {
        type: Array,
        default: () => []
    },
    filtros: {
        type: Object,
        default: () => ({
            fecha_inicio: '',
            fecha_fin: '',
            sucursal_id: null,
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
const sucursalId = ref(props.filtros.sucursal_id || '')
const search = ref(props.filtros.search || '')

// Estado para expandir sucursales y productos
const expandedSucursales = ref({})
const expandedProductos = ref({})

// ==================== COMPUTED ====================
const theme = computed(() => usePage().props?.theme || { primary: '#1f2937' })
const primaryColor = computed(() => theme.value.primary || '#1f2937')

const hayDatos = computed(() => {
    return props.datos && props.datos.length > 0
})

const hayFiltros = computed(() => {
    return fechaInicio.value || fechaFin.value
})

// ==================== FUNCIONES ====================
const toggleSucursal = (sucursalId) => {
    expandedSucursales.value[sucursalId] = !expandedSucursales.value[sucursalId]
}

const toggleProducto = (key) => {
    expandedProductos.value[key] = !expandedProductos.value[key]
}

const aplicarFiltros = () => {
    router.get('/operacion/pedidos/reportes/informe-gerente', {
        fecha_inicio: fechaInicio.value,
        fecha_fin: fechaFin.value,
        sucursal_id: sucursalId.value || undefined,
        search: search.value || undefined
    }, { preserveState: true, replace: true })
}

const limpiarFiltros = () => {
    fechaInicio.value = ''
    fechaFin.value = ''
    sucursalId.value = ''
    search.value = ''
    aplicarFiltros()
}

const formatNumber = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toLocaleString('es-BO')
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
                    <div class="w-9 h-9 bg-emerald-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-chart-pie text-emerald-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Detalle Pedido - Gerente</h1>
                        <p class="text-xs text-gray-500">Listado de pedidos agrupados por sucursal y producto</p>
                    </div>
                </div>

                <!-- ==================== FILTROS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Fecha Inicio</label>
                            <input type="date" v-model="fechaInicio" @change="aplicarFiltros"
                                class="w-36 border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Fecha Fin</label>
                            <input type="date" v-model="fechaFin" @change="aplicarFiltros"
                                class="w-36 border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Sucursal</label>
                            <select v-model="sucursalId" @change="aplicarFiltros"
                                class="w-40 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                                <option value="">Todas las sucursales</option>
                                <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">
                                    {{ sucursal.texto }}
                                </option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[120px] max-w-[200px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Buscar Producto</label>
                            <input type="text" v-model="search" placeholder="Código o descripción..."
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
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
                        </div>
                    </div>
                </div>

                <!-- ==================== RESÚMENES ==================== -->
                <div v-if="hayDatos" class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                    <div class="bg-white rounded-xl shadow-sm p-2.5 border border-gray-200">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-store text-purple-600 text-[10px]"></i>
                            </div>
                            <div>
                                <p class="text-[8px] text-gray-400 uppercase tracking-wide">Sucursales</p>
                                <p class="text-base font-bold text-gray-800">{{ formatNumber(totales.total_sucursales) }}</p>
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
                            <div class="w-7 h-7 bg-emerald-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-emerald-600 text-[10px]"></i>
                            </div>
                            <div>
                                <p class="text-[8px] text-gray-400 uppercase tracking-wide">Pedidos</p>
                                <p class="text-base font-bold text-gray-800">{{ formatNumber(totales.total_pedidos) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-2.5 border border-gray-200">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-boxes text-yellow-600 text-[10px]"></i>
                            </div>
                            <div>
                                <p class="text-[8px] text-gray-400 uppercase tracking-wide">Unidades</p>
                                <p class="text-base font-bold text-gray-800">{{ formatNumber(totales.total_unidades) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== LISTA DE DATOS ==================== -->
                <div v-if="hayDatos" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                            <i class="fas fa-list text-emerald-500 text-[10px]"></i>
                            Pedidos por Sucursal
                        </h3>
                        <span class="text-[10px] text-gray-500">{{ datos.length }} sucursal(es)</span>
                    </div>

                    <div class="divide-y divide-gray-200">
                        <div v-for="sucursal in datos" :key="sucursal.IdSucursal">
                            <!-- Cabecera de Sucursal -->
                            <div class="hover:bg-gray-50 cursor-pointer transition" @click="toggleSucursal(sucursal.IdSucursal)">
                                <div class="grid grid-cols-12 gap-1 px-3 py-2 items-center">
                                    <div class="col-span-1">
                                        <i class="fas text-emerald-500 text-[10px] transition-transform"
                                            :class="expandedSucursales[sucursal.IdSucursal] ? 'fa-chevron-down' : 'fa-chevron-right'">
                                        </i>
                                    </div>
                                    <div class="col-span-4">
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-store text-emerald-600 text-[10px]"></i>
                                            <span class="font-semibold text-gray-800 text-xs">{{ sucursal.NombreSucursal }}</span>
                                        </div>
                                    </div>
                                    <div class="col-span-3 text-[10px] text-gray-500">
                                        <i class="fas fa-user mr-1 text-[8px]"></i> {{ sucursal.Operador }}
                                    </div>
                                    <div class="col-span-2 text-center text-[10px]">
                                        <span class="font-medium">{{ formatNumber(sucursal.total_productos) }}</span> productos
                                    </div>
                                    <div class="col-span-2 text-right text-[10px] font-bold text-emerald-600">
                                        {{ formatNumber(sucursal.total_unidades) }} und
                                    </div>
                                </div>
                            </div>

                            <!-- Detalle de la sucursal -->
                            <div v-if="expandedSucursales[sucursal.IdSucursal]" class="bg-gray-50 px-3 py-2">
                                <div v-for="producto in sucursal.productos" :key="producto.IdProducto" class="mb-2 last:mb-0">
                                    <!-- Cabecera de Producto -->
                                    <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition cursor-pointer"
                                        @click="toggleProducto(`${sucursal.IdSucursal}-${producto.IdProducto}`)">
                                        <div class="grid grid-cols-12 gap-1 px-3 py-1.5 items-center">
                                            <div class="col-span-1">
                                                <i class="fas text-blue-500 text-[10px] transition-transform"
                                                    :class="expandedProductos[`${sucursal.IdSucursal}-${producto.IdProducto}`] ? 'fa-chevron-down' : 'fa-chevron-right'">
                                                </i>
                                            </div>
                                            <div class="col-span-6">
                                                <div class="flex flex-col">
                                                    <span class="font-medium text-gray-800 text-xs">{{ producto.DestalleProducto }}</span>
                                                    <span class="text-[8px] text-gray-400">ID: {{ producto.IdProducto }}</span>
                                                </div>
                                            </div>
                                            <div class="col-span-2 text-center text-[10px] text-gray-500">
                                                {{ producto.total_pedidos }} pedidos
                                            </div>
                                            <div class="col-span-3 text-right text-[10px] font-bold text-blue-600">
                                                {{ formatNumber(producto.total_unidades) }} und
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Detalle de pedidos del producto -->
                                    <div v-if="expandedProductos[`${sucursal.IdSucursal}-${producto.IdProducto}`]" class="mt-1.5 ml-6">
                                        <table class="min-w-full divide-y divide-gray-200 bg-white rounded-lg overflow-hidden shadow-sm text-xs">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-3 py-1 text-left text-[8px] font-medium text-gray-500 uppercase">Fecha Realiza</th>
                                                    <th class="px-3 py-1 text-left text-[8px] font-medium text-gray-500 uppercase">Fecha Producción</th>
                                                    <th class="px-3 py-1 text-right text-[8px] font-medium text-gray-500 uppercase">Unidades</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr v-for="pedido in producto.pedidos" :key="pedido.IdPedidos" class="hover:bg-gray-50">
                                                    <td class="px-3 py-1 text-[10px]">{{ pedido.FechaRealiza }}</td>
                                                    <td class="px-3 py-1 text-[10px]">{{ pedido.FechaDelPedido }}</td>
                                                    <td class="px-3 py-1 text-right text-[10px] font-mono font-bold">{{ pedido.Unidades }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== SIN DATOS ==================== -->
                <div v-else-if="hayFiltros" class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2 block"></i>
                    <p class="text-sm font-medium">No hay pedidos para los filtros seleccionados</p>
                    <p class="text-xs mt-1">Prueba ajustando el rango de fechas o los filtros aplicados</p>
                </div>

                <!-- ==================== MENSAJE INICIAL ==================== -->
                <div v-else class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-calendar-alt text-3xl mb-2 block text-emerald-300"></i>
                    <p class="text-sm font-medium">Selecciona un rango de fechas</p>
                    <p class="text-xs mt-1">Define la fecha de inicio y fin para generar el informe</p>
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

@media (max-width: 640px) {
    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
    }
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