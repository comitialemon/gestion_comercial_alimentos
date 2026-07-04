<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

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
    if (num === undefined || num === null) return '0.00'
    const valor = Number(num)
    if (isNaN(valor)) return '0.00'
    if (valor < 0) return `- ${Math.abs(valor).toFixed(2)}`
    return valor.toFixed(2)
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
    <div v-if="visible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-2 sm:p-4" @click.self="cerrar">
        <div class="bg-white rounded-xl max-w-5xl w-full max-h-[95vh] sm:max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
            
            <!-- Header - Usando variables CSS dinámicas -->
            <div 
                class="p-3 sm:p-4 flex-shrink-0 text-white"
                :style="{ backgroundColor: 'var(--color-primary, #61131a)' }"
            >
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                    <div>
                        <h3 class="font-bold text-sm sm:text-base lg:text-lg text-white">
                            <i class="fas fa-chart-line mr-2"></i>Movimientos de Inventario
                        </h3>
                        <p class="text-white text-xs sm:text-sm opacity-90 mt-1">{{ producto?.Descripcion || 'Producto' }}</p>
                        <div class="flex flex-wrap gap-2 sm:gap-4 mt-1 text-xs opacity-75 text-white">
                            <span>Código: {{ producto?.Codigo || '-' }}</span>
                            <span>Rango: {{ fechaInicial }} al {{ fechaFinal }}</span>
                        </div>
                    </div>
                    <button @click="cerrar" class="text-white hover:text-gray-200 transition text-lg sm:text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Cuerpo -->
            <div class="flex-1 overflow-y-auto p-3 sm:p-4 lg:p-5">
                <div v-if="cargandoMovimientos" class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-3xl" :style="{ color: 'var(--color-primary, #61131a)' }"></i>
                    <p class="mt-3 text-gray-500 text-sm">Cargando movimientos...</p>
                </div>
                
                <div v-else-if="errorMessage" class="text-center py-12">
                    <i class="fas fa-exclamation-triangle text-5xl text-red-400 mb-3 block"></i>
                    <p class="text-red-600 text-sm">{{ errorMessage }}</p>
                    <button 
                        @click="cargarMovimientos" 
                        class="mt-3 px-4 py-2 text-white rounded-lg text-sm hover:opacity-90 transition"
                        :style="{ backgroundColor: 'var(--color-primary, #61131a)' }"
                    >
                        Reintentar
                    </button>
                </div>
                
                <div v-else-if="movimientos.length === 0" class="text-center py-12">
                    <i class="fas fa-inbox text-5xl text-gray-300 mb-3 block"></i>
                    <p class="text-gray-400 text-sm">No hay movimientos en el rango de fechas seleccionado</p>
                </div>
                
                <div v-else>
                    <!-- Resumen de saldo inicial -->
                    <div class="mb-4 p-3 bg-gray-100 rounded-lg flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Saldo inicial al <strong>{{ fechaInicial }}</strong>:</span>
                        <span class="text-base sm:text-lg font-bold" :class="saldoAnteriorModalRaw < 0 ? 'text-red-600' : 'text-gray-800'">
                            {{ formatNumber(saldoAnteriorModalRaw) }}
                        </span>
                    </div>
                    
                    <!-- ==================== VISTA MÓVIL (tarjetas) ==================== -->
                    <div v-if="isMobile" class="space-y-3">
                        <div v-for="mov in movimientos" :key="mov.id" class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-mono text-gray-500">{{ mov.fecha }}</span>
                                <span class="px-2 py-0.5 text-[10px] rounded-full" :class="mov.tipo === 'D' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                    {{ mov.tipo === 'D' ? 'ENTRADA' : 'SALIDA' }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-700 leading-tight mb-2">{{ mov.Glosa || 'Sin glosa' }}</p>
                            <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                                <div>
                                    <p class="text-[10px] text-gray-400">Unidades</p>
                                    <p class="text-sm font-semibold" :class="mov.tipo === 'D' ? 'text-emerald-600' : 'text-red-600'">
                                        {{ mov.unidades_signo }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-gray-400">Saldo</p>
                                    <p class="text-sm font-bold" :class="getSaldoClass(mov.saldo_acumulado_raw)">
                                        {{ formatNumber(mov.saldo_acumulado_raw) }}
                                    </p>
                                </div>
                            </div>
                            <div v-if="mov.almacen || mov.tipo_operacion" class="flex flex-wrap gap-3 mt-2 pt-1 text-[10px] text-gray-400">
                                <span v-if="mov.almacen"><i class="fas fa-warehouse mr-1"></i>{{ mov.almacen }}</span>
                                <span v-if="mov.tipo_operacion"><i class="fas fa-tag mr-1"></i>{{ mov.tipo_operacion }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- ==================== VISTA TABLET (tabla compacta) ==================== -->
                    <div v-else-if="isTablet" class="relative overflow-x-auto" style="max-height: 55vh; overflow-y: auto;">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100 sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-700 uppercase">Fecha</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-700 uppercase">Tipo</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-700 uppercase">Glosa</th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-700 uppercase">Unid.</th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-700 uppercase">Saldo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="mov in movimientos" :key="mov.id" class="hover:bg-gray-50">
                                    <td class="px-2 py-2 text-xs text-gray-600 whitespace-nowrap">{{ mov.fecha }}</td>
                                    <td class="px-2 py-2 text-xs">
                                        <span :class="mov.tipo === 'D' ? 'text-emerald-600 font-bold' : 'text-red-600 font-bold'">
                                            {{ mov.tipo === 'D' ? 'ENTRADA' : 'SALIDA' }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-2 text-xs text-gray-700 max-w-[200px]">
                                        <p class="truncate">{{ mov.Glosa || 'Sin glosa' }}</p>
                                    </td>
                                    <td class="px-2 py-2 text-right text-xs font-mono">
                                        <span :class="mov.tipo === 'D' ? 'text-emerald-600' : 'text-red-600'">
                                            {{ mov.unidades_signo }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-2 text-right text-xs font-medium" :class="getSaldoClass(mov.saldo_acumulado_raw)">
                                        {{ formatNumber(mov.saldo_acumulado_raw) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ==================== VISTA ESCRITORIO (tabla completa) ==================== -->
                    <div v-else class="relative overflow-x-auto" style="max-height: 55vh; overflow-y: auto;">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100 sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th class="px-3 py-2 text-left text-sm font-medium text-gray-700 uppercase">Fecha</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium text-gray-700 uppercase">Tipo</th>
                                    <th class="px-3 py-2 text-left text-sm font-medium text-gray-700 uppercase">Glosa</th>
                                    <th class="px-3 py-2 text-right text-sm font-medium text-gray-700 uppercase w-24">Unidades</th>
                                    <th class="px-3 py-2 text-right text-sm font-medium text-gray-700 uppercase w-24">Saldo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="mov in movimientos" :key="mov.id" class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 text-sm text-gray-600 whitespace-nowrap">{{ mov.fecha }}</td>
                                    <td class="px-3 py-2 text-sm">
                                        <span class="px-2 py-0.5 text-xs rounded-full" :class="mov.tipo === 'D' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                            {{ mov.tipo === 'D' ? 'ENTRADA' : 'SALIDA' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-700">
                                        <p class="whitespace-normal break-words leading-relaxed max-w-md">{{ mov.Glosa || 'Sin glosa' }}</p>
                                        <div class="flex flex-wrap gap-3 mt-1">
                                            <p v-if="mov.almacen" class="text-xs text-gray-400">
                                                <i class="fas fa-warehouse mr-1"></i>{{ mov.almacen }}
                                            </p>
                                            <p v-if="mov.tipo_operacion" class="text-xs text-gray-400">
                                                <i class="fas fa-tag mr-1"></i>{{ mov.tipo_operacion }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm font-mono">
                                        <span :class="mov.tipo === 'D' ? 'text-emerald-600 font-semibold' : 'text-red-600 font-semibold'">
                                            {{ mov.unidades_signo }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm font-medium" :class="getSaldoClass(mov.saldo_acumulado_raw)">
                                        {{ formatNumber(mov.saldo_acumulado_raw) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="border-t p-3 bg-gray-50 flex justify-end flex-shrink-0">
                <button 
                    @click="cerrar" 
                    class="px-3 py-1.5 sm:px-4 sm:py-2 text-white rounded-lg text-sm hover:opacity-90 transition flex items-center gap-2"
                    :style="{ backgroundColor: 'var(--color-primary, #61131a)' }"
                >
                    <i class="fas fa-times"></i>
                    <span>Cerrar</span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* 🔥 Clases auxiliares que usan variables CSS */
.bg-primary {
    background-color: var(--color-primary, #61131a) !important;
}

.text-primary {
    color: var(--color-primary, #61131a) !important;
}

.border-primary {
    border-color: var(--color-primary, #61131a) !important;
}

/* 🔥 Hover usando variables CSS */
.hover\:bg-primary-700:hover {
    background-color: var(--color-primary-700, #4a0f14) !important;
}

.hover\:bg-primary-800:hover {
    background-color: var(--color-primary-800, #3d0a0f) !important;
}

/* 🔥 Focus usando variables CSS */
.focus\:ring-primary-500:focus {
    --tw-ring-color: var(--color-primary-500, #61131a) !important;
}

/* Asegurar que los textos sean legibles en PC */
@media (min-width: 1024px) {
    .text-sm {
        font-size: 14px !important;
    }
}
</style>