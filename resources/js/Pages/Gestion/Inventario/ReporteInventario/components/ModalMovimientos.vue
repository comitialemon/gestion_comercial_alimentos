<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import ModalShowMovimiento from './ModalShowMovimiento.vue'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    producto: {
        type: Object,
        default: null
    },
    sucursalId: {
        type: Number,
        required: true
    },
    fechaInicial: {
        type: String,
        required: true
    },
    fechaFinal: {
        type: String,
        required: true
    }
})

const emit = defineEmits(['update:visible', 'close'])

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

const movimientos = ref([])
const cargandoMovimientos = ref(false)
const saldoAnteriorModalRaw = ref(0)
const errorMessage = ref('')

// ==================== MODAL SHOW ====================
const modalShowVisible = ref(false)
const movimientoSeleccionado = ref(null)

const verDetalle = (movimiento) => {
    movimientoSeleccionado.value = movimiento
    modalShowVisible.value = true
}

const cerrar = () => {
    emit('update:visible', false)
    emit('close')
    errorMessage.value = ''
}

const cargarMovimientos = async () => {
    if (!props.producto) return
    
    cargandoMovimientos.value = true
    errorMessage.value = ''
    
    try {
        const response = await axios.get('/inventario/reporte-movimientos', {
            params: {
                producto_id: props.producto.IdProducto,
                sucursal_id: props.sucursalId,
                fecha_inicial: props.fechaInicial,
                fecha_final: props.fechaFinal
            }
        })
        
        if (response.data.success) {
            movimientos.value = response.data.movimientos || []
            saldoAnteriorModalRaw.value = response.data.saldo_anterior_raw || 0
        } else {
            errorMessage.value = response.data.message || 'Error al cargar movimientos'
            movimientos.value = []
        }
    } catch (error) {
        console.error('Error cargando movimientos:', error)
        errorMessage.value = error.response?.data?.message || error.message || 'Error de conexión'
        movimientos.value = []
        saldoAnteriorModalRaw.value = 0
    } finally {
        cargandoMovimientos.value = false
    }
}

// Recargar cuando se abre el modal
watch(() => props.visible, (newVal) => {
    if (newVal && props.producto && props.sucursalId) {
        cargarMovimientos()
    } else {
        movimientos.value = []
        errorMessage.value = ''
    }
})

const formatNumber = (num) => {
    if (num === undefined || num === null) return '0.000'
    const valor = Number(num)
    if (isNaN(valor)) return '0.000'
    if (valor < 0) return `- ${Math.abs(valor).toFixed(3)}`
    return valor.toFixed(3)
}

const getSaldoClass = (saldo) => {
    const saldoNum = Number(saldo) || 0
    if (saldoNum < 0) return 'text-red-600 font-bold'
    return 'text-gray-800 font-semibold'
}

onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div v-if="visible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-2 sm:p-4" @click.self="cerrar">
        <div class="bg-white rounded-xl max-w-5xl w-full max-h-[95vh] sm:max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
            
            <!-- ==================== HEADER COMPACTO ==================== -->
            <div 
                class="p-2.5 sm:p-3 flex-shrink-0 text-white"
                :style="{ backgroundColor: 'var(--color-primary, #61131a)' }"
            >
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-1.5">
                    <div>
                        <h3 class="font-bold text-sm sm:text-base text-white">
                            <i class="fas fa-chart-line mr-1.5 text-xs"></i>Movimientos
                        </h3>
                        <p class="text-white text-xs opacity-90 mt-0.5 max-w-[200px] sm:max-w-none truncate">{{ producto?.Descripcion || 'Producto' }}</p>
                        <div class="flex flex-wrap gap-2 sm:gap-3 mt-0.5 text-[10px] opacity-75 text-white">
                            <span>Código: {{ producto?.Codigo || '-' }}</span>
                            <span>{{ fechaInicial }} → {{ fechaFinal }}</span>
                        </div>
                    </div>
                    <button @click="cerrar" class="text-white/80 hover:text-white transition text-base sm:text-lg p-1 -mr-1">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- ==================== BODY ==================== -->
            <div class="flex-1 overflow-y-auto p-2.5 sm:p-4">
                <div v-if="cargandoMovimientos" class="text-center py-10">
                    <i class="fas fa-spinner fa-spin text-2xl" :style="{ color: 'var(--color-primary, #61131a)' }"></i>
                    <p class="mt-2 text-gray-500 text-xs">Cargando movimientos...</p>
                </div>
                
                <div v-else-if="errorMessage" class="text-center py-10">
                    <i class="fas fa-exclamation-triangle text-3xl text-red-400 mb-2 block"></i>
                    <p class="text-red-600 text-xs">{{ errorMessage }}</p>
                    <button 
                        @click="cargarMovimientos" 
                        class="mt-2.5 px-3 py-1.5 text-white rounded-md text-xs hover:opacity-90 transition"
                        :style="{ backgroundColor: 'var(--color-primary, #61131a)' }"
                    >
                        Reintentar
                    </button>
                </div>
                
                <div v-else-if="movimientos.length === 0" class="text-center py-10">
                    <i class="fas fa-inbox text-3xl text-gray-300 mb-2 block"></i>
                    <p class="text-gray-400 text-xs">No hay movimientos en el rango seleccionado</p>
                </div>
                
                <div v-else>
                    <!-- Resumen de saldo inicial -->
                    <div class="mb-3 p-2 bg-gray-100 rounded-lg flex flex-wrap justify-between items-center gap-1.5">
                        <span class="text-[10px] sm:text-xs text-gray-600">Saldo inicial al <strong>{{ fechaInicial }}</strong>:</span>
                        <span class="text-sm sm:text-base font-bold" :class="saldoAnteriorModalRaw < 0 ? 'text-red-600' : 'text-gray-800'">
                            {{ formatNumber(saldoAnteriorModalRaw) }}
                        </span>
                    </div>
                    
                    <!-- ==================== VISTA MÓVIL (tarjetas) ==================== -->
                    <div v-if="isMobile" class="space-y-2">
                        <div v-for="mov in movimientos" :key="mov.id" class="bg-gray-50 rounded-lg p-2.5 border border-gray-100">
                            <div class="flex justify-between items-start mb-1.5">
                                <span class="text-[10px] font-mono text-gray-500">{{ mov.fecha }}</span>
                                <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="mov.tipo === 'D' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                    {{ mov.tipo === 'D' ? 'ENTRADA' : 'SALIDA' }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-700 leading-tight mb-1.5 line-clamp-2">{{ mov.Glosa || 'Sin glosa' }}</p>
                            <div class="flex justify-between items-center pt-1.5 border-t border-gray-200">
                                <div>
                                    <p class="text-[8px] text-gray-400">Unidades</p>
                                    <p class="text-xs font-semibold" :class="mov.tipo === 'D' ? 'text-emerald-600' : 'text-red-600'">
                                        {{ mov.unidades_signo }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[8px] text-gray-400">Saldo</p>
                                    <p class="text-xs font-bold" :class="getSaldoClass(mov.saldo_acumulado_raw)">
                                        {{ formatNumber(mov.saldo_acumulado_raw) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex justify-end mt-1.5 pt-1 border-t border-gray-200">
                                <button 
                                    @click="verDetalle(mov)"
                                    class="px-2.5 py-1 text-[9px] rounded-md bg-blue-50 text-blue-600 hover:bg-blue-100 transition flex items-center gap-1"
                                >
                                    <i class="fas fa-eye text-[8px]"></i> Detalle
                                </button>
                            </div>
                            <div v-if="mov.almacen || mov.tipo_operacion" class="flex flex-wrap gap-2 mt-1.5 pt-1 text-[8px] text-gray-400">
                                <span v-if="mov.almacen"><i class="fas fa-warehouse mr-0.5"></i>{{ mov.almacen }}</span>
                                <span v-if="mov.tipo_operacion"><i class="fas fa-tag mr-0.5"></i>{{ mov.tipo_operacion }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- ==================== VISTA TABLET (tabla compacta) ==================== -->
                    <div v-else-if="isTablet" class="relative overflow-x-auto" style="max-height: 50vh; overflow-y: auto;">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100 sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th class="px-1.5 py-1.5 text-left text-[8px] font-medium text-gray-700 uppercase">Fecha</th>
                                    <th class="px-1.5 py-1.5 text-left text-[8px] font-medium text-gray-700 uppercase">Tipo</th>
                                    <th class="px-1.5 py-1.5 text-left text-[8px] font-medium text-gray-700 uppercase">Glosa</th>
                                    <th class="px-1.5 py-1.5 text-right text-[8px] font-medium text-gray-700 uppercase">Unid.</th>
                                    <th class="px-1.5 py-1.5 text-right text-[8px] font-medium text-gray-700 uppercase">Saldo</th>
                                    <th class="px-1.5 py-1.5 text-center text-[8px] font-medium text-gray-700 uppercase w-12">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="mov in movimientos" :key="mov.id" class="hover:bg-gray-50">
                                    <td class="px-1.5 py-1.5 text-[9px] text-gray-600 whitespace-nowrap">{{ mov.fecha }}</td>
                                    <td class="px-1.5 py-1.5 text-[9px]">
                                        <span :class="mov.tipo === 'D' ? 'text-emerald-600 font-bold' : 'text-red-600 font-bold'">
                                            {{ mov.tipo === 'D' ? 'ENT' : 'SAL' }}
                                        </span>
                                    </td>
                                    <td class="px-1.5 py-1.5 text-[9px] text-gray-700 max-w-[150px]">
                                        <p class="truncate">{{ mov.Glosa || 'Sin glosa' }}</p>
                                    </td>
                                    <td class="px-1.5 py-1.5 text-right text-[9px] font-mono">
                                        <span :class="mov.tipo === 'D' ? 'text-emerald-600' : 'text-red-600'">
                                            {{ mov.unidades_signo }}
                                        </span>
                                    </td>
                                    <td class="px-1.5 py-1.5 text-right text-[9px] font-medium" :class="getSaldoClass(mov.saldo_acumulado_raw)">
                                        {{ formatNumber(mov.saldo_acumulado_raw) }}
                                    </td>
                                    <td class="px-1.5 py-1.5 text-center">
                                        <button 
                                            @click="verDetalle(mov)"
                                            class="p-0.5 rounded hover:bg-blue-100 transition text-blue-600"
                                            title="Ver detalles"
                                        >
                                            <i class="fas fa-eye text-[9px]"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ==================== VISTA ESCRITORIO (tabla completa) ==================== -->
                    <div v-else class="relative overflow-x-auto" style="max-height: 50vh; overflow-y: auto;">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100 sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th class="px-2 py-1.5 text-left text-[9px] font-medium text-gray-700 uppercase">Fecha</th>
                                    <th class="px-2 py-1.5 text-left text-[9px] font-medium text-gray-700 uppercase">Tipo</th>
                                    <th class="px-2 py-1.5 text-left text-[9px] font-medium text-gray-700 uppercase">Glosa</th>
                                    <th class="px-2 py-1.5 text-right text-[9px] font-medium text-gray-700 uppercase w-20">Unidades</th>
                                    <th class="px-2 py-1.5 text-right text-[9px] font-medium text-gray-700 uppercase w-20">Saldo</th>
                                    <th class="px-2 py-1.5 text-center text-[9px] font-medium text-gray-700 uppercase w-14">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="mov in movimientos" :key="mov.id" class="hover:bg-gray-50 transition">
                                    <td class="px-2 py-1.5 text-xs text-gray-600 whitespace-nowrap">{{ mov.fecha }}</td>
                                    <td class="px-2 py-1.5 text-xs">
                                        <span class="px-1.5 py-0.5 text-[8px] rounded-full" :class="mov.tipo === 'D' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                            {{ mov.tipo === 'D' ? 'ENTRADA' : 'SALIDA' }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-1.5 text-xs text-gray-700 max-w-[250px]">
                                        <p class="truncate">{{ mov.Glosa || 'Sin glosa' }}</p>
                                        <div class="flex flex-wrap gap-2 mt-0.5">
                                            <p v-if="mov.almacen" class="text-[8px] text-gray-400">
                                                <i class="fas fa-warehouse mr-0.5"></i>{{ mov.almacen }}
                                            </p>
                                            <p v-if="mov.tipo_operacion" class="text-[8px] text-gray-400">
                                                <i class="fas fa-tag mr-0.5"></i>{{ mov.tipo_operacion }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-2 py-1.5 text-right text-xs font-mono">
                                        <span :class="mov.tipo === 'D' ? 'text-emerald-600 font-semibold' : 'text-red-600 font-semibold'">
                                            {{ mov.unidades_signo }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-1.5 text-right text-xs font-medium" :class="getSaldoClass(mov.saldo_acumulado_raw)">
                                        {{ formatNumber(mov.saldo_acumulado_raw) }}
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                        <button 
                                            @click="verDetalle(mov)"
                                            class="p-1 rounded hover:bg-blue-100 transition text-blue-600"
                                            title="Ver detalles"
                                        >
                                            <i class="fas fa-eye text-xs"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- ==================== FOOTER ==================== -->
            <div class="border-t p-2 bg-gray-50 flex justify-end flex-shrink-0 rounded-b-xl">
                <button 
                    @click="cerrar" 
                    class="px-3 py-1 text-white rounded-md text-xs hover:opacity-90 transition flex items-center gap-1.5"
                    :style="{ backgroundColor: 'var(--color-primary, #61131a)' }"
                >
                    <i class="fas fa-times text-[10px]"></i>
                    <span>Cerrar</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 🔥 MODAL SHOW (detalle del movimiento) -->
    <ModalShowMovimiento
        v-model:visible="modalShowVisible"
        :movimiento="movimientoSeleccionado"
        @close="modalShowVisible = false"
    />
</template>

<style scoped>
.bg-primary {
    background-color: var(--color-primary, #61131a) !important;
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
.hover\:bg-primary-800:hover {
    background-color: var(--color-primary-800, #3d0a0f) !important;
}
.focus\:ring-primary-500:focus {
    --tw-ring-color: var(--color-primary-500, #61131a) !important;
}
</style>