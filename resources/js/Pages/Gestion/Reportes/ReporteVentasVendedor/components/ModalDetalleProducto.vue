<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    producto: String,
    filtros: Object,
})

const emit = defineEmits(['update:modelValue'])

const modalOpen = ref(props.modelValue)
const detalles = ref([])
const cargando = ref(false)
const totalUnidades = ref(0)
const totalBolivianos = ref(0)
const vistaMobile = ref(window.innerWidth < 768)

// 🔥 FUNCIÓN PARA FORMATEAR FECHA - SIN usar new Date()
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    
    // Si ya es string y tiene formato DD/MM/YYYY, devolverlo directamente
    if (typeof fecha === 'string' && fecha.includes('/')) {
        return fecha
    }
    
    // Si es string YYYY-MM-DD, formatear manualmente
    if (typeof fecha === 'string' && fecha.includes('-')) {
        const partes = fecha.split('-')
        if (partes.length === 3) {
            return `${partes[2]}/${partes[1]}/${partes[0]}`
        }
    }
    
    return fecha
}

const formatearNumero = (value, decimals = 2) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    })
}

watch(() => props.modelValue, (newVal) => {
    modalOpen.value = newVal
    if (newVal && props.producto) {
        cargarDetalle()
    }
})

watch(modalOpen, (newVal) => {
    emit('update:modelValue', newVal)
})

const handleResize = () => {
    vistaMobile.value = window.innerWidth < 768
}

window.addEventListener('resize', handleResize)

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize)
})

// 🔥 CORREGIDO: Función para formatear fecha a YYYY-MM-DD
const formatearFechaParaBackend = (fecha) => {
    if (!fecha) return null
    
    // Si ya es YYYY-MM-DD, devolverla
    if (typeof fecha === 'string' && fecha.match(/^\d{4}-\d{2}-\d{2}$/)) {
        return fecha
    }
    
    // Si es DD/MM/YYYY, convertir a YYYY-MM-DD
    if (typeof fecha === 'string' && fecha.includes('/')) {
        const partes = fecha.split('/')
        if (partes.length === 3) {
            return `${partes[2]}-${partes[1]}-${partes[0]}`
        }
    }
    
    return fecha
}

const cargarDetalle = async () => {
    cargando.value = true
    try {
        const params = {
            producto: props.producto,
        }
        
        // 🔥 CORRECCIÓN: Formatear fechas correctamente
        if (props.filtros?.tipoBusqueda === 'dia' && props.filtros?.fecha) {
            const fechaFormateada = formatearFechaParaBackend(props.filtros.fecha)
            if (fechaFormateada) {
                params.fecha = fechaFormateada
            }
        } else {
            // Modo rango
            if (props.filtros?.fecha_desde) {
                const fechaDesde = formatearFechaParaBackend(props.filtros.fecha_desde)
                if (fechaDesde) {
                    params.fecha_desde = fechaDesde
                }
            }
            if (props.filtros?.fecha_hasta) {
                const fechaHasta = formatearFechaParaBackend(props.filtros.fecha_hasta)
                if (fechaHasta) {
                    params.fecha_hasta = fechaHasta
                }
            }
        }
        
        if (props.filtros?.metodo_pago) {
            params.metodo_pago = props.filtros.metodo_pago
        }
        
        console.log('📤 Enviando al backend:', params) // 🔥 Depuración
        
        const response = await axios.get('/gestion/reportes/ventas-vendedor/detalle-producto', { params })
        
        console.log('📥 Respuesta del backend:', response.data) // 🔥 Depuración
        
        if (response.data.success) {
            detalles.value = response.data.detalles
            totalUnidades.value = response.data.totalUnidades
            totalBolivianos.value = response.data.totalBolivianos
        }
    } catch (error) {
        console.error('Error cargando detalle:', error)
    } finally {
        cargando.value = false
    }
}

const cerrarModal = () => {
    modalOpen.value = false
}
</script>

<template>
    <Teleport to="body">
        <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="cerrarModal">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" @click="cerrarModal"></div>
            
            <!-- Modal -->
            <div class="relative z-10 flex items-center justify-center min-h-screen p-2 sm:p-4">
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-7xl mx-auto transform transition-all duration-300">
                    
                    <!-- Header -->
                    <div class="sticky top-0 z-10 flex items-center justify-between px-3 sm:px-5 py-2.5 border-b bg-primary-600 rounded-t-xl">
                        <h3 class="text-xs sm:text-sm font-semibold text-white truncate">
                            <i class="fas fa-box mr-1.5 text-xs"></i> 
                            Detalle de Ventas - {{ producto }}
                        </h3>
                        <button @click="cerrarModal" class="text-white/80 hover:text-white transition p-1 rounded-full hover:bg-white/10">
                            <i class="fas fa-times text-xs sm:text-sm"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-2 sm:p-4 max-h-[calc(100vh-10rem)] overflow-y-auto">
                        
                        <!-- Loading -->
                        <div v-if="cargando" class="flex justify-center py-12">
                            <div class="flex flex-col items-center gap-2">
                                <i class="fas fa-spinner fa-spin text-primary-500 text-2xl"></i>
                                <p class="text-xs text-gray-500">Cargando detalles...</p>
                            </div>
                        </div>

                        <!-- Tabla Desktop -->
                        <div v-else-if="!vistaMobile && detalles.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-2 sm:px-3 py-2 text-left font-medium text-gray-500 uppercase">Fecha</th>
                                        <th class="px-2 sm:px-3 py-2 text-left font-medium text-gray-500 uppercase">N° Factura</th>
                                        <th class="px-2 sm:px-3 py-2 text-left font-medium text-gray-500 uppercase">Producto Venta</th>
                                        <th class="px-2 sm:px-3 py-2 text-right font-medium text-gray-500 uppercase">Unidades</th>
                                        <th class="px-2 sm:px-3 py-2 text-right font-medium text-gray-500 uppercase">Precio Unit.</th>
                                        <th class="px-2 sm:px-3 py-2 text-right font-medium text-gray-500 uppercase">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <tr v-for="detalle in detalles" :key="detalle.NumeroFactura" class="hover:bg-gray-50 transition">
                                        <td class="px-2 sm:px-3 py-2 text-gray-600 whitespace-nowrap">{{ formatearFecha(detalle.FechaVenta) }}</td>
                                        <td class="px-2 sm:px-3 py-2 font-mono text-gray-900 whitespace-nowrap">{{ detalle.NumeroFactura }}</td>
                                        <td class="px-2 sm:px-3 py-2 text-gray-800 max-w-[200px] truncate" :title="detalle.ProductoVenta">{{ detalle.ProductoVenta }}</td>
                                        <td class="px-2 sm:px-3 py-2 text-right text-gray-700 whitespace-nowrap">{{ formatearNumero(detalle.unidades, 4) }}</td>
                                        <td class="px-2 sm:px-3 py-2 text-right text-gray-700 whitespace-nowrap">{{ formatearNumero(detalle.PrecioUnidades, 2) }}</td>
                                        <td class="px-2 sm:px-3 py-2 text-right font-semibold text-primary-600 whitespace-nowrap">{{ formatearNumero(detalle.Total, 2) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 sticky bottom-0">
                                    <tr class="border-t border-gray-200">
                                        <td colspan="3" class="px-2 sm:px-3 py-2 text-sm font-bold text-gray-800">TOTAL ACUMULADO</td>
                                        <td class="px-2 sm:px-3 py-2 text-right font-bold text-gray-800">{{ formatearNumero(totalUnidades, 4) }}</td>
                                        <td class="px-2 sm:px-3 py-2"></td>
                                        <td class="px-2 sm:px-3 py-2 text-right font-bold text-primary-700">{{ formatearNumero(totalBolivianos, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Tarjetas Mobile -->
                        <div v-else-if="vistaMobile && detalles.length > 0" class="space-y-3">
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
                            <div v-for="detalle in detalles" :key="detalle.NumeroFactura" class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                                <div class="bg-gray-50 px-3 py-2 border-b border-gray-100">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-receipt text-primary-500 text-xs"></i>
                                            <span class="text-xs font-mono font-bold text-gray-800">N° {{ detalle.NumeroFactura }}</span>
                                        </div>
                                        <span class="text-[10px] text-gray-500">{{ formatearFecha(detalle.FechaVenta) }}</span>
                                    </div>
                                </div>
                                
                                <div class="p-3 space-y-2">
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
                                            <p class="text-[10px] text-gray-500">Precio Unitario</p>
                                            <p class="text-sm font-semibold text-gray-700">{{ formatearNumero(detalle.PrecioUnidades, 2) }} Bs</p>
                                        </div>
                                    </div>
                                    
                                    <div class="pt-1 border-t border-gray-100">
                                        <div class="flex justify-between items-center">
                                            <p class="text-[10px] text-gray-500">Total</p>
                                            <p class="text-base font-bold text-primary-600">{{ formatearNumero(detalle.Total, 2) }} Bs</p>
                                        </div>
                                    </div>
                                    
                                    <div v-if="detalle.MetodoPago && detalle.MetodoPago !== 'SIN MÉTODO DE PAGO'" class="pt-1">
                                        <p class="text-[10px] text-gray-500">Método de Pago</p>
                                        <p class="text-xs font-medium text-gray-700">{{ detalle.MetodoPago }}</p>
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

                    <!-- Footer -->
                    <div class="sticky bottom-0 px-3 sm:px-5 py-2.5 border-t bg-gray-50 rounded-b-xl flex justify-end">
                        <button @click="cerrarModal" class="px-3 sm:px-4 py-1.5 bg-gray-200 text-gray-700 rounded-lg text-xs sm:text-sm font-medium hover:bg-gray-300 transition">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>