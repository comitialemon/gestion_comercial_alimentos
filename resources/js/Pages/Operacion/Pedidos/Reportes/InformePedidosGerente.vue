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

const theme = computed(() => usePage().props?.theme || { primary: '#1f2937' })
const primaryColor = computed(() => theme.value.primary || '#1f2937')

// Estado de filtros
const fechaInicio = ref(props.filtros.fecha_inicio || '')
const fechaFin = ref(props.filtros.fecha_fin || '')
const sucursalId = ref(props.filtros.sucursal_id || '')
const search = ref(props.filtros.search || '')

// Estado para expandir sucursales y productos
const expandedSucursales = ref({})
const expandedProductos = ref({})

const toggleSucursal = (sucursalId) => {
    expandedSucursales.value[sucursalId] = !expandedSucursales.value[sucursalId]
}

const toggleProducto = (key) => {
    expandedProductos.value[key] = !expandedProductos.value[key]
}

// Aplicar filtros
const aplicarFiltros = () => {
    router.get('/operacion/pedidos/reportes/informe-gerente', {
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

const checkScreenSize = () => {
    isMobile.value = window.innerWidth < 640
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
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-emerald-100 rounded-2xl mb-3">
                        <i class="fas fa-chart-pie text-xl text-emerald-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Detalle Pedido - Gerente</h1>
                    <p class="text-xs text-gray-500">Listado de pedidos agrupados por sucursal y producto</p>
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
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha Fin</label>
                            <input 
                                type="date" 
                                v-model="fechaFin" 
                                @change="aplicarFiltros"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Sucursal</label>
                            <select 
                                v-model="sucursalId" 
                                @change="aplicarFiltros"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500"
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
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                            >
                        </div>
                    </div>
                    <div class="flex justify-end mt-3 gap-2">
                        <button 
                            @click="aplicarFiltros"
                            class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition"
                        >
                            <i class="fas fa-search mr-1"></i> Buscar
                        </button>
                        <button 
                            @click="limpiarFiltros"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300 transition"
                        >
                            <i class="fas fa-eraser mr-1"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Resumen de totales -->
                <div v-if="totales && totales.total_sucursales > 0" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
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
                </div>

                <!-- Grid de datos -->
                <div v-if="datos && datos.length > 0" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">
                            <i class="fas fa-list text-emerald-500 mr-2"></i>
                            Pedidos por Sucursal
                        </h3>
                        <span class="text-xs text-gray-500">{{ datos.length }} sucursal(es)</span>
                    </div>

                    <div class="divide-y divide-gray-200">
                        <!-- Iterar por cada sucursal -->
                        <div v-for="sucursal in datos" :key="sucursal.IdSucursal">
                            <!-- Cabecera de Sucursal (click para expandir) -->
                            <div 
                                class="hover:bg-gray-50 cursor-pointer transition"
                                @click="toggleSucursal(sucursal.IdSucursal)"
                            >
                                <div class="grid grid-cols-12 gap-2 px-4 py-3 items-center">
                                    <div class="col-span-1">
                                        <i 
                                            class="fas text-emerald-500 transition-transform"
                                            :class="expandedSucursales[sucursal.IdSucursal] ? 'fa-chevron-down' : 'fa-chevron-right'"
                                        ></i>
                                    </div>
                                    <div class="col-span-4">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-store text-emerald-600"></i>
                                            <span class="font-semibold text-gray-800">{{ sucursal.NombreSucursal }}</span>
                                        </div>
                                    </div>
                                    <div class="col-span-3 text-sm text-gray-500">
                                        <i class="fas fa-user mr-1"></i> {{ sucursal.Operador }}
                                    </div>
                                    <div class="col-span-2 text-center text-sm">
                                        <span class="font-medium">{{ formatNumber(sucursal.total_productos) }}</span> productos
                                    </div>
                                    <div class="col-span-2 text-right text-sm font-bold text-emerald-600">
                                        {{ formatNumber(sucursal.total_unidades) }} und
                                    </div>
                                </div>
                            </div>

                            <!-- Detalle de la sucursal (expandido) -->
                            <div v-if="expandedSucursales[sucursal.IdSucursal]" class="bg-gray-50 px-4 py-3">
                                <!-- Iterar por cada producto de la sucursal -->
                                <div v-for="producto in sucursal.productos" :key="producto.IdProducto" class="mb-3 last:mb-0">
                                    <!-- Cabecera de Producto (click para expandir) -->
                                    <div 
                                        class="bg-white rounded-lg shadow-sm hover:shadow-md transition cursor-pointer"
                                        @click="toggleProducto(`${sucursal.IdSucursal}-${producto.IdProducto}`)"
                                    >
                                        <div class="grid grid-cols-12 gap-2 px-4 py-2 items-center">
                                            <div class="col-span-1">
                                                <i 
                                                    class="fas text-blue-500 transition-transform"
                                                    :class="expandedProductos[`${sucursal.IdSucursal}-${producto.IdProducto}`] ? 'fa-chevron-down' : 'fa-chevron-right'"
                                                ></i>
                                            </div>
                                            <div class="col-span-6">
                                                <div class="flex flex-col">
                                                    <span class="font-medium text-gray-800 text-sm">{{ producto.DestalleProducto }}</span>
                                                    <span class="text-xs text-gray-400">ID: {{ producto.IdProducto }}</span>
                                                </div>
                                            </div>
                                            <div class="col-span-2 text-center text-sm text-gray-500">
                                                {{ producto.total_pedidos }} pedidos
                                            </div>
                                            <div class="col-span-3 text-right text-sm font-bold text-blue-600">
                                                {{ formatNumber(producto.total_unidades) }} und
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Detalle de pedidos del producto (expandido) -->
                                    <div v-if="expandedProductos[`${sucursal.IdSucursal}-${producto.IdProducto}`]" class="mt-2 ml-8">
                                        <table class="min-w-full divide-y divide-gray-200 bg-white rounded-lg overflow-hidden shadow-sm">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha Realiza</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha Producción</th>
                                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unidades</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr v-for="pedido in producto.pedidos" :key="pedido.IdPedidos" class="hover:bg-gray-50">
                                                    <td class="px-4 py-2 text-sm">{{ pedido.FechaRealiza }}</td>
                                                    <td class="px-4 py-2 text-sm">{{ pedido.FechaDelPedido }}</td>
                                                    <td class="px-4 py-2 text-right text-sm font-mono font-bold">{{ pedido.Unidades }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sin datos -->
                <div v-else-if="fechaInicio && fechaFin" class="bg-white rounded-xl shadow-sm p-12 text-center text-gray-400">
                    <i class="fas fa-inbox text-4xl mb-3 block"></i>
                    <p class="text-lg font-medium">No hay pedidos para los filtros seleccionados</p>
                    <p class="text-sm mt-1">Prueba ajustando el rango de fechas o los filtros aplicados</p>
                </div>

                <!-- Mensaje inicial -->
                <div v-else class="bg-white rounded-xl shadow-sm p-12 text-center text-gray-400">
                    <i class="fas fa-calendar-alt text-4xl mb-3 block text-emerald-300"></i>
                    <p class="text-lg font-medium">Selecciona un rango de fechas</p>
                    <p class="text-sm mt-1">Define la fecha de inicio y fin para generar el informe</p>
                </div>

                <!-- Footer -->
                <div v-if="datos && datos.length > 0" class="mt-4 text-center text-xs text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Reporte generado el {{ new Date().toLocaleString('es-BO') }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
    }
}
</style>