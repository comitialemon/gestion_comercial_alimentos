<!-- resources/js/Pages/Gestion/Reportes/components/ModalReporteVentasSupervisorPorOperador.vue -->

<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    operador: {
        type: Object,
        default: null
    },
    fechaInicio: {
        type: String,
        default: ''
    },
    fechaFin: {
        type: String,
        default: ''
    },
    anio: {
        type: String,
        default: ''
    }
})

const emit = defineEmits(['update:visible', 'close'])

// ==================== ESTADO ====================
const cargando = ref(false)
const error = ref('')
const data = ref(null)
const isMobile = ref(false)
const isTablet = ref(false)

// Estado para expandir facturas
const facturasExpandidas = ref({})

// ==================== MÉTODOS ====================
const cerrar = () => {
    emit('update:visible', false)
    emit('close')
    data.value = null
    error.value = ''
    facturasExpandidas.value = {}
}

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

const toggleFactura = (index) => {
    facturasExpandidas.value = {
        ...facturasExpandidas.value,
        [index]: !facturasExpandidas.value[index]
    }
}

const cargar = async () => {
    if (!props.operador) return
    
    cargando.value = true
    error.value = ''
    facturasExpandidas.value = {}
    
    try {
        const params = new URLSearchParams({
            operador_id: props.operador.id
        })
        
        // Usar los filtros del reporte
        if (props.fechaInicio) params.append('fecha_inicio', props.fechaInicio)
        if (props.fechaFin) params.append('fecha_fin', props.fechaFin)
        
        const response = await axios.get('/gestion/reportes/ventas-por-operador/detalle?' + params.toString())
        
        if (response.data.success) {
            data.value = response.data
        } else {
            error.value = response.data.message || 'Error al cargar'
        }
    } catch (err) {
        console.error('Error:', err)
        error.value = err.response?.data?.message || 'Error de conexión'
    } finally {
        cargando.value = false
    }
}

// Cargar cuando se abre el modal
watch(() => props.visible, (newVal) => {
    if (newVal && props.operador) {
        cargar()
    }
})

// ==================== COMPUTADOS ====================
const formatearNumero = (num) => {
    if (num === undefined || num === null) return '0.00'
    return Number(num).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

const formatearNumeroEntero = (num) => {
    if (num === undefined || num === null) return '0'
    return Number(num).toLocaleString('es-BO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    })
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    })
}

// ==================== WATCH ====================
watch(() => props.visible, (newVal) => {
    if (newVal && props.operador) {
        cargar()
    }
})
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="cerrar"></div>
        
        <div class="relative bg-white rounded-xl shadow-2xl max-w-5xl w-full max-h-[95vh] flex flex-col overflow-hidden border border-gray-100">
            
            <!-- Header -->
            <div class="p-4 flex-shrink-0" style="background-color: var(--color-primary, #61131a)">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center text-white">
                                <i class="fas fa-user text-sm"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-white">Ventas por Vendedor</h3>
                                <p class="text-sm text-white/80">{{ data?.operador_nombre || 'Cargando...' }}</p>
                            </div>
                        </div>
                        <p class="text-xs text-white/60 mt-1">
                            <i class="fas fa-calendar-alt mr-1"></i>
                            {{ fechaInicio || 'Inicio' }} al {{ fechaFin || 'Fin' }}
                            <span v-if="anio" class="ml-2">(Año: {{ anio }})</span>
                        </p>
                    </div>
                    <button @click="cerrar" class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Cuerpo -->
            <div class="flex-1 overflow-y-auto p-3 sm:p-5">
                
                <!-- Loading -->
                <div v-if="cargando" class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-3xl" style="color: var(--color-primary, #61131a)"></i>
                    <p class="mt-3 text-gray-500 text-sm">Cargando ventas del vendedor...</p>
                </div>
                
                <!-- Error -->
                <div v-else-if="error" class="text-center py-10 px-4">
                    <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-2 block"></i>
                    <p class="text-gray-800 font-medium text-sm">{{ error }}</p>
                    <button 
                        @click="cargar" 
                        class="mt-3 px-4 py-2 text-white rounded-lg text-xs font-medium transition hover:opacity-90"
                        style="background-color: var(--color-primary, #61131a)"
                    >
                        <i class="fas fa-sync-alt mr-1"></i> Reintentar
                    </button>
                </div>
                
                <!-- Contenido -->
                <div v-else-if="data" class="space-y-4">
                    
                    <!-- Resumen -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div class="bg-emerald-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500">Total Ventas</p>
                            <p class="text-lg font-bold text-emerald-600">Bs. {{ formatearNumero(data.total_ventas) }}</p>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500">Unidades</p>
                            <p class="text-lg font-bold text-blue-600">{{ formatearNumeroEntero(data.total_unidades) }}</p>
                        </div>
                        <div class="bg-primary-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500">Facturas</p>
                            <p class="text-lg font-bold" style="color: var(--color-primary, #61131a)">{{ data.total_facturas }}</p>
                        </div>
                    </div>

                    <!-- Lista de facturas -->
                    <div v-if="data.facturas && data.facturas.length > 0">
                        <div v-for="(factura, index) in data.facturas" :key="index" 
                            class="border border-gray-200 rounded-lg overflow-hidden mb-3">
                            
                            <!-- Cabecera factura -->
                            <div 
                                @click="toggleFactura(index)"
                                class="flex flex-col sm:flex-row justify-between items-start sm:items-center p-3 cursor-pointer hover:bg-gray-50 transition gap-2"
                                :style="{ backgroundColor: facturasExpandidas[index] ? 'var(--color-primary-50)' : 'white' }"
                            >
                                <div class="flex items-center gap-3 w-full sm:w-auto">
                                    <i :class="facturasExpandidas[index] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                       class="text-gray-400 text-sm"></i>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-gray-800">Factura #{{ factura.numero_factura }}</span>
                                            <span class="text-xs text-gray-400">
                                                <i class="far fa-calendar-alt mr-1"></i>{{ factura.fecha }}
                                            </span>
                                            <span v-if="factura.ticket_dia" class="text-xs text-gray-400">
                                                Ticket: {{ factura.ticket_dia }}
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap gap-x-3 text-xs text-gray-500 mt-0.5">
                                            <span><i class="fas fa-user mr-1"></i>{{ factura.cliente_nombre }}</span>
                                            <span><i class="fas fa-id-card mr-1"></i>NIT: {{ factura.cliente_nit }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                                    <span class="text-xs text-gray-400">{{ factura.productos.length }} producto(s)</span>
                                    <span class="font-bold text-sm" style="color: var(--color-primary, #61131a)">
                                        Bs. {{ formatearNumero(factura.importe_total) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Productos de la factura -->
                            <div v-if="facturasExpandidas[index]" class="border-t border-gray-100">
                                <!-- Vista escritorio -->
                                <div class="hidden sm:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-20">Cant.</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase w-28">Precio Unit.</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase w-28">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            <tr v-for="(prod, pIdx) in factura.productos" :key="pIdx" class="hover:bg-gray-50/50">
                                                <td class="px-3 py-2 text-sm text-gray-700">
                                                    {{ prod.producto }}
                                                    <span class="text-xs text-gray-400 ml-2">({{ prod.codigo || '-' }})</span>
                                                </td>
                                                <td class="px-3 py-2 text-sm text-center text-gray-600">{{ formatearNumeroEntero(prod.unidades) }}</td>
                                                <td class="px-3 py-2 text-sm text-right text-gray-600">Bs. {{ formatearNumero(prod.precio_unitario) }}</td>
                                                <td class="px-3 py-2 text-sm text-right font-medium" style="color: var(--color-primary, #61131a)">
                                                    Bs. {{ formatearNumero(prod.total) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="bg-gray-50">
                                            <tr>
                                                <td colspan="3" class="px-3 py-2 text-right text-sm font-semibold text-gray-700">Total Factura:</td>
                                                <td class="px-3 py-2 text-right text-sm font-bold" style="color: var(--color-primary, #61131a)">
                                                    Bs. {{ formatearNumero(factura.importe_total) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- Vista móvil -->
                                <div class="sm:hidden p-3 space-y-2">
                                    <div v-for="(prod, pIdx) in factura.productos" :key="pIdx" 
                                        class="bg-gray-50 rounded-lg p-3">
                                        <p class="font-medium text-sm text-gray-800">{{ prod.producto }}</p>
                                        <div class="grid grid-cols-3 gap-2 mt-1 text-xs">
                                            <div>
                                                <span class="text-gray-500">Cant:</span>
                                                <span class="font-medium ml-1">{{ formatearNumeroEntero(prod.unidades) }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Precio:</span>
                                                <span class="font-medium ml-1">Bs. {{ formatearNumero(prod.precio_unitario) }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Total:</span>
                                                <span class="font-medium ml-1" style="color: var(--color-primary, #61131a)">
                                                    Bs. {{ formatearNumero(prod.total) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-gray-100 rounded-lg p-2 text-right">
                                        <span class="text-xs text-gray-600">Total factura:</span>
                                        <span class="font-bold text-sm ml-2" style="color: var(--color-primary, #61131a)">
                                            Bs. {{ formatearNumero(factura.importe_total) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center text-gray-400 py-8">
                        <i class="fas fa-receipt text-3xl mb-2 block"></i>
                        No hay facturas para este vendedor
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="border-t border-gray-100 p-3 bg-gray-50 flex justify-between flex-shrink-0 rounded-b-xl">
                <span class="text-xs text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    {{ data?.facturas?.length || 0 }} facturas encontradas
                </span>
                <button 
                    @click="cerrar" 
                    class="px-5 py-2 text-white font-medium rounded-lg text-sm transition hover:opacity-90"
                    style="background-color: var(--color-primary, #61131a)"
                >
                    <i class="fas fa-times mr-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-primary-50 {
    background-color: var(--color-primary-50, #fdf2f2) !important;
}
.text-primary {
    color: var(--color-primary, #61131a) !important;
}
.border-primary {
    border-color: var(--color-primary, #61131a) !important;
}
.hover\:bg-primary-700:hover {
    background-color: var(--color-primary-700, #4a0f14) !important;
}
</style>