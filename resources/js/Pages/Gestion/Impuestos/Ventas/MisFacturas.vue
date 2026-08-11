<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import ModalDetalleFactura from './ModalDetalleFactura.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    ventas: Object,
    operadorNombre: String,
    filtroEstado: String,
    buscar: String
})

// =============================================
// ESTADO DE FILTROS
// =============================================
const estadoFiltro = ref(props.filtroEstado || 'activos')
const buscador = ref(props.buscar || '')

// =============================================
// ESTADO PARA MODAL DE DETALLE
// =============================================
const modalDetalleVisible = ref(false)
const ventaIdSeleccionada = ref(null)

// =============================================
// ACCIONES
// =============================================
const aplicarFiltros = () => {
    const params = {}
    
    if (estadoFiltro.value && estadoFiltro.value !== '') {
        params.estado = estadoFiltro.value
    }
    if (buscador.value && buscador.value !== '') {
        params.buscar = buscador.value
    }
    
    router.get('/gestion/reportes/control-interno/ventas/mis-facturas', params, {
        preserveState: true,
        replace: true
    })
}

let timeoutBuscador
const buscarVentas = () => {
    clearTimeout(timeoutBuscador)
    timeoutBuscador = setTimeout(() => {
        aplicarFiltros()
    }, 500)
}

const limpiarBusqueda = () => {
    buscador.value = ''
    aplicarFiltros()
}

// =============================================
// ABRIR MODAL DE DETALLE
// =============================================
const abrirModalDetalle = (ventaId) => {
    ventaIdSeleccionada.value = ventaId
    modalDetalleVisible.value = true
}

// =============================================
// UTILIDADES
// =============================================
const formatearMonto = (monto) => {
    return Number(monto).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    const d = new Date(fecha)
    return d.toLocaleDateString('es-BO', { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const getEstadoColor = (activo) => {
    return activo === 1 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Inactivo' : 'Activo'
}

const puedeEditar = (venta) => {
    return venta.ActivoInactivo === 0 && venta.LiquidadoVendedor === 0
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-invoice text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Mis Facturas</h1>
                            <p class="text-[10px] text-gray-500">
                                <i class="fas fa-user mr-1"></i> 
                                {{ props.operadorNombre || 'Operador' }}
                            </p>
                            <p class="text-[9px] text-gray-400">
                                <i class="fas fa-store mr-1"></i> 
                                Sucursal: {{ session?.cliente_sucursal_nombre || 'Actual' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Link href="/venta-factura/nueva" class="flex-1 sm:flex-initial bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-plus text-[10px]"></i>
                            <span>Nueva Venta</span>
                        </Link>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        
                        <!-- Estado -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-700">Estado:</label>
                            <select v-model="estadoFiltro" @change="aplicarFiltros" class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-32 sm:w-36 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none">
                                <option value="activos">Activos (Borrador)</option>
                                <option value="inactivos">Inactivos (Cerrado)</option>
                                <option value="">Todos</option>
                            </select>
                        </div>
                        
                        <!-- Buscador por N° Factura -->
                        <div class="flex items-center gap-1">
                            <input 
                                type="text" 
                                v-model="buscador" 
                                @input="buscarVentas"
                                placeholder="N° Factura..."
                                class="border border-gray-300 rounded-lg px-2 py-1 text-xs w-28 sm:w-32 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none"
                            >
                            <button 
                                v-if="buscador" 
                                @click="limpiarBusqueda" 
                                class="text-gray-400 hover:text-gray-600 text-xs"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="buscador" class="mt-2 text-[10px] text-gray-500">
                        <span class="font-semibold">{{ buscador }}</span>
                        <span class="ml-2">({{ ventas?.total || 0 }} resultados)</span>
                    </div>
                    
                    <div class="text-[10px] text-gray-400 text-center mt-2 sm:text-right">
                        <i class="fas fa-info-circle"></i> 
                        <span class="text-green-600">● Activo</span> = Borrador (editable) | 
                        <span class="text-red-600">● Inactivo</span> = Cerrado (no editable)
                        <span v-if="ventas?.total" class="ml-2">| Total: <strong>{{ ventas.total }}</strong> facturas</span>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Factura</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="venta in ventas.data" :key="venta.IdVentas" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">
                                        {{ venta.NumeroFactura }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ formatearFecha(venta.FechaVenta) }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-700 max-w-[120px] truncate" :title="venta.cliente_nombre">
                                        {{ venta.cliente_nombre || 'Consumidor Final' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-right font-semibold text-primary-600">
                                        {{ formatearMonto(venta.ImporteVenta) }} Bs
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 text-[10px] rounded-full whitespace-nowrap" :class="getEstadoColor(venta.ActivoInactivo)">
                                            {{ getEstadoTexto(venta.ActivoInactivo) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <!-- 🔥 Ver - abre modal -->
                                            <button 
                                                @click="abrirModalDetalle(venta.IdVentas)"
                                                class="text-blue-600 hover:text-blue-800 text-xs"
                                                title="Ver detalle de la factura"
                                            >
                                                <i class="fas fa-eye text-sm"></i>
                                            </button>
                                            
                                            <!-- 🔥 Editar - solo si está activa y no liquidada -->
                                            <Link 
                                                v-if="puedeEditar(venta)"
                                                :href="`/gestion/reportes/control-interno/ventas/mis-facturas/${venta.IdVentas}/edit`"
                                                class="text-green-600 hover:text-green-800 text-xs"
                                                title="Editar opciones de la factura"
                                            >
                                                <i class="fas fa-edit text-sm"></i>
                                            </Link>
                                            <span v-else-if="venta.LiquidadoVendedor > 0" class="text-[10px] text-gray-400" title="Factura liquidada">
                                                <i class="fas fa-lock text-xs mr-1"></i>
                                            </span>
                                            <span v-else class="text-[10px] text-gray-400" title="Factura cerrada">
                                                <i class="fas fa-lock text-xs mr-1"></i>
                                            </span>
                                            
                                            <!-- Reimprimir -->
                                            <a 
                                                :href="`/gestion/reportes/control-interno/ventas/mis-facturas/${venta.IdVentas}/reimprimir`"
                                                target="_blank"
                                                class="text-red-600 hover:text-red-800 text-xs"
                                                title="Reimprimir factura"
                                            >
                                                <i class="fas fa-file-pdf text-sm"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!ventas.data?.length">
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-400 text-sm">
                                        <i class="fas fa-file-invoice text-3xl block mb-2 text-gray-300"></i>
                                        No tienes facturas activas para editar
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="ventas.data?.length" class="px-3 py-2 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <p class="text-[10px] text-gray-500">
                            Mostrando {{ ventas.from || 0 }} - {{ ventas.to || 0 }} de {{ ventas.total || 0 }}
                        </p>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link 
                                v-for="link in ventas.links" 
                                :key="link.label" 
                                :href="link.url || '#'"
                                class="px-2 sm:px-2.5 py-1 rounded text-[10px] sm:text-xs transition" 
                                :class="{ 
                                    'bg-primary-600 text-white': link.active, 
                                    'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200': !link.active && link.url, 
                                    'opacity-50 cursor-not-allowed': !link.url 
                                }" 
                                v-html="link.label"
                                preserve-state
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🔥 MODAL DE DETALLE DE FACTURA (mismo que GestionEstado) -->
        <ModalDetalleFactura
            v-model:visible="modalDetalleVisible"
            :venta-id="ventaIdSeleccionada"
        />
    </div>
</template>

<style scoped>
/* No se necesita autocomplete */
</style>