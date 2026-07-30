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

const theme = computed(() => usePage().props?.theme || { primary: '#1f2937' })
const primaryColor = computed(() => theme.value.primary || '#1f2937')

// Estado de filtros
const fechaInicio = ref(props.filtros.fecha_inicio || '')
const fechaFin = ref(props.filtros.fecha_fin || '')
const sucursalId = ref(props.filtros.sucursal_id || '')
const search = ref(props.filtros.search || '')

// Estado para expandir productos
const expandedProducts = ref({})

const toggleProduct = (productoId) => {
    expandedProducts.value[productoId] = !expandedProducts.value[productoId]
}

// Aplicar filtros
const aplicarFiltros = () => {
    router.get('/operacion/pedidos/reportes/informe-supervisor', {
        fecha_inicio: fechaInicio.value,
        fecha_fin: fechaFin.value,
        sucursal_id: sucursalId.value || undefined,
        search: search.value || undefined
    }, { preserveState: true, replace: true })
}

// Limpiar filtros
const limpiarFiltros = () => {
    fechaInicio.value = ''
    fechaFin.value = ''
    sucursalId.value = ''
    search.value = ''
    aplicarFiltros()
}

// Formatear números
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

// Estado responsive
const isMobile = ref(false)
const isTablet = ref(false)

const checkScreenSize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

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
        <div class="py-4 px-4 sm:px-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="flex flex-col items-center text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-100 rounded-2xl mb-3">
                        <i class="fas fa-clipboard-list text-xl text-indigo-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Detalle Pedido - Supervisor</h1>
                    <p class="text-xs text-gray-500">Listado de pedidos agrupados por producto</p>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha Inicio</label>
                            <input 
                                type="date" 
                                v-model="fechaInicio" 
                                @change="aplicarFiltros"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha Fin</label>
                            <input 
                                type="date" 
                                v-model="fechaFin" 
                                @change="aplicarFiltros"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Sucursal</label>
                            <select 
                                v-model="sucursalId" 
                                @change="aplicarFiltros"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">Todas las sucursales</option>
                                <option 
                                    v-for="sucursal in sucursales" 
                                    :key="sucursal.id"
                                    :value="sucursal.id"
                                >
                                    {{ sucursal.texto }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Buscar Producto</label>
                            <input 
                                type="text" 
                                v-model="search" 
                                placeholder="Código o descripción..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                        </div>
                    </div>
                    <div class="flex justify-end mt-3">
                        <button 
                            @click="limpiarFiltros"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300 transition"
                        >
                            <i class="fas fa-eraser mr-1"></i> Limpiar filtros
                        </button>
                    </div>
                </div>

                <!-- Tarjetas de resumen -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-box text-blue-600 text-xs"></i>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-500">Productos</p>
                                <p class="text-lg font-bold text-gray-800">{{ formatNumber(totales.total_productos) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-green-600 text-xs"></i>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-500">Pedidos</p>
                                <p class="text-lg font-bold text-gray-800">{{ formatNumber(totales.total_pedidos) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-boxes text-yellow-600 text-xs"></i>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-500">Unidades</p>
                                <p class="text-lg font-bold text-gray-800">{{ formatNumber(totales.total_unidades) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-store text-purple-600 text-xs"></i>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-500">Sucursales</p>
                                <p class="text-lg font-bold text-gray-800">{{ formatNumber(totales.total_sucursales) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grid de productos -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">
                            <i class="fas fa-list text-indigo-500 mr-2"></i>
                            Productos con pedidos
                        </h3>
                        <span class="text-xs text-gray-500">{{ productosAgrupados.length }} producto(s)</span>
                    </div>

                    <div class="overflow-x-auto">
                        <!-- Cabecera de la tabla -->
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-10">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fechas</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Unidades</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Sucursales</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-12">Detalle</th>
                                </tr>
                            </thead>
                        </table>

                        <!-- Cuerpo con expansión -->
                        <div class="divide-y divide-gray-200">
                            <div v-for="(producto, idx) in productosAgrupados" :key="producto.IdProducto">
                                <!-- Fila del producto -->
                                <div 
                                    class="hover:bg-gray-50 cursor-pointer transition"
                                    @click="toggleProduct(producto.IdProducto)"
                                >
                                    <div class="grid grid-cols-12 gap-2 px-4 py-3 items-center">
                                        <div class="col-span-1 text-sm text-gray-500">{{ idx + 1 }}</div>
                                        <div class="col-span-3">
                                            <div class="flex flex-col">
                                                <span class="font-medium text-gray-800 text-sm">{{ producto.DestalleProducto }}</span>
                                                <span class="text-xs text-gray-400">ID: {{ producto.IdProducto }}</span>
                                            </div>
                                        </div>
                                        <div class="col-span-3 text-sm text-gray-600">{{ producto.Fechas }}</div>
                                        <div class="col-span-2 text-center text-sm font-bold">{{ formatNumber(producto.total_unidades) }}</div>
                                        <div class="col-span-2 text-center text-sm">{{ producto.total_sucursales }}</div>
                                        <div class="col-span-1 text-center">
                                            <i 
                                                class="fas text-indigo-500 transition-transform"
                                                :class="expandedProducts[producto.IdProducto] ? 'fa-chevron-up' : 'fa-chevron-down'"
                                            ></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- Detalle expandido -->
                                <div v-if="expandedProducts[producto.IdProducto]" class="bg-gray-50 px-4 py-3">
                                    <div class="mb-2 text-xs font-semibold text-gray-500">
                                        <i class="fas fa-store mr-1"></i> Detalle por sucursal
                                    </div>
                                    
                                    <!-- Detalle por sucursal -->
                                    <div v-for="sucursal in producto.sucursales" :key="sucursal.IdSucursal" class="mb-4 last:mb-0">
                                        <div class="bg-white rounded-lg shadow-sm p-3">
                                            <!-- Cabecera de sucursal -->
                                            <div class="grid grid-cols-4 gap-2 mb-2 text-sm font-medium text-gray-700 border-b pb-2">
                                                <div>
                                                    <i class="fas fa-store text-indigo-500 mr-1"></i>
                                                    {{ sucursal.NombreSucursal }}
                                                </div>
                                                <div>
                                                    <i class="fas fa-user text-blue-500 mr-1"></i>
                                                    {{ sucursal.Operador }}
                                                </div>
                                                <div>
                                                    <i class="fas fa-tag text-green-500 mr-1"></i>
                                                    {{ sucursal.NombreSolicitud }}
                                                </div>
                                                <div class="text-right">
                                                    <span class="font-bold">{{ formatNumber(sucursal.total_unidades) }}</span> unidades
                                                </div>
                                            </div>
                                            
                                            <!-- Tabla de pedidos de la sucursal -->
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead>
                                                    <tr class="text-xs text-gray-500">
                                                        <th class="px-3 py-1 text-left">Fecha Realiza</th>
                                                        <th class="px-3 py-1 text-left">Fecha Producción</th>
                                                        <th class="px-3 py-1 text-right">Unidades</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                    <tr v-for="pedido in sucursal.pedidos" :key="pedido.IdPedidos" class="text-sm hover:bg-gray-50">
                                                        <td class="px-3 py-1.5">{{ pedido.FechaRealiza }}</td>
                                                        <td class="px-3 py-1.5">{{ pedido.FechaDelPedido }}</td>
                                                        <td class="px-3 py-1.5 text-right font-mono">{{ pedido.Unidades }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="productosAgrupados.length === 0" class="px-4 py-12 text-center text-gray-400">
                                <i class="fas fa-inbox text-3xl mb-2 block"></i>
                                No hay pedidos para los filtros seleccionados
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-4 text-center text-xs text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Reporte generado el {{ new Date().toLocaleString('es-BO') }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.sticky {
    position: sticky;
}

@media (max-width: 640px) {
    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
    }
}
</style>