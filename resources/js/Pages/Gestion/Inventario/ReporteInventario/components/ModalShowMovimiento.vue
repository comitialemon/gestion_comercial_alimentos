<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    movimiento: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['update:visible', 'close'])

const cargando = ref(false)
const error = ref('')
const data = ref(null)

const cerrar = () => {
    emit('update:visible', false)
    emit('close')
    data.value = null
    error.value = ''
}

const cargar = async () => {
    if (!props.movimiento) return
    
    cargando.value = true
    error.value = ''
    
    try {
        const response = await axios.get(`/gestion/inventario/reporte-inventario/movimiento/${props.movimiento.id}`, {
            params: {
                sucursal_id: props.movimiento.IdSucursal
            }
        })
        
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
    if (newVal && props.movimiento) {
        cargar()
    }
})

const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const esVenta = computed(() => {
    return data.value?.es_venta || false
})

const estadoClase = (estado) => {
    return estado == 1 
        ? 'text-emerald-700 bg-emerald-50 border-emerald-200' 
        : 'text-red-700 bg-red-50 border-red-200'
}

const estadoTexto = (estado) => {
    return estado == 1 ? 'Activa' : 'Anulada'
}

const imprimir = () => {
    window.print()
}
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="cerrar"></div>
        
        <div class="relative bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden border border-gray-100">
            
            <!-- ==================== HEADER COMPACTO ==================== -->
            <div class="p-2.5 sm:p-3 flex-shrink-0 bg-primary-600 text-white flex justify-between items-center shadow-md">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-white/10 flex items-center justify-center text-white">
                        <i class="fas fa-file-invoice text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm leading-tight text-white">
                            Detalle del Movimiento
                        </h3>
                        <p class="text-[10px] text-white/80 mt-0.5">
                            {{ data?.movimiento?.tipo_operacion || 'Movimiento' }}
                            <span v-if="data?.venta?.NumeroFactura" class="ml-1 font-mono font-semibold">
                                #{{ data.venta.NumeroFactura }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-0.5">
                    <button 
                        @click="imprimir"
                        class="p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition"
                        title="Imprimir"
                    >
                        <i class="fas fa-print text-sm"></i>
                    </button>
                    <button 
                        @click="cerrar" 
                        class="p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition"
                        title="Cerrar"
                    >
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
            </div>
            
            <!-- ==================== BODY COMPACTO ==================== -->
            <div class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-3">
                
                <div v-if="cargando" class="text-center py-10">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-primary-600"></i>
                    <p class="mt-2 text-gray-500 text-xs">Cargando información...</p>
                </div>
                
                <div v-else-if="error" class="text-center py-8 px-4">
                    <i class="fas fa-exclamation-circle text-3xl text-red-500 mb-2 block"></i>
                    <p class="text-gray-800 font-medium text-xs">{{ error }}</p>
                    <button 
                        @click="cargar" 
                        class="mt-2.5 px-3 py-1.5 bg-primary-600 text-white rounded-md text-xs font-medium hover:bg-primary-700 transition"
                    >
                        Reintentar
                    </button>
                </div>
                
                <div v-else-if="data?.movimiento" class="space-y-3">
                    
                    <!-- 📄 VENTA -->
                    <div v-if="esVenta && data.venta" class="space-y-3">
                        <div class="bg-primary-50/40 border border-primary-100 rounded-lg p-2.5 text-xs text-gray-700">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 border-b border-primary-100/60 pb-2">
                                <div>
                                    <span class="text-[8px] text-gray-400 font-semibold uppercase block">Cliente</span>
                                    <span class="font-semibold text-gray-800 text-xs">{{ data.venta.nombre_cliente || 'CONSUMIDOR FINAL' }}</span>
                                    <span class="text-gray-500 text-[9px] block">NIT: {{ data.venta.nit_cliente || '0' }}</span>
                                </div>
                                <div>
                                    <span class="text-[8px] text-gray-400 font-semibold uppercase block">Fecha</span>
                                    <span class="font-medium text-gray-700 text-xs">{{ formatearFecha(data.venta.FechaVenta) }}</span>
                                </div>
                                <div>
                                    <span class="text-[8px] text-gray-400 font-semibold uppercase block">Vendedor</span>
                                    <span class="font-medium text-gray-700 text-xs">{{ data.venta.nombre_operador || '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-[8px] text-gray-400 font-semibold uppercase block">N° Factura</span>
                                    <span class="font-mono font-bold text-gray-800 text-xs">#{{ data.venta.NumeroFactura }}</span>
                                    <span v-if="data.venta.TicketDia" class="text-gray-500 text-[9px] block">Ticket #{{ data.venta.TicketDia }}</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap justify-between items-center pt-2">
                                <div>
                                    <span class="text-[8px] text-gray-400 font-semibold uppercase block">Estado</span>
                                    <span class="px-1.5 py-0.5 text-[8px] font-semibold rounded-full border" :class="estadoClase(data.venta.IdEstado)">
                                        {{ estadoTexto(data.venta.IdEstado) }}
                                    </span>
                                </div>
                                <div class="text-right">
                                    <span class="text-[8px] text-gray-400 font-semibold uppercase block">Total</span>
                                    <span class="font-black text-primary-600 text-sm">{{ formatearNumero(data.venta.ImporteVenta) }} Bs</span>
                                </div>
                            </div>
                            <div v-if="data.venta.Observacion" class="pt-1.5 border-t border-primary-100/60 mt-1.5 text-[10px]">
                                <span class="font-semibold text-gray-500 uppercase text-[8px]">Observación:</span>
                                <span class="text-gray-600">{{ data.venta.Observacion }}</span>
                            </div>
                        </div>

                        <div v-if="data.pagos?.length">
                            <span class="text-[9px] font-bold text-gray-600 uppercase tracking-wider block mb-1">💳 Formas de Pago</span>
                            <div class="flex flex-wrap gap-1.5">
                                <div v-for="(pago, idx) in data.pagos" :key="idx" class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 rounded px-2 py-1 text-[10px]">
                                    <span class="text-gray-600 font-medium">{{ pago.Concepto }}:</span>
                                    <span class="font-bold text-gray-900">{{ formatearNumero(pago.Bolivianos) }} Bs</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="data.detalles_venta?.length">
                            <span class="text-[9px] font-bold text-gray-600 uppercase tracking-wider block mb-1">📦 Productos</span>
                            <div class="border border-gray-200 rounded-lg overflow-hidden bg-white">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-200 text-[8px] font-bold text-gray-500 uppercase">
                                            <th class="px-2 py-1.5">Producto</th>
                                            <th class="px-2 py-1.5 text-right">Cant.</th>
                                            <th class="px-2 py-1.5 text-right">P.U.</th>
                                            <th class="px-2 py-1.5 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-[10px] text-gray-700">
                                        <tr v-for="(item, idx) in data.detalles_venta" :key="idx" class="hover:bg-gray-50/80 transition-colors">
                                            <td class="px-2 py-1.5 font-medium text-gray-800">{{ item.nombre }}</td>
                                            <td class="px-2 py-1.5 text-right font-mono">{{ item.unidades }}</td>
                                            <td class="px-2 py-1.5 text-right font-mono">{{ formatearNumero(item.preciounidades) }}</td>
                                            <td class="px-2 py-1.5 text-right font-bold text-gray-900 font-mono">{{ formatearNumero(item.totalbolivianos) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-gray-50 border-t border-gray-200 font-bold text-xs text-gray-800">
                                            <td colspan="3" class="px-2 py-1.5 text-right text-[10px]">TOTAL</td>
                                            <td class="px-2 py-1.5 text-right text-primary-600 font-mono text-sm">
                                                {{ formatearNumero(data.venta.ImporteVenta) }} Bs
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 📄 TODOS LOS DEMÁS TIPOS DE MOVIMIENTOS -->
                    <div v-else class="space-y-3">
                        <div class="rounded-lg p-2.5 text-xs text-gray-700 border bg-gray-50/40 border-gray-200">
                            <div class="flex items-center gap-2 mb-1.5 pb-1.5 border-b border-gray-200/50">
                                <i class="fas fa-box text-gray-600 text-sm"></i>
                                <span class="font-bold text-gray-800 text-sm">
                                    {{ data.movimiento.tipo_operacion }}
                                </span>
                                <span class="text-gray-400 text-[9px] ml-auto">
                                    {{ formatearFecha(data.movimiento.fecha_movimiento) }}
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                                <div>
                                    <span class="text-[8px] text-gray-400 font-semibold uppercase block">Producto</span>
                                    <span class="font-medium text-gray-800 text-xs">{{ data.movimiento.producto_nombre || '-' }}</span>
                                    <span class="text-[8px] text-gray-400 block">{{ data.movimiento.producto_codigo || '' }}</span>
                                </div>
                                
                                <div>
                                    <span class="text-[8px] text-gray-400 font-semibold uppercase block">Almacén</span>
                                    <span class="font-medium text-gray-700 text-xs">{{ data.movimiento.almacen_nombre || '-' }}</span>
                                </div>
                                
                                <div>
                                    <span class="text-[8px] text-gray-400 font-semibold uppercase block">Tipo</span>
                                    <span class="px-1.5 py-0.5 text-[8px] font-semibold rounded-full inline-block"
                                          :class="data.movimiento.D_H === 'D' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                        {{ data.movimiento.D_H === 'D' ? 'ENTRADA' : 'SALIDA' }}
                                    </span>
                                </div>
                                
                                <div>
                                    <span class="text-[8px] text-gray-400 font-semibold uppercase block">Unidades</span>
                                    <span class="font-bold text-gray-800 text-xs">{{ Number(data.movimiento.Unidades).toFixed(3) }}</span>
                                </div>
                                
                                <div v-if="data.movimiento.Bolivianos > 0">
                                    <span class="text-[8px] text-gray-400 font-semibold uppercase block">Bolivianos</span>
                                    <span class="font-bold text-primary-600 text-xs">{{ formatearNumero(data.movimiento.Bolivianos) }} Bs</span>
                                </div>
                            </div>
                            
                            <div v-if="data.movimiento.Glosa" class="mt-1.5 pt-1.5 border-t border-gray-200/50 text-[10px]">
                                <span class="font-semibold text-gray-500 uppercase text-[8px]">Glosa:</span>
                                <span class="text-gray-600">{{ data.movimiento.Glosa }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== FOOTER ==================== -->
            <div class="border-t border-gray-100 p-2 bg-gray-50 flex justify-end flex-shrink-0 rounded-b-xl">
                <button 
                    @click="cerrar" 
                    class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-md text-xs transition"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-primary-600 {
    background-color: var(--color-primary-600, #059669);
}
.text-primary-600 {
    color: var(--color-primary-600, #059669);
}
.hover\:bg-primary-700:hover {
    background-color: var(--color-primary-700, #047857);
}
</style>