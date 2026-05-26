<script setup>
import { ref, watch } from 'vue'
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
</script>

<template>
    <div v-if="visible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-2 sm:p-4" @click.self="cerrar">
        <div class="bg-white rounded-xl max-w-5xl w-full max-h-[95vh] sm:max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
            
            <!-- Header -->
            <div class="bg-guindo text-white p-3 sm:p-4 flex-shrink-0">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                    <div>
                        <h3 class="text-white font-bold text-base sm:text-lg">
                            <i class="fas fa-chart-line mr-2"></i>Movimientos de Inventario
                        </h3>
                        <p class="text-white text-xs sm:text-sm opacity-90 mt-1">{{ producto?.Descripcion || 'Producto' }}</p>
                        <div class="flex flex-wrap gap-2 sm:gap-4 mt-1 text-xs opacity-75">
                            <span>Código: {{ producto?.Codigo || '-' }}</span>
                            <span>Rango: {{ fechaInicial }} al {{ fechaFinal }}</span>
                        </div>
                    </div>
                    <button @click="cerrar" class="text-white hover:text-gray-200 transition text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Cuerpo -->
            <div class="flex-1 overflow-y-auto p-3 sm:p-4">
                <div v-if="cargandoMovimientos" class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-3xl text-guindo"></i>
                    <p class="mt-3 text-gray-500">Cargando movimientos...</p>
                </div>
                
                <div v-else-if="errorMessage" class="text-center py-12">
                    <i class="fas fa-exclamation-triangle text-5xl text-red-400 mb-3 block"></i>
                    <p class="text-red-600">{{ errorMessage }}</p>
                    <button @click="cargarMovimientos" class="mt-3 px-4 py-2 bg-guindo text-white rounded-lg text-sm">
                        Reintentar
                    </button>
                </div>
                
                <div v-else-if="movimientos.length === 0" class="text-center py-12">
                    <i class="fas fa-inbox text-5xl text-gray-300 mb-3 block"></i>
                    <p class="text-gray-400">No hay movimientos en el rango de fechas seleccionado</p>
                </div>
                
                <div v-else>
                    <!-- Resumen de saldo inicial -->
                    <div class="mb-4 p-3 bg-gray-100 rounded-lg flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Saldo inicial al <strong>{{ fechaInicial }}</strong>:</span>
                        <span class="text-base sm:text-lg font-bold" :class="saldoAnteriorModalRaw < 0 ? 'text-red-600' : 'text-gray-800'">
                            {{ formatNumber(saldoAnteriorModalRaw) }}
                        </span>
                    </div>
                    
                    <!-- Tabla de movimientos -->
                    <div class="relative overflow-x-auto" style="max-height: 50vh; overflow-y: auto;">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100 sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th class="px-2 py-2 sm:px-3 text-left text-xs font-medium text-gray-700 uppercase">Fecha</th>
                                    <th class="px-2 py-2 sm:px-3 text-left text-xs font-medium text-gray-700 uppercase">Documento</th>
                                    <th class="px-2 py-2 sm:px-3 text-left text-xs font-medium text-gray-700 uppercase">Tipo</th>
                                    <th class="px-2 py-2 sm:px-3 text-left text-xs font-medium text-gray-700 uppercase">Glosa</th>
                                    <th class="px-2 py-2 sm:px-3 text-right text-xs font-medium text-gray-700 uppercase">Unidades</th>
                                    <th class="px-2 py-2 sm:px-3 text-right text-xs font-medium text-gray-700 uppercase">Saldo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="mov in movimientos" :key="mov.id" class="hover:bg-gray-50 transition">
                                    <td class="px-2 py-2 sm:px-3 text-xs sm:text-sm text-gray-600 whitespace-nowrap">{{ mov.fecha }}</td>
                                    <td class="px-2 py-2 sm:px-3 text-xs sm:text-sm text-gray-600 whitespace-nowrap">{{ mov.IdDocumento || '-' }}</td>
                                    <td class="px-2 py-2 sm:px-3 text-xs sm:text-sm">
                                        <span :class="mov.tipo === 'D' ? 'text-emerald-600 font-bold' : 'text-red-600 font-bold'">
                                            {{ mov.tipo === 'D' ? 'ENTRADA' : 'SALIDA' }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-2 sm:px-3 text-xs sm:text-sm text-gray-700">
                                        <p class="whitespace-normal break-words leading-relaxed">{{ mov.Glosa || 'Sin glosa' }}</p>
                                        <div class="flex flex-wrap gap-2 sm:gap-3 mt-1">
                                            <p v-if="mov.almacen" class="text-xs text-gray-400">
                                                <i class="fas fa-warehouse mr-1"></i>{{ mov.almacen }}
                                            </p>
                                            <p v-if="mov.tipo_operacion" class="text-xs text-gray-400">
                                                <i class="fas fa-tag mr-1"></i>{{ mov.tipo_operacion }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2 sm:px-3 text-right text-xs sm:text-sm font-mono">
                                        <span :class="mov.tipo === 'D' ? 'text-emerald-600' : 'text-red-600'">
                                            {{ mov.unidades_signo }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-2 sm:px-3 text-right text-xs sm:text-sm font-medium" :class="getSaldoClass(mov.saldo_acumulado_raw)">
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
                <button @click="cerrar" class="px-3 py-1.5 sm:px-4 sm:py-2 bg-guindo text-white rounded-lg text-sm hover:bg-guindo-700 transition">
                    <i class="fas fa-times mr-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-guindo {
    background-color: #61131a;
}
.text-guindo {
    color: #61131a;
}
.border-guindo {
    border-color: #61131a;
}
</style>