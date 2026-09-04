<!-- resources/js/Pages/Gestion/Inventario/ReporteProductosBase/Index.vue -->

<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ModalDetalleProductoBase from './components/ModalDetalleProductoBase.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productos: Array,
    sucursales: Array,
    fechaInicial: String,
    fechaFinal: String,
    sucursalSeleccionada: Number,
    search: String
})

// ==================== ESTADO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

const sucursalId = ref(props.sucursalSeleccionada || '')
const mostrarListaSucursales = ref(false)
const fechaInicial = ref(props.fechaInicial || new Date().toISOString().slice(0, 10))
const fechaFinal = ref(props.fechaFinal || new Date().toISOString().slice(0, 10))
const search = ref(props.search || '')
const sucursalSearch = ref('')

// Modal
const modalVisible = ref(false)
const productoSeleccionado = ref(null)

// Cargar nombre de sucursal
const cargarSucursalNombre = () => {
    if (sucursalId.value && props.sucursales) {
        const encontrada = props.sucursales.find(s => s.id === Number(sucursalId.value))
        if (encontrada) sucursalSearch.value = encontrada.nombre
    }
}

const haySucursalSeleccionada = computed(() => {
    return sucursalId.value && sucursalId.value !== '' && Number(sucursalId.value) > 0
})

const sucursalesFiltradas = computed(() => {
    if (!sucursalSearch.value) return props.sucursales || []
    const termino = sucursalSearch.value.toLowerCase()
    return (props.sucursales || []).filter(s => 
        s.nombre.toLowerCase().includes(termino) || 
        s.numero?.toString().includes(termino)
    )
})

// ==================== MÉTODOS ====================
const aplicarFiltros = () => {
    if (!haySucursalSeleccionada.value) return
    router.get('/gestion/inventario/reporte-productos-base', {
        sucursal_id: sucursalId.value || undefined,
        fecha_inicial: fechaInicial.value,
        fecha_final: fechaFinal.value,
        search: search.value || undefined
    }, { preserveState: true, replace: true })
}

const limpiarFiltros = () => {
    sucursalId.value = ''
    sucursalSearch.value = ''
    fechaInicial.value = new Date().toISOString().slice(0, 10)
    fechaFinal.value = new Date().toISOString().slice(0, 10)
    search.value = ''
    router.get('/gestion/inventario/reporte-productos-base', {}, { preserveState: true, replace: true })
}

const seleccionarSucursal = (suc) => {
    sucursalId.value = suc.id
    sucursalSearch.value = suc.nombre
    mostrarListaSucursales.value = false
    aplicarFiltros()
}

const verDetalle = (producto) => {
    productoSeleccionado.value = producto
    modalVisible.value = true
}

const cerrarModal = () => {
    modalVisible.value = false
    productoSeleccionado.value = null
}

const formatNumber = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toFixed(0)
}

// Debounce
let timeout
watch(search, () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        if (haySucursalSeleccionada.value) aplicarFiltros()
    }, 500)
})

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
    cargarSucursalNombre()
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
                
                <!-- Header -->
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-cubes text-primary-600 text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-lg lg:text-xl font-bold text-gray-800">Reporte de Productos Base</h1>
                        <p class="text-xs text-gray-500">Muestra en qué productos se vendió cada producto base</p>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                        <!-- Sucursal -->
                        <div class="sucursal-selector relative">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Sucursal</label>
                            <div class="relative">
                                <input 
                                    type="text"
                                    v-model="sucursalSearch"
                                    @focus="mostrarListaSucursales = true"
                                    @input="mostrarListaSucursales = true"
                                    placeholder="Seleccione Sucursal..."
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500 pr-8"
                                    autocomplete="off"
                                />
                                <button 
                                    v-if="sucursalId"
                                    @click="limpiarSucursal"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <i class="fas fa-times text-sm"></i>
                                </button>
                            </div>
                            <span v-if="sucursalId" class="text-xs text-primary-600 font-medium mt-1 inline-block">
                                <i class="fas fa-check-circle"></i> {{ sucursalSearch }}
                            </span>
                            <span v-else class="text-xs text-gray-400 mt-1 inline-block">
                                <i class="fas fa-store"></i> Ninguna
                            </span>
                            
                            <div v-if="mostrarListaSucursales && sucursalesFiltradas.length > 0" 
                                class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-52 overflow-y-auto">
                                <div 
                                    v-for="suc in sucursalesFiltradas" 
                                    :key="suc.id"
                                    @mousedown="seleccionarSucursal(suc)"
                                    class="px-3 py-2.5 hover:bg-primary-50 cursor-pointer transition-colors border-b border-gray-100 last:border-b-0"
                                    :class="{ 'bg-primary-50 text-primary-700': sucursalId === suc.id }"
                                >
                                    <div class="font-medium text-sm">{{ suc.nombre }}</div>
                                    <div class="text-xs text-gray-400">N° {{ suc.numero }}</div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha Inicial</label>
                            <input type="date" v-model="fechaInicial" @change="aplicarFiltros" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha Final</label>
                            <input type="date" v-model="fechaFinal" @change="aplicarFiltros" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Buscar producto</label>
                            <input type="text" v-model="search" placeholder="Código o descripción..." 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <span class="text-xs text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Muestra productos que se vendieron como sueltos o dentro de combos/packs
                        </span>
                        <div class="flex gap-3 w-full sm:w-auto">
                            <button @click="aplicarFiltros" 
                                :disabled="!haySucursalSeleccionada"
                                class="flex-1 sm:flex-initial px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-search text-sm"></i>
                                <span>Buscar</span>
                            </button>
                            <button @click="limpiarFiltros" 
                                class="flex-1 sm:flex-initial px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition flex items-center justify-center gap-2">
                                <i class="fas fa-eraser text-sm"></i>
                                <span>Limpiar</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mensaje sin sucursal -->
                <div v-if="!haySucursalSeleccionada" class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <i class="fas fa-building text-5xl text-gray-300 mb-3 block"></i>
                    <p class="text-base font-medium text-gray-600">Selecciona una sucursal para ver el reporte</p>
                </div>

                <!-- Tabla -->
                <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL -->
                        <div v-if="isMobile" class="p-3 space-y-3">
                            <div v-for="item in productos" :key="item.IdProducto" 
                                class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="text-xs font-mono text-gray-500">{{ item.Codigo || '-' }}</p>
                                        <p class="text-sm font-medium text-gray-800">{{ item.Descripcion }}</p>
                                    </div>
                                    <span class="px-2 py-1 bg-primary-100 text-primary-700 rounded-lg text-xs font-bold">
                                        {{ formatNumber(item.total_vendido) }} unid.
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 pt-2 border-t border-gray-200">
                                    <div class="bg-emerald-50 rounded p-2 text-center">
                                        <p class="text-[10px] text-gray-500">Suelto</p>
                                        <p class="text-sm font-bold text-emerald-600">{{ formatNumber(item.venta_suelto) }}</p>
                                    </div>
                                    <div class="bg-blue-50 rounded p-2 text-center">
                                        <p class="text-[10px] text-gray-500">En Combo/Pack</p>
                                        <p class="text-sm font-bold text-blue-600">{{ formatNumber(item.venta_compuesta) }}</p>
                                    </div>
                                </div>
                                <div class="mt-2 flex justify-end">
                                    <button @click="verDetalle(item)" 
                                        class="text-xs text-primary-600 hover:text-primary-800 flex items-center gap-1">
                                        <i class="fas fa-list"></i> Ver desglose
                                    </button>
                                </div>
                            </div>
                            <div v-if="!productos || productos.length === 0" class="text-center text-gray-400 py-10">
                                <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                No hay datos para los filtros seleccionados
                            </div>
                        </div>

                        <!-- VISTA TABLET -->
                        <div v-else-if="isTablet" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Código</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Producto Base</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-emerald-600 uppercase">Suelto</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-blue-600 uppercase">En Combo/Pack</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Total</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase w-10">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in productos" :key="item.IdProducto" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 text-xs font-mono text-gray-600">{{ item.Codigo || '-' }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-800">{{ item.Descripcion }}</td>
                                        <td class="px-3 py-2 text-right text-xs text-emerald-600 font-semibold">{{ formatNumber(item.venta_suelto) }}</td>
                                        <td class="px-3 py-2 text-right text-xs text-blue-600 font-semibold">{{ formatNumber(item.venta_compuesta) }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-bold text-primary-700">{{ formatNumber(item.total_vendido) }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <button @click="verDetalle(item)" class="text-primary-600 hover:text-primary-800">
                                                <i class="fas fa-list-ul"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!productos || productos.length === 0">
                                        <td colspan="6" class="px-4 py-10 text-center text-gray-400">No hay datos</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- VISTA ESCRITORIO -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-primary-700 uppercase">Código</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-primary-700 uppercase">Producto Base</th>
                                        <th class="px-4 py-3 text-right text-sm font-medium text-emerald-600 uppercase w-28">Venta Suelta</th>
                                        <th class="px-4 py-3 text-right text-sm font-medium text-blue-600 uppercase w-28">En Combo/Pack</th>
                                        <th class="px-4 py-3 text-right text-sm font-medium text-primary-700 uppercase w-28">Total Vendido</th>
                                        <th class="px-4 py-3 text-center text-sm font-medium text-primary-700 uppercase w-12">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in productos" :key="item.IdProducto" class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ item.Codigo || '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-800 font-medium">{{ item.Descripcion }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-emerald-600 font-semibold">{{ formatNumber(item.venta_suelto) }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-blue-600 font-semibold">{{ formatNumber(item.venta_compuesta) }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-bold text-primary-700">{{ formatNumber(item.total_vendido) }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <button @click="verDetalle(item)" 
                                                class="p-1.5 rounded-md hover:bg-primary-100 transition text-primary-600"
                                                title="Ver desglose">
                                                <i class="fas fa-list-ul"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!productos || productos.length === 0">
                                        <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                                            <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                            No hay datos para los filtros seleccionados
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Detalle -->
        <ModalDetalleProductoBase
            v-model:visible="modalVisible"
            :producto="productoSeleccionado"
            @close="cerrarModal"
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