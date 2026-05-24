<script setup>
import { ref, watch, onUnmounted } from 'vue'
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
    search: String
})

// Estado del formulario
const fechaInicial = ref(props.fechaInicial || new Date().toISOString().slice(0, 10))
const fechaFinal = ref(props.fechaFinal || new Date().toISOString().slice(0, 10))
const soloConMovimiento = ref(props.soloConMovimiento || false)
const search = ref(props.search || '')

// Estado del modal
const modalVisible = ref(false)
const productoSeleccionado = ref(null)

// Obtener sucursalId del contexto (para el modal)
const sucursalId = ref(null)

// Cargar sucursalId de la sesión
const cargarSucursalId = () => {
    fetch('/check-session')
        .then(res => res.json())
        .then(data => {
            // Obtener de la sesión, pero como no tenemos endpoint, usamos props o contexto
            sucursalId.value = 44 // 👈 Reemplaza con tu lógica o pasa como prop
        })
        .catch(err => console.error('Error:', err))
}

// Aplicar filtros
const aplicarFiltros = () => {
    router.get('/gestion/inventario/reporte-inventario/sucursal-actual', {
        fecha_inicial: fechaInicial.value,
        fecha_final: fechaFinal.value,
        solo_con_movimiento: soloConMovimiento.value ? 1 : undefined,
        search: search.value || undefined
    }, { preserveState: true, replace: true })
}

// Limpiar filtros (solo fechas y búsqueda, no sucursal)
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

// Formatear número
const formatNumber = (num) => {
    if (num === undefined || num === null) return '0.00'
    const valor = Number(num)
    if (valor < 0) return `- ${Math.abs(valor).toFixed(2)}`
    return valor.toFixed(2)
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

onUnmounted(() => {
    if (timeout) clearTimeout(timeout)
})

cargarSucursalId()
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <div class="py-4 px-3 sm:py-6 sm:px-4 md:px-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- Header -->
                <div class="text-center mb-4 sm:mb-6">
                    <div class="inline-flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 bg-guindo-100 rounded-2xl mb-2 sm:mb-3">
                        <i class="fas fa-chart-line text-lg sm:text-xl text-guindo-600"></i>
                    </div>
                    <h1 class="text-lg sm:text-xl font-bold text-gray-900">Reporte de Inventario</h1>
                    <p class="text-xs text-gray-500">Movimientos por rango de fechas</p>
                    <div class="mt-2 inline-flex items-center gap-2 bg-guindo-50 px-3 py-1 rounded-full">
                        <i class="fas fa-store text-guindo-600 text-xs"></i>
                        <span class="text-sm font-medium text-guindo-700">{{ nombreSucursal }}</span>
                    </div>
                </div>

                <!-- Filtros (sin selector de sucursal) -->
                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 mb-4 sm:mb-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fecha Inicial</label>
                            <input type="date" v-model="fechaInicial" @change="aplicarFiltros" 
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-guindo-500 focus:border-guindo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fecha Final</label>
                            <input type="date" v-model="fechaFinal" @change="aplicarFiltros" 
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-guindo-500 focus:border-guindo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Buscar producto</label>
                            <input type="text" v-model="search" placeholder="Código o descripción..." 
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-guindo-500 focus:border-guindo-500">
                        </div>
                    </div>
                    <div class="mt-3 flex flex-col sm:flex-row justify-between items-center gap-3">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" v-model="soloConMovimiento" @change="aplicarFiltros" class="rounded focus:ring-guindo-500">
                            Mostrar solo productos con movimiento
                        </label>
                        <div class="flex gap-2">
                            <button @click="aplicarFiltros" class="px-3 py-1.5 sm:px-4 sm:py-2 bg-guindo-600 text-white rounded-lg text-sm hover:bg-guindo-700 transition">
                                <i class="fas fa-search mr-1"></i> Buscar
                            </button>
                            <button @click="limpiarFiltros" class="px-3 py-1.5 sm:px-4 sm:py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300 transition">
                                <i class="fas fa-eraser mr-1"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de inventario -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="relative overflow-x-auto" style="max-height: 70vh; overflow-y: auto;">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-guindo-50 sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th class="px-3 py-2 sm:px-4 sm:py-3 text-left text-xs font-medium text-guindo-700 uppercase">Código</th>
                                    <th class="px-3 py-2 sm:px-4 sm:py-3 text-left text-xs font-medium text-guindo-700 uppercase">Producto</th>
                                    <th class="px-3 py-2 sm:px-4 sm:py-3 text-right text-xs font-medium text-guindo-700 uppercase w-24 sm:w-28">Saldo Anterior</th>
                                    <th class="px-3 py-2 sm:px-4 sm:py-3 text-right text-xs font-medium text-emerald-600 uppercase w-20 sm:w-24">Ingresos</th>
                                    <th class="px-3 py-2 sm:px-4 sm:py-3 text-right text-xs font-medium text-red-600 uppercase w-20 sm:w-24">Salidas</th>
                                    <th class="px-3 py-2 sm:px-4 sm:py-3 text-right text-xs font-medium text-blue-600 uppercase w-24 sm:w-28">Saldo Actual</th>
                                    <th class="px-3 py-2 sm:px-4 sm:py-3 text-center text-xs font-medium text-guindo-700 uppercase w-12 sm:w-16">Detalle</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in productos.data" :key="item.IdProducto" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 text-xs sm:text-sm font-mono text-gray-600">{{ item.Codigo || '-' }}</td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 text-xs sm:text-sm text-gray-800">{{ item.Descripcion || 'Sin descripción' }}</td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 text-right text-xs sm:text-sm">{{ formatNumber(item.saldo_anterior) }}</td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 text-right text-xs sm:text-sm text-emerald-600 font-medium">{{ formatNumber(item.ingresos) }}</td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 text-right text-xs sm:text-sm text-red-600 font-medium">{{ formatNumber(item.salidas) }}</td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 text-right text-xs sm:text-sm font-bold" :class="getSaldoClass(item.saldo_actual)">
                                        {{ formatNumber(item.saldo_actual) }}
                                    </td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 text-center">
                                        <button @click="verMovimientos(item)" class="text-guindo-600 hover:text-guindo-800 transition" title="Ver movimientos">
                                            <i class="fas fa-list-ul text-sm sm:text-base"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!productos.data || productos.data.length === 0">
                                    <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                                        No hay datos para los filtros seleccionados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación responsiva -->
                    <div v-if="productos.links && productos.links.length > 1" class="px-3 py-2 sm:px-4 sm:py-3 border-t">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                            <div class="text-xs sm:text-sm text-gray-500">
                                Mostrando {{ productos.from || 0 }} a {{ productos.to || 0 }} de {{ productos.total || 0 }}
                            </div>
                            <div class="flex gap-1">
                                <Link v-for="link in productos.links" :key="link.label" :href="link.url || '#'" 
                                    class="px-2 py-1 sm:px-3 sm:py-1 rounded border text-xs sm:text-sm"
                                    :class="{
                                        'bg-guindo-600 text-white border-guindo-600': link.active,
                                        'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url,
                                        'opacity-50 cursor-not-allowed': !link.url
                                    }"
                                    v-html="link.label" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer informativo -->
                <div class="mt-4 text-center text-xs text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Reporte de la sucursal: <span class="font-medium text-guindo-600">{{ nombreSucursal }}</span>
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