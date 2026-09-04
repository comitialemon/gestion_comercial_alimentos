<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    producto: String,
    filtros: Object,
})

const emit = defineEmits(['update:modelValue'])

// ==================== DETECTAR MÓVIL ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const modalOpen = ref(props.modelValue)
const detalles = ref([])
const cargando = ref(false)
const errorMessage = ref('')
const totalUnidades = ref(0)
const totalBolivianos = ref(0)

// ==================== MAPA DE VENDEDORES ====================
// 🔥 Cache de nombres de vendedores para evitar múltiples requests
const vendedoresCache = ref({})

const obtenerNombreVendedor = async (idOperador) => {
    if (!idOperador) return '-'
    if (vendedoresCache.value[idOperador]) {
        return vendedoresCache.value[idOperador]
    }
    
    try {
        const response = await axios.get('/gestion/reportes/ventas-sucursal/vendedor/' + idOperador)
        if (response.data.success) {
            vendedoresCache.value[idOperador] = response.data.nombre
            return response.data.nombre
        }
    } catch (error) {
        console.error('Error obteniendo vendedor:', error)
    }
    return `ID: ${idOperador}`
}

// ==================== WATCHERS ====================
watch(() => props.modelValue, (newVal) => {
    modalOpen.value = newVal
    if (newVal && props.producto) {
        cargarDetalle()
    }
})

watch(modalOpen, (newVal) => {
    emit('update:modelValue', newVal)
})

// ==================== MÉTODOS ====================
const cargarDetalle = async () => {
    cargando.value = true
    errorMessage.value = ''
    detalles.value = []
    totalUnidades.value = 0
    totalBolivianos.value = 0
    
    try {
        const params = {
            producto: props.producto,
        }
        
        if (props.filtros?.tipoBusqueda === 'dia' && props.filtros?.fecha) {
            params.fecha = props.filtros.fecha
        } else {
            if (props.filtros?.fecha_desde) params.fecha_desde = props.filtros.fecha_desde
            if (props.filtros?.fecha_hasta) params.fecha_hasta = props.filtros.fecha_hasta
        }
        if (props.filtros?.operador) params.operador = props.filtros.operador
        
        const response = await axios.get('/gestion/reportes/ventas-sucursal/detalle-producto', { params })
        
        if (response.data.success) {
            // 🔥 ENRIQUECER DETALLES CON NOMBRE DEL VENDEDOR
            const detallesConVendedor = []
            for (const detalle of response.data.detalles) {
                const nombreVendedor = await obtenerNombreVendedor(detalle.IdOperadorIngresa)
                detallesConVendedor.push({
                    ...detalle,
                    nombre_vendedor: nombreVendedor
                })
            }
            
            detalles.value = detallesConVendedor
            totalUnidades.value = response.data.totalUnidades
            totalBolivianos.value = response.data.totalBolivianos
        } else {
            errorMessage.value = response.data.message || 'Error al cargar detalles'
        }
    } catch (error) {
        console.error('Error cargando detalle:', error)
        errorMessage.value = error.response?.data?.message || 'Error de conexión'
    } finally {
        cargando.value = false
    }
}

const cerrarModal = () => {
    modalOpen.value = false
}

const formatearNumero = (value, decimals = 2) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
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

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <Teleport to="body">
        <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="cerrarModal">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" @click="cerrarModal"></div>
            
            <!-- Modal -->
            <div class="relative z-10 flex items-center justify-center min-h-screen p-2 sm:p-4">
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-5xl mx-auto transform transition-all duration-300 flex flex-col max-h-[95vh]">
                    
                    <!-- ==================== HEADER ==================== -->
                    <div class="flex-shrink-0 flex items-center justify-between px-4 sm:px-6 py-3 bg-primary-600 rounded-t-xl">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center text-white">
                                <i class="fas fa-box text-sm"></i>
                            </div>
                            <div>
                                <h3 class="text-sm sm:text-base font-semibold text-white">
                                    Detalle de Ventas
                                </h3>
                                <p class="text-xs text-white/80 truncate max-w-[200px] sm:max-w-md">
                                    {{ producto }}
                                </p>
                            </div>
                        </div>
                        <button @click="cerrarModal" class="text-white/80 hover:text-white transition p-1 rounded-full hover:bg-white/10">
                            <i class="fas fa-times text-sm sm:text-base"></i>
                        </button>
                    </div>

                    <!-- ==================== BODY ==================== -->
                    <div class="flex-1 overflow-y-auto p-3 sm:p-5 space-y-4">
                        
                        <!-- Loading -->
                        <div v-if="cargando" class="text-center py-12">
                            <i class="fas fa-circle-notch fa-spin text-3xl text-primary-600"></i>
                            <p class="mt-3 text-gray-500 text-xs font-medium">Cargando detalles...</p>
                        </div>
                        
                        <!-- Error -->
                        <div v-else-if="errorMessage" class="text-center py-10 px-4">
                            <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-2 block"></i>
                            <p class="text-gray-800 font-medium text-sm">{{ errorMessage }}</p>
                            <button 
                                @click="cargarDetalle" 
                                class="mt-3 px-4 py-2 bg-primary-600 text-white rounded-lg text-xs font-medium hover:bg-primary-700 transition"
                            >
                                Reintentar
                            </button>
                        </div>

                        <!-- ==================== TABLA ESCRITORIO / TABLET ==================== -->
                        <div v-else-if="!isMobile && detalles.length > 0" class="overflow-x-auto">
                            <div class="bg-primary-50 rounded-lg p-3 flex flex-wrap justify-between items-center gap-2 mb-4">
                                <span class="text-xs text-gray-600">Total <strong>{{ producto }}</strong></span>
                                <div class="flex gap-4">
                                    <span class="text-xs font-semibold text-emerald-700">Unidades: {{ formatearNumero(totalUnidades, 4) }}</span>
                                    <span class="text-xs font-bold text-primary-700">Total: {{ formatearNumero(totalBolivianos, 2) }} Bs</span>
                                </div>
                            </div>

                            <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                                <thead class="bg-gray-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">Fecha</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">N° Factura</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">Vendedor</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">Producto</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Unidades</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Precio Unit.</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <tr v-for="detalle in detalles" :key="detalle.NumeroFactura + detalle.FechaVenta" class="hover:bg-gray-50 transition">
                                        <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ formatearFecha(detalle.FechaVenta) }}</td>
                                        <td class="px-3 py-2 font-mono text-gray-900 whitespace-nowrap">{{ detalle.NumeroFactura }}</td>
                                        <td class="px-3 py-2 text-gray-700 whitespace-nowrap">
                                            <span class="font-medium">{{ detalle.nombre_vendedor || '-' }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-800 max-w-[200px] truncate" :title="detalle.ProductoVenta">{{ detalle.ProductoVenta }}</td>
                                        <td class="px-3 py-2 text-right text-gray-700 whitespace-nowrap">{{ formatearNumero(detalle.unidades, 4) }}</td>
                                        <td class="px-3 py-2 text-right text-gray-700 whitespace-nowrap">{{ formatearNumero(detalle.PrecioUnidades, 2) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold text-primary-600 whitespace-nowrap">{{ formatearNumero(detalle.Total, 2) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 sticky bottom-0">
                                    <tr class="border-t border-gray-200">
                                        <td colspan="4" class="px-3 py-2 text-sm font-bold text-gray-800">TOTAL ACUMULADO</td>
                                        <td class="px-3 py-2 text-right font-bold text-gray-800">{{ formatearNumero(totalUnidades, 4) }}</td>
                                        <td class="px-3 py-2"></td>
                                        <td class="px-3 py-2 text-right font-bold text-primary-700">{{ formatearNumero(totalBolivianos, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- ==================== TARJETAS MÓVIL ==================== -->
                        <div v-else-if="isMobile && detalles.length > 0" class="space-y-3">
                            <!-- Resumen -->
                            <div class="bg-primary-50 rounded-lg p-3 border border-primary-100">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] text-primary-600 uppercase font-semibold">Total Unidades</p>
                                        <p class="text-lg font-bold text-primary-700">{{ formatearNumero(totalUnidades, 4) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] text-primary-600 uppercase font-semibold">Total Bs</p>
                                        <p class="text-lg font-bold text-primary-700">{{ formatearNumero(totalBolivianos, 2) }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Tarjetas de venta -->
                            <div v-for="detalle in detalles" :key="detalle.NumeroFactura + detalle.FechaVenta" 
                                class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                                <!-- Cabecera -->
                                <div class="bg-gray-50 px-3 py-2 border-b border-gray-100">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-receipt text-primary-500 text-xs"></i>
                                            <span class="text-xs font-mono font-bold text-gray-800">N° {{ detalle.NumeroFactura }}</span>
                                        </div>
                                        <span class="text-[10px] text-gray-500">{{ formatearFecha(detalle.FechaVenta) }}</span>
                                    </div>
                                </div>
                                
                                <!-- Cuerpo -->
                                <div class="p-3 space-y-2">
                                    <div>
                                        <p class="text-[10px] text-gray-500">Vendedor</p>
                                        <p class="text-sm font-medium text-gray-800">{{ detalle.nombre_vendedor || '-' }}</p>
                                    </div>
                                    
                                    <div>
                                        <p class="text-[10px] text-gray-500">Producto</p>
                                        <p class="text-sm font-medium text-gray-800 break-words">{{ detalle.ProductoVenta }}</p>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <p class="text-[10px] text-gray-500">Unidades</p>
                                            <p class="text-sm font-semibold text-gray-700">{{ formatearNumero(detalle.unidades, 4) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-gray-500">Precio Unit.</p>
                                            <p class="text-sm font-semibold text-gray-700">{{ formatearNumero(detalle.PrecioUnidades, 2) }} Bs</p>
                                        </div>
                                    </div>
                                    
                                    <div class="pt-1 border-t border-gray-100">
                                        <div class="flex justify-between items-center">
                                            <p class="text-[10px] text-gray-500">Total</p>
                                            <p class="text-base font-bold text-primary-600">{{ formatearNumero(detalle.Total, 2) }} Bs</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sin datos -->
                        <div v-else-if="detalles.length === 0 && !cargando" class="text-center py-12">
                            <i class="fas fa-box-open text-gray-300 text-3xl mb-2 block"></i>
                            <p class="text-sm text-gray-500">No hay ventas para este producto</p>
                        </div>
                    </div>

                    <!-- ==================== FOOTER ==================== -->
                    <div class="flex-shrink-0 px-4 sm:px-6 py-3 border-t border-gray-200 bg-gray-50 rounded-b-xl flex justify-end">
                        <button @click="cerrarModal" 
                            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg text-sm transition">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
/* Scroll personalizado */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

@media (min-width: 1024px) {
    .text-sm {
        font-size: 14px !important;
    }
}
</style>