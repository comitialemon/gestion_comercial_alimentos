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

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const sucursalId = ref(props.sucursalSeleccionada || '')
const mostrarListaSucursales = ref(false)
const fechaInicial = ref(props.fechaInicial || new Date().toISOString().slice(0, 10))
const fechaFinal = ref(props.fechaFinal || new Date().toISOString().slice(0, 10))
const search = ref(props.search || '')
const sucursalSearch = ref('')

// Modal
const modalVisible = ref(false)
const productoSeleccionado = ref(null)

// ==================== COMPUTED ====================
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

// ==================== FUNCIONES ====================
const cargarSucursalNombre = () => {
    if (sucursalId.value && props.sucursales) {
        const encontrada = props.sucursales.find(s => s.id === Number(sucursalId.value))
        if (encontrada) sucursalSearch.value = encontrada.nombre
    }
}

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

const limpiarSucursal = () => {
    sucursalId.value = ''
    sucursalSearch.value = ''
    mostrarListaSucursales.value = false
    router.get('/gestion/inventario/reporte-productos-base', {}, { preserveState: true, replace: true })
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

// ==================== LIFECYCLE ====================
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
                <!-- ==================== HEADER COMPACTO ==================== -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 bg-primary-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-cubes text-primary-600 text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-base lg:text-lg font-bold text-gray-800">Reporte de Productos Base</h1>
                        <p class="text-xs text-gray-500">Muestra en qué productos se vendió cada producto base</p>
                    </div>
                </div>

                <!-- ==================== FILTROS COMPACTOS ==================== -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Sucursal -->
                        <div class="sucursal-selector relative flex-1 min-w-[160px] max-w-[220px]">
                            <label class="text-[10px] text-gray-500 font-medium block mb-0.5">Sucursal *</label>
                            <div class="relative">
                                <input type="text" v-model="sucursalSearch"
                                    @focus="mostrarListaSucursales = true" @input="mostrarListaSucursales = true"
                                    placeholder="Seleccione..."
                                    class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm pr-6 focus:ring-primary-500 focus:border-primary-500 outline-none"
                                    autocomplete="off" />
                                <button v-if="sucursalId" @click="limpiarSucursal"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>
                            <span v-if="sucursalId" class="text-[9px] text-primary-600 font-medium">
                                <i class="fas fa-check-circle"></i> {{ sucursalSearch }}
                            </span>
                            <span v-else class="text-[9px] text-gray-400">
                                <i class="fas fa-store"></i> Ninguna
                            </span>
                            <div v-if="mostrarListaSucursales && sucursalesFiltradas.length > 0" 
                                class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-40 overflow-y-auto">
                                <div v-for="suc in sucursalesFiltradas" :key="suc.id"
                                    @mousedown="seleccionarSucursal(suc)"
                                    class="px-2.5 py-1.5 hover:bg-primary-50 cursor-pointer border-b border-gray-100 last:border-b-0 text-sm"
                                    :class="{ 'bg-primary-50 text-primary-700': sucursalId === suc.id }">
                                    <div class="font-medium text-xs">{{ suc.nombre }}</div>
                                    <div class="text-[9px] text-gray-400">N° {{ suc.numero }}</div>
                                </div>
                            </div>
                            <div v-if="mostrarListaSucursales && sucursalesFiltradas.length === 0 && sucursalSearch" 
                                class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg p-2 text-center text-gray-400 text-[10px]">
                                No se encontraron sucursales
                            </div>
                        </div>

                        <!-- Fecha Inicial -->
                        <div class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Desde:</label>
                            <input type="date" v-model="fechaInicial" @change="aplicarFiltros" 
                                class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                        </div>

                        <!-- Fecha Final -->
                        <div class="flex items-center gap-1">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Hasta:</label>
                            <input type="date" v-model="fechaFinal" @change="aplicarFiltros" 
                                class="w-32 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                        </div>

                        <!-- Buscar -->
                        <div class="flex items-center gap-1 flex-1 min-w-[120px] max-w-[200px]">
                            <label class="text-[10px] text-gray-500 font-medium whitespace-nowrap">Producto:</label>
                            <input type="text" v-model="search" placeholder="Código o descripción..." 
                                class="w-full border border-gray-300 rounded-md px-2.5 py-1 text-sm focus:ring-primary-500 focus:border-primary-500 outline-none">
                        </div>

                        <!-- Botones -->
                        <div class="flex gap-1.5 ml-auto">
                            <button @click="aplicarFiltros" :disabled="!haySucursalSeleccionada"
                                class="px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-search text-[10px]"></i> Buscar
                            </button>
                            <button @click="limpiarFiltros" 
                                class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-300 transition flex items-center gap-1">
                                <i class="fas fa-eraser text-[10px]"></i> Limpiar
                            </button>
                        </div>
                    </div>
                    <div class="mt-1 text-[8px] text-gray-400">
                        <i class="fas fa-info-circle"></i> Muestra productos que se vendieron como sueltos o dentro de combos/packs
                    </div>
                </div>

                <!-- ==================== MENSAJE SIN SUCURSAL ==================== -->
                <div v-if="!haySucursalSeleccionada" class="bg-white rounded-xl shadow-sm p-10 text-center">
                    <i class="fas fa-building text-4xl text-gray-300 mb-2 block"></i>
                    <p class="text-sm font-medium text-gray-600">Selecciona una sucursal para ver el reporte</p>
                </div>

                <!-- ==================== TABLA ==================== -->
                <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL -->
                        <div v-if="isMobile" class="p-2 space-y-2">
                            <div v-for="item in productos" :key="item.IdProducto" 
                                class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                                <div class="flex justify-between items-start mb-1.5">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[10px] font-mono text-gray-500">{{ item.Codigo || '-' }}</p>
                                        <p class="text-xs font-medium text-gray-800 truncate">{{ item.Descripcion }}</p>
                                    </div>
                                    <span class="px-1.5 py-0.5 bg-primary-100 text-primary-700 rounded text-[10px] font-bold flex-shrink-0 ml-2">
                                        {{ formatNumber(item.total_vendido) }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-1.5 pt-1.5 border-t border-gray-200">
                                    <div class="bg-emerald-50 rounded p-1.5 text-center">
                                        <p class="text-[8px] text-gray-500">Suelto</p>
                                        <p class="text-xs font-bold text-emerald-600">{{ formatNumber(item.venta_suelto) }}</p>
                                    </div>
                                    <div class="bg-blue-50 rounded p-1.5 text-center">
                                        <p class="text-[8px] text-gray-500">Combo/Pack</p>
                                        <p class="text-xs font-bold text-blue-600">{{ formatNumber(item.venta_compuesta) }}</p>
                                    </div>
                                </div>
                                <div class="mt-1.5 flex justify-end">
                                    <button @click="verDetalle(item)" 
                                        class="text-[10px] text-primary-600 hover:text-primary-800 flex items-center gap-1">
                                        <i class="fas fa-list text-[9px]"></i> Desglose
                                    </button>
                                </div>
                            </div>
                            <div v-if="!productos || productos.length === 0" class="text-center text-gray-400 py-8">
                                <i class="fas fa-box-open text-2xl mb-1 block"></i>
                                <span class="text-xs">No hay datos para los filtros seleccionados</span>
                            </div>
                        </div>

                        <!-- VISTA TABLET -->
                        <div v-else-if="isTablet" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Código</th>
                                        <th class="px-2 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Producto Base</th>
                                        <th class="px-2 py-1.5 text-right text-[9px] font-medium text-emerald-600 uppercase">Suelto</th>
                                        <th class="px-2 py-1.5 text-right text-[9px] font-medium text-blue-600 uppercase">Combo</th>
                                        <th class="px-2 py-1.5 text-right text-[9px] font-medium text-primary-700 uppercase">Total</th>
                                        <th class="px-2 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase w-8">Det.</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in productos" :key="item.IdProducto" class="hover:bg-gray-50">
                                        <td class="px-2 py-1.5 text-[10px] font-mono text-gray-600">{{ item.Codigo || '-' }}</td>
                                        <td class="px-2 py-1.5 text-[10px] text-gray-800 truncate max-w-[120px]">{{ item.Descripcion }}</td>
                                        <td class="px-2 py-1.5 text-right text-[10px] text-emerald-600 font-semibold">{{ formatNumber(item.venta_suelto) }}</td>
                                        <td class="px-2 py-1.5 text-right text-[10px] text-blue-600 font-semibold">{{ formatNumber(item.venta_compuesta) }}</td>
                                        <td class="px-2 py-1.5 text-right text-[10px] font-bold text-primary-700">{{ formatNumber(item.total_vendido) }}</td>
                                        <td class="px-2 py-1.5 text-center">
                                            <button @click="verDetalle(item)" class="text-primary-600 hover:text-primary-800 text-[10px]">
                                                <i class="fas fa-list-ul"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!productos || productos.length === 0">
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-xs">No hay datos</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- VISTA ESCRITORIO -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-[10px] font-medium text-primary-700 uppercase">Código</th>
                                        <th class="px-3 py-2 text-left text-[10px] font-medium text-primary-700 uppercase">Producto Base</th>
                                        <th class="px-3 py-2 text-right text-[10px] font-medium text-emerald-600 uppercase w-20">Suelto</th>
                                        <th class="px-3 py-2 text-right text-[10px] font-medium text-blue-600 uppercase w-20">Combo</th>
                                        <th class="px-3 py-2 text-right text-[10px] font-medium text-primary-700 uppercase w-20">Total</th>
                                        <th class="px-3 py-2 text-center text-[10px] font-medium text-primary-700 uppercase w-10">Det.</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in productos" :key="item.IdProducto" class="hover:bg-gray-50 transition">
                                        <td class="px-3 py-2 text-xs font-mono text-gray-600">{{ item.Codigo || '-' }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-800 font-medium truncate max-w-[200px]" :title="item.Descripcion">{{ item.Descripcion }}</td>
                                        <td class="px-3 py-2 text-right text-xs text-emerald-600 font-semibold">{{ formatNumber(item.venta_suelto) }}</td>
                                        <td class="px-3 py-2 text-right text-xs text-blue-600 font-semibold">{{ formatNumber(item.venta_compuesta) }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-bold text-primary-700">{{ formatNumber(item.total_vendido) }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <button @click="verDetalle(item)" 
                                                class="p-1 rounded hover:bg-primary-100 transition text-primary-600 text-xs" title="Ver desglose">
                                                <i class="fas fa-list-ul"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!productos || productos.length === 0">
                                        <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">
                                            <i class="fas fa-box-open text-2xl mb-1 block"></i>
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

        <!-- ==================== MODAL ==================== -->
        <ModalDetalleProductoBase
            v-model:visible="modalVisible"
            :producto="productoSeleccionado"
            @close="cerrarModal"
        />
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
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