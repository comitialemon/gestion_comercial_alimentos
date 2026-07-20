<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ModalMovimientos from './components/ModalMovimientos.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    productos: Object,
    nombreSucursal: String,
    fechaInicial: String,
    fechaFinal: String,
    soloConMovimiento: Boolean,
    search: String,
    sucursalId: Number,
})

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// Estado del formulario
const fechaInicial = ref(props.fechaInicial || new Date().toISOString().slice(0, 10))
const fechaFinal = ref(props.fechaFinal || new Date().toISOString().slice(0, 10))
const soloConMovimiento = ref(props.soloConMovimiento || false)
const search = ref(props.search || '')

// Estado del modal
const modalVisible = ref(false)
const productoSeleccionado = ref(null)

// Usar la sucursal que viene de la prop
const sucursalId = ref(props.sucursalId)

// Aplicar filtros
const aplicarFiltros = () => {
    router.get('/gestion/inventario/reporte-inventario/sucursal-actual', {
        fecha_inicial: fechaInicial.value,
        fecha_final: fechaFinal.value,
        solo_con_movimiento: soloConMovimiento.value ? 1 : undefined,
        search: search.value || undefined
    }, { preserveState: true, replace: true })
}

// Limpiar filtros
const limpiarFiltros = () => {
    fechaInicial.value = new Date().toISOString().slice(0, 10)
    fechaFinal.value = new Date().toISOString().slice(0, 10)
    soloConMovimiento.value = false
    search.value = ''
    aplicarFiltros()
}

// Ver movimientos de un producto
const verMovimientos = (producto) => {
    productoSeleccionado.value = producto
    modalVisible.value = true
}

// Cerrar modal
const cerrarModal = () => {
    modalVisible.value = false
    productoSeleccionado.value = null
}

const formatNumber = (num) => {
    if (num === undefined || num === null) return '0.000'
    const valor = Number(num)
    if (isNaN(valor)) return '0.000'
    if (valor < 0) return `- ${Math.abs(valor).toFixed(3)}`  // ← 3 DECIMALES
    return valor.toFixed(3)  // ← 3 DECIMALES
}

// Clase para el color del saldo
const getSaldoClass = (saldo) => {
    const saldoNum = Number(saldo) || 0
    if (saldoNum < 0) return 'text-red-600 font-bold'
    return 'text-gray-800 font-semibold'
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
                <!-- Header -->
                <div class="flex flex-col items-center text-center mb-5">
                    <div class="w-12 h-12 lg:w-14 lg:h-14 bg-primary-100 rounded-xl flex items-center justify-center mb-2">
                        <i class="fas fa-chart-line text-primary-600 text-lg lg:text-xl"></i>
                    </div>
                    <h1 class="text-lg lg:text-xl font-bold text-gray-800">Reporte de Inventario</h1>
                    <p class="text-xs text-gray-500">Movimientos por rango de fechas</p>
                    <div class="mt-2 inline-flex items-center gap-2 bg-primary-50 px-3 py-1.5 rounded-full">
                        <i class="fas fa-store text-primary-600 text-xs"></i>
                        <span class="text-sm lg:text-[15px] font-medium text-primary-700">{{ nombreSucursal }}</span>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha Inicial</label>
                            <input type="date" v-model="fechaInicial" @change="aplicarFiltros" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm lg:text-[15px] focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha Final</label>
                            <input type="date" v-model="fechaFinal" @change="aplicarFiltros" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm lg:text-[15px] focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Buscar producto</label>
                            <input type="text" v-model="search" placeholder="Código o descripción..." 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm lg:text-[15px] focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" v-model="soloConMovimiento" @change="aplicarFiltros" 
                                class="w-4 h-4 rounded border-gray-300 focus:ring-primary-500">
                            <span>Mostrar solo productos con movimiento</span>
                        </label>
                        <div class="flex gap-3 w-full sm:w-auto">
                            <button @click="aplicarFiltros" 
                                class="flex-1 sm:flex-initial px-4 py-2 bg-primary-600 text-white rounded-lg text-sm lg:text-[15px] font-medium hover:bg-primary-700 transition flex items-center justify-center gap-2">
                                <i class="fas fa-search text-sm"></i>
                                <span>Buscar</span>
                            </button>
                            <button @click="limpiarFiltros" 
                                class="flex-1 sm:flex-initial px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm lg:text-[15px] font-medium hover:bg-gray-300 transition flex items-center justify-center gap-2">
                                <i class="fas fa-eraser text-sm"></i>
                                <span>Limpiar</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de inventario -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        
                        <!-- VISTA MÓVIL (tarjetas) -->
                        <div v-if="isMobile" class="p-3 space-y-3">
                            <div v-for="item in productos.data" :key="item.IdProducto" 
                                class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <p class="text-xs font-mono text-gray-500 mb-1">{{ item.Codigo || '-' }}</p>
                                        <p class="text-sm font-medium text-gray-800 leading-tight">{{ item.Descripcion || 'Sin descripción' }}</p>
                                    </div>
                                    <button @click="verMovimientos(item)" class="text-primary-600 hover:text-primary-800 p-2 -mt-1 -mr-1" title="Ver movimientos">
                                        <i class="fas fa-list-ul text-base"></i>
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-200">
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Saldo Anterior</p>
                                        <p class="text-sm font-semibold">{{ formatNumber(item.saldo_anterior) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Ingresos</p>
                                        <p class="text-sm font-semibold text-emerald-600">{{ formatNumber(item.ingresos) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Salidas</p>
                                        <p class="text-sm font-semibold text-red-600">{{ formatNumber(item.salidas) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Saldo Actual</p>
                                        <p class="text-sm font-bold" :class="getSaldoClass(item.saldo_actual)">{{ formatNumber(item.saldo_actual) }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!productos.data || productos.data.length === 0" class="text-center text-gray-400 py-10">
                                <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                No hay datos para los filtros seleccionados
                            </div>
                        </div>

                        <!-- VISTA TABLET (tabla compacta) -->
                        <div v-else-if="isTablet" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Código</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-primary-700 uppercase">Producto</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-primary-700 uppercase">Saldo Ant.</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-emerald-600 uppercase">Ing.</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-red-600 uppercase">Sal.</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-blue-600 uppercase">Saldo Act.</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-primary-700 uppercase w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in productos.data" :key="item.IdProducto" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 text-xs font-mono text-gray-600">{{ item.Codigo || '-' }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-800 max-w-[180px] truncate">{{ item.Descripcion || '-' }}</td>
                                        <td class="px-3 py-2 text-right text-xs">{{ formatNumber(item.saldo_anterior) }}</td>
                                        <td class="px-3 py-2 text-right text-xs text-emerald-600">{{ formatNumber(item.ingresos) }}</td>
                                        <td class="px-3 py-2 text-right text-xs text-red-600">{{ formatNumber(item.salidas) }}</td>
                                        <td class="px-3 py-2 text-right text-xs font-bold" :class="getSaldoClass(item.saldo_actual)">
                                            {{ formatNumber(item.saldo_actual) }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button @click="verMovimientos(item)" class="text-primary-600 hover:text-primary-800">
                                                <i class="fas fa-list-ul"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!productos.data || productos.data.length === 0">
                                        <td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay datos</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- VISTA ESCRITORIO (tabla completa) -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-primary-700 uppercase">Código</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-primary-700 uppercase">Producto</th>
                                        <th class="px-4 py-3 text-right text-sm font-medium text-primary-700 uppercase w-28">Saldo Anterior</th>
                                        <th class="px-4 py-3 text-right text-sm font-medium text-emerald-600 uppercase w-24">Ingresos</th>
                                        <th class="px-4 py-3 text-right text-sm font-medium text-red-600 uppercase w-24">Salidas</th>
                                        <th class="px-4 py-3 text-right text-sm font-medium text-blue-600 uppercase w-28">Saldo Actual</th>
                                        <th class="px-4 py-3 text-center text-sm font-medium text-primary-700 uppercase w-12">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in productos.data" :key="item.IdProducto" class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ item.Codigo || '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-800">{{ item.Descripcion || 'Sin descripción' }}</td>
                                        <td class="px-4 py-3 text-right text-sm">{{ formatNumber(item.saldo_anterior) }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-emerald-600 font-medium">{{ formatNumber(item.ingresos) }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-red-600 font-medium">{{ formatNumber(item.salidas) }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-bold" :class="getSaldoClass(item.saldo_actual)">
                                            {{ formatNumber(item.saldo_actual) }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button @click="verMovimientos(item)" class="text-primary-600 hover:text-primary-800 transition" title="Ver movimientos">
                                                <i class="fas fa-list-ul text-base"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!productos.data || productos.data.length === 0">
                                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                                            <i class="fas fa-box-open text-3xl mb-2 block"></i>
                                            No hay datos para los filtros seleccionados
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div v-if="productos.links && productos.links.length > 1" class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                            <div class="text-sm text-gray-500">
                                Mostrando {{ productos.from || 0 }} a {{ productos.to || 0 }} de {{ productos.total || 0 }} resultados
                            </div>
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link v-for="link in productos.links" :key="link.label" :href="link.url || '#'" 
                                    class="px-3 py-1.5 rounded-lg border text-sm transition"
                                    :class="{
                                        'bg-primary-600 text-white border-primary-600': link.active,
                                        'bg-white text-gray-700 hover:bg-gray-50 border-gray-300': !link.active && link.url,
                                        'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400': !link.url
                                    }"
                                    v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer informativo -->
                <div class="mt-4 text-center text-xs text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Reporte de la sucursal: <span class="font-medium text-primary-600">{{ nombreSucursal }}</span>
                </div>
            </div>
        </div>

        <!-- Modal de movimientos -->
        <ModalMovimientos
            v-model:visible="modalVisible"
            :producto="productoSeleccionado"
            :sucursal-id="sucursalId"
            :fecha-inicial="fechaInicial"
            :fecha-final="fechaFinal"
            @close="cerrarModal"
        />
    </div>
</template>

<style scoped>
/* Asegurar que los textos sean legibles en PC */
@media (min-width: 1024px) {
    input, select, button, .text-sm {
        font-size: 15px !important;
    }
}
</style>