<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch, onMounted, computed, inject } from 'vue'
import ModalDetalleFactura from './ModalDetalleFactura.vue'

defineOptions({ layout: AppLayout })

const toast = inject('toast')

const props = defineProps({
    ventas: Object,
    operadorNombre: String,
    sucursalNombre: String,
    filtroEstado: String,
    buscar: String
})

// =============================================
// ESTADO DE FILTROS
// =============================================
const estadoFiltro = ref(props.filtroEstado || '')
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
// CUANDO SE ACTIVA UNA FACTURA DESDE EL MODAL
// =============================================
const onFacturaActivada = () => {
    setTimeout(() => {
        aplicarFiltros()
    }, 500)
}

// =============================================
// UTILIDADES
// =============================================
const formatearMonto = (monto) => {
    return Number(monto || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
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

// =============================================
// COMPUTADOS
// =============================================
const ventasData = computed(() => {
    if (props.ventas && props.ventas.data && Array.isArray(props.ventas.data)) {
        return props.ventas.data
    }
    return []
})

const totalVentas = computed(() => {
    return props.ventas?.total || 0
})

const hasVentas = computed(() => {
    return ventasData.value.length > 0
})

onMounted(() => {
    console.log('📊 Datos de ventas:', props.ventas)
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-full lg:max-w-5xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-invoice text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Mis Facturas</h1>
                            <p class="text-[10px] text-gray-500">
                                {{ props.operadorNombre || 'Vendedor' }} - {{ props.sucursalNombre || 'Sucursal' }}
                            </p>
                            <p class="text-[10px] text-gray-400">
                                Total: {{ totalVentas }} facturas
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Link href="/gestion/reportes/control-interno/ventas/estadisticas" 
                              class="px-3 py-1.5 bg-primary-100 hover:bg-primary-200 text-primary-700 rounded-lg text-xs">
                            <i class="fas fa-chart-bar"></i> Estadísticas
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
                                <option value="">Activos (Borrador)</option>
                                <option value="inactivos">Inactivos (Cerrado)</option>
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
                    
                    <div class="text-[10px] text-gray-400 text-center mt-2 sm:text-right">
                        <i class="fas fa-info-circle"></i> 
                        <span class="text-green-600">● Activo</span> = Borrador (editable) | 
                        <span class="text-red-600">● Inactivo</span> = Cerrado (no editable)
                        <span v-if="totalVentas" class="ml-2">| Total: <strong>{{ totalVentas }}</strong> facturas</span>
                    </div>
                </div>

                <!-- CONTENIDO PRINCIPAL -->
                
                <!-- MENSAJE: SIN DATOS -->
                <div v-if="!hasVentas" class="bg-white rounded-xl shadow-sm p-6 sm:p-8 text-center text-gray-500">
                    <i class="fas fa-file-invoice text-3xl sm:text-4xl block mb-2 text-gray-300"></i>
                    <p class="text-sm sm:text-base">
                        <span v-if="buscador">No hay facturas que coincidan con "{{ buscador }}"</span>
                        <span v-else>No tienes facturas en esta sucursal</span>
                    </p>
                </div>

                <!-- TABLA DE FACTURAS -->
                <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Factura</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="venta in ventasData" :key="venta.IdVentas" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-xs font-mono text-gray-900 font-bold">
                                        {{ venta.NumeroFactura || 'N/A' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500">
                                        {{ formatearFecha(venta.FechaVenta) }}
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
                                            <!-- 🔥 EDITAR - solo si está activa -->
                                            <Link 
                                                v-if="puedeEditar(venta)"
                                                :href="`/gestion/reportes/control-interno/ventas/mis-facturas/${venta.IdVentas}/edit`"
                                                class="text-primary-600 hover:text-primary-800 text-xs"
                                                title="Editar opciones"
                                            >
                                                <i class="fas fa-edit text-sm"></i>
                                            </Link>
                                            
                                            <!-- 🔥 VER DETALLE - Usa el modal en lugar de página separada -->
                                            <button 
                                                @click="abrirModalDetalle(venta.IdVentas)"
                                                class="text-blue-600 hover:text-blue-800 text-xs"
                                                title="Ver detalle de la factura"
                                            >
                                                <i class="fas fa-eye text-sm"></i>
                                            </button>

                                            <!-- 🔥 REIMPRIMIR -->
                                            <a 
                                                :href="`/gestion/reportes/control-interno/ventas/${venta.IdVentas}/reimprimir`"
                                                target="_blank"
                                                class="text-red-600 hover:text-red-800 text-xs"
                                                title="Reimprimir factura"
                                            >
                                                <i class="fas fa-file-pdf text-sm"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!ventasData.length">
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-400 text-sm">
                                        No hay facturas para mostrar
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="props.ventas?.links" class="border-t px-3 py-2 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <p class="text-[10px] sm:text-xs text-gray-500">
                            Mostrando {{ ventasData.length }} de {{ totalVentas }}
                        </p>
                        <div class="flex gap-1 flex-wrap justify-center">
                            <Link 
                                v-for="link in props.ventas.links" 
                                :key="link.label" 
                                :href="link.url || '#'"
                                class="px-2 sm:px-2.5 py-1 rounded text-[10px] sm:text-xs transition" 
                                :class="{ 
                                    'bg-primary-600 text-white': link.active, 
                                    'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200': !link.active && link.url, 
                                    'opacity-50 cursor-not-allowed': !link.url 
                                }" 
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🔥 MODAL DE DETALLE DE FACTURA (reutilizado de GestionEstado) -->
        <ModalDetalleFactura
            v-model:visible="modalDetalleVisible"
            :venta-id="ventaIdSeleccionada"
            @activado="onFacturaActivada"
        />
    </div>
</template>

<style scoped>
/* Estilos adicionales */
</style>