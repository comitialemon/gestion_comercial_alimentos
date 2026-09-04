<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { usePage } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productosAgrupados: {
        type: Array,
        default: () => []
    },
    sucursales: {
        type: Array,
        default: () => []
    },
    totales: {
        type: Object,
        default: () => ({
            total_productos: 0,
            total_pedidos: 0,
            total_unidades: 0,
            total_sucursales: 0
        })
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

// Estado para expandir productos
const expandedProducts = ref({})

// ==================== COMPUTED ====================
const theme = computed(() => usePage().props?.theme || { primary: '#1f2937' })
const primaryColor = computed(() => theme.value.primary || '#1f2937')

const hayDatos = computed(() => props.productosAgrupados && props.productosAgrupados.length > 0)

// ==================== FUNCIONES ====================
const toggleProduct = (productoId) => {
    expandedProducts.value[productoId] = !expandedProducts.value[productoId]
}

const aplicarFiltros = () => {
    router.get('/operacion/pedidos/reportes/informe-supervisor', {
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
                    <div class="w-9 h-9 bg-indigo-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-clipboard-list text-indigo-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Detalle Pedido - Supervisor</h1>
                        <p class="text-xs text-gray-500">Listado de pedidos agrupados por producto</p>
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
                        </div>
                    </div>
                </div>

                <!-- ==================== RESÚMENES ==================== -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
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
                </div>

                <!-- ==================== LISTA DE PRODUCTOS ==================== -->
                <div v-if="hayDatos" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                            <i class="fas fa-list text-indigo-500 text-[10px]"></i>
                            Productos con pedidos
                        </h3>
                        <span class="text-[10px] text-gray-500">{{ productosAgrupados.length }} producto(s)</span>
                    </div>

                    <div class="overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="(producto, idx) in productosAgrupados" :key="producto.IdProducto" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-xs font-medium text-indigo-600">#{{ idx + 1 }}</span>
                                    <span class="text-[10px] text-gray-400">{{ producto.total_unidades }} und</span>
                                </div>
                                <p class="text-xs font-medium text-gray-800 truncate">{{ producto.DestalleProducto }}</p>
                                <p class="text-[9px] text-gray-500">ID: {{ producto.IdProducto }}</p>
                                <div class="flex justify-between items-center mt-1.5 pt-1.5 border-t border-gray-200">
                                    <span class="text-[10px] text-gray-500">{{ producto.total_sucursales }} sucursales</span>
                                    <button @click="toggleProduct(producto.IdProducto)" 
                                        class="text-indigo-600 hover:text-indigo-800 text-[10px] flex items-center gap-1">
                                        {{ expandedProducts[producto.IdProducto] ? 'Ocultar' : 'Ver' }}
                                        <i class="fas" :class="expandedProducts[producto.IdProducto] ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </button>
                                </div>
                                <!-- Detalle expandido móvil -->
                                <div v-if="expandedProducts[producto.IdProducto]" class="mt-2 pt-2 border-t border-gray-200 space-y-2">
                                    <div v-for="sucursal in producto.sucursales" :key="sucursal.IdSucursal" class="bg-white rounded p-2 border border-gray-200">
                                        <div class="text-xs font-medium text-gray-700">{{ sucursal.NombreSucursal }}</div>
                                        <div class="text-[9px] text-gray-500">{{ sucursal.Operador }} - {{ sucursal.NombreSolicitud }}</div>
                                        <div class="text-right text-xs font-bold text-indigo-600">{{ formatNumber(sucursal.total_unidades) }} und</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- VISTA TABLET Y ESCRITORIO -->
                        <div v-else>
                            <!-- Cabecera fija -->
                            <div class="grid grid-cols-12 gap-1 px-3 py-1.5 bg-gray-50 border-b border-gray-200 text-[8px] font-medium text-gray-500 uppercase sticky top-0 z-10">
                                <div class="col-span-1">#</div>
                                <div class="col-span-3">Producto</div>
                                <div class="col-span-3">Fechas</div>
                                <div class="col-span-2 text-center">Unidades</div>
                                <div class="col-span-2 text-center">Sucursales</div>
                                <div class="col-span-1 text-center">Detalle</div>
                            </div>

                            <div class="divide-y divide-gray-200">
                                <div v-for="(producto, idx) in productosAgrupados" :key="producto.IdProducto">
                                    <!-- Fila del producto -->
                                    <div class="hover:bg-gray-50 cursor-pointer transition" @click="toggleProduct(producto.IdProducto)">
                                        <div class="grid grid-cols-12 gap-1 px-3 py-2 items-center">
                                            <div class="col-span-1 text-xs text-gray-500">{{ idx + 1 }}</div>
                                            <div class="col-span-3">
                                                <div class="flex flex-col">
                                                    <span class="font-medium text-gray-800 text-xs truncate">{{ producto.DestalleProducto }}</span>
                                                    <span class="text-[8px] text-gray-400">ID: {{ producto.IdProducto }}</span>
                                                </div>
                                            </div>
                                            <div class="col-span-3 text-[10px] text-gray-600 truncate">{{ producto.Fechas }}</div>
                                            <div class="col-span-2 text-center text-xs font-bold">{{ formatNumber(producto.total_unidades) }}</div>
                                            <div class="col-span-2 text-center text-[10px]">{{ producto.total_sucursales }}</div>
                                            <div class="col-span-1 text-center">
                                                <i class="fas text-indigo-500 text-[10px] transition-transform"
                                                    :class="expandedProducts[producto.IdProducto] ? 'fa-chevron-up' : 'fa-chevron-down'">
                                                </i>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Detalle expandido -->
                                    <div v-if="expandedProducts[producto.IdProducto]" class="bg-gray-50 px-3 py-2">
                                        <div class="text-[10px] font-semibold text-gray-500 mb-1.5">
                                            <i class="fas fa-store mr-1"></i> Detalle por sucursal
                                        </div>

                                        <div v-for="sucursal in producto.sucursales" :key="sucursal.IdSucursal" class="mb-2 last:mb-0">
                                            <div class="bg-white rounded-lg shadow-sm p-2.5 border border-gray-200">
                                                <!-- Cabecera de sucursal -->
                                                <div class="grid grid-cols-4 gap-2 mb-1.5 text-[10px] font-medium text-gray-700 border-b border-gray-100 pb-1.5">
                                                    <div>
                                                        <i class="fas fa-store text-indigo-500 text-[8px] mr-0.5"></i>
                                                        {{ sucursal.NombreSucursal }}
                                                    </div>
                                                    <div>
                                                        <i class="fas fa-user text-blue-500 text-[8px] mr-0.5"></i>
                                                        {{ sucursal.Operador }}
                                                    </div>
                                                    <div>
                                                        <i class="fas fa-tag text-emerald-500 text-[8px] mr-0.5"></i>
                                                        {{ sucursal.NombreSolicitud }}
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="font-bold">{{ formatNumber(sucursal.total_unidades) }}</span> und
                                                    </div>
                                                </div>

                                                <!-- Tabla de pedidos -->
                                                <table class="min-w-full divide-y divide-gray-200 text-xs">
                                                    <thead>
                                                        <tr class="text-[8px] text-gray-500">
                                                            <th class="px-2 py-0.5 text-left">Fecha Realiza</th>
                                                            <th class="px-2 py-0.5 text-left">Fecha Producción</th>
                                                            <th class="px-2 py-0.5 text-right">Unidades</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100">
                                                        <tr v-for="pedido in sucursal.pedidos" :key="pedido.IdPedidos" class="hover:bg-gray-50">
                                                            <td class="px-2 py-0.5 text-[10px]">{{ pedido.FechaRealiza }}</td>
                                                            <td class="px-2 py-0.5 text-[10px]">{{ pedido.FechaDelPedido }}</td>
                                                            <td class="px-2 py-0.5 text-right font-mono text-[10px]">{{ pedido.Unidades }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="productosAgrupados.length === 0" class="px-4 py-10 text-center text-gray-400 text-sm">
                                    <i class="fas fa-inbox text-2xl mb-1 block"></i>
                                    No hay pedidos para los filtros seleccionados
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== SIN DATOS ==================== -->
                <div v-else-if="fechaInicio || fechaFin" class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2 block"></i>
                    <p class="text-sm font-medium">No hay pedidos para los filtros seleccionados</p>
                    <p class="text-xs mt-1">Prueba ajustando el rango de fechas o los filtros aplicados</p>
                </div>

                <!-- ==================== MENSAJE INICIAL ==================== -->
                <div v-else class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
                    <i class="fas fa-calendar-alt text-3xl mb-2 block text-indigo-300"></i>
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