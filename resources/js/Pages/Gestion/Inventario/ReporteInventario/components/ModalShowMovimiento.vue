<!-- resources/js/Pages/Gestion/Inventario/components/ModalShowMovimiento.vue -->
<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    movimientoId: {
        type: Number,
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
    if (!props.movimientoId) return
    
    cargando.value = true
    error.value = ''
    
    try {
        const response = await axios.get(`/gestion/inventario/reporte-inventario/movimiento/${props.movimientoId}`)
        
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

watch(() => props.visible, (newVal) => {
    if (newVal && props.movimientoId) {
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

// 🔥 ICONOS Y COLORES SEGÚN EL TIPO DE MOVIMIENTO
const getIconoPorTipo = (tipo) => {
    if (!tipo) return 'fa-box'
    const lower = tipo.toLowerCase()
    if (lower.includes('sobrante') || lower.includes('sobrante cocida')) return 'fa-arrow-up'
    if (lower.includes('faltante')) return 'fa-arrow-down'
    if (lower.includes('dañada')) return 'fa-times-circle'
    if (lower.includes('reventado')) return 'fa-fire'
    if (lower.includes('traspaso')) return 'fa-exchange-alt'
    if (lower.includes('produccion') || lower.includes('producido') || lower.includes('elaboracion')) return 'fa-industry'
    if (lower.includes('compra')) return 'fa-shopping-cart'
    if (lower.includes('ajuste')) return 'fa-tools'
    if (lower.includes('inventario fisico')) return 'fa-clipboard-list'
    if (lower.includes('mala creacion')) return 'fa-exclamation-triangle'
    if (lower.includes('degustacion')) return 'fa-utensils'
    if (lower.includes('pruebas')) return 'fa-flask'
    if (lower.includes('fecha de vencimiento')) return 'fa-calendar-times'
    if (lower.includes('por cruce')) return 'fa-random'
    if (lower.includes('anulacion')) return 'fa-ban'
    return 'fa-box'
}

const getColorPorTipo = (tipo) => {
    if (!tipo) return 'text-gray-600'
    const lower = tipo.toLowerCase()
    if (lower.includes('sobrante') || lower.includes('sobrante cocida')) return 'text-emerald-600'
    if (lower.includes('faltante')) return 'text-red-600'
    if (lower.includes('dañada')) return 'text-red-600'
    if (lower.includes('reventado')) return 'text-orange-600'
    if (lower.includes('traspaso')) return 'text-purple-600'
    if (lower.includes('produccion') || lower.includes('producido') || lower.includes('elaboracion')) return 'text-blue-600'
    if (lower.includes('compra')) return 'text-green-600'
    if (lower.includes('ajuste')) return 'text-amber-600'
    if (lower.includes('inventario fisico')) return 'text-indigo-600'
    if (lower.includes('mala creacion')) return 'text-red-500'
    if (lower.includes('degustacion')) return 'text-yellow-600'
    if (lower.includes('pruebas')) return 'text-cyan-600'
    if (lower.includes('fecha de vencimiento')) return 'text-gray-500'
    if (lower.includes('por cruce')) return 'text-pink-600'
    if (lower.includes('anulacion')) return 'text-red-400'
    return 'text-gray-600'
}

const getBgColorPorTipo = (tipo) => {
    if (!tipo) return 'bg-gray-50/40 border-gray-200'
    const lower = tipo.toLowerCase()
    if (lower.includes('sobrante') || lower.includes('sobrante cocida')) return 'bg-emerald-50/40 border-emerald-200'
    if (lower.includes('faltante')) return 'bg-red-50/40 border-red-200'
    if (lower.includes('dañada')) return 'bg-red-50/40 border-red-200'
    if (lower.includes('reventado')) return 'bg-orange-50/40 border-orange-200'
    if (lower.includes('traspaso')) return 'bg-purple-50/40 border-purple-200'
    if (lower.includes('produccion') || lower.includes('producido') || lower.includes('elaboracion')) return 'bg-blue-50/40 border-blue-200'
    if (lower.includes('compra')) return 'bg-green-50/40 border-green-200'
    if (lower.includes('ajuste')) return 'bg-amber-50/40 border-amber-200'
    if (lower.includes('inventario fisico')) return 'bg-indigo-50/40 border-indigo-200'
    if (lower.includes('mala creacion')) return 'bg-red-50/40 border-red-200'
    if (lower.includes('degustacion')) return 'bg-yellow-50/40 border-yellow-200'
    if (lower.includes('pruebas')) return 'bg-cyan-50/40 border-cyan-200'
    if (lower.includes('fecha de vencimiento')) return 'bg-gray-50/40 border-gray-200'
    if (lower.includes('por cruce')) return 'bg-pink-50/40 border-pink-200'
    if (lower.includes('anulacion')) return 'bg-gray-50/40 border-red-200'
    return 'bg-gray-50/40 border-gray-200'
}
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="cerrar"></div>
        
        <div class="relative bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden border border-gray-100">
            
            <!-- Header -->
            <div class="p-4 flex-shrink-0 bg-primary-600 text-white flex justify-between items-center shadow-md">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center text-white">
                        <i class="fas fa-file-invoice text-base"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-base leading-tight">
                            Detalle del Movimiento
                        </h3>
                        <p class="text-xs text-white/80 mt-0.5">
                            {{ data?.movimiento?.tipo_operacion || 'Movimiento' }}
                            <span v-if="data?.venta?.NumeroFactura" class="ml-1.5 font-mono font-semibold">
                                #{{ data.venta.NumeroFactura }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <button 
                        @click="imprimir"
                        class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition"
                        title="Imprimir"
                    >
                        <i class="fas fa-print"></i>
                    </button>
                    <button 
                        @click="cerrar" 
                        class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition"
                        title="Cerrar"
                    >
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Cuerpo -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4">
                
                <div v-if="cargando" class="text-center py-12">
                    <i class="fas fa-circle-notch fa-spin text-3xl text-primary-600"></i>
                    <p class="mt-3 text-gray-500 text-xs font-medium">Cargando información...</p>
                </div>
                
                <div v-else-if="error" class="text-center py-10 px-4">
                    <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-2 block"></i>
                    <p class="text-gray-800 font-medium text-sm">{{ error }}</p>
                    <button 
                        @click="cargar" 
                        class="mt-3 px-4 py-2 bg-primary-600 text-white rounded-lg text-xs font-medium hover:bg-primary-700 transition"
                    >
                        Reintentar
                    </button>
                </div>
                
                <div v-else-if="data?.movimiento" class="space-y-4">
                    
                    <!-- 📄 VENTA (solo si es venta) -->
                    <div v-if="esVenta && data.venta" class="space-y-4">
                        <div class="bg-primary-50/40 border border-primary-100 rounded-lg p-3 text-xs text-gray-700">
                            <div class="flex flex-wrap items-center justify-between gap-y-2 gap-x-4 border-b border-primary-100/60 pb-2.5">
                                <div>
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase block">Cliente</span>
                                    <span class="font-semibold text-gray-800">{{ data.venta.nombre_cliente || 'CONSUMIDOR FINAL' }}</span>
                                    <span class="text-gray-500 text-[11px] ml-1.5">(NIT: {{ data.venta.nit_cliente || '0' }})</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase block">Fecha</span>
                                    <span class="font-medium text-gray-700">{{ formatearFecha(data.venta.FechaVenta) }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase block">Vendedor</span>
                                    <span class="font-medium text-gray-700">{{ data.venta.nombre_operador || '-' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div>
                                        <span class="text-[10px] text-gray-400 font-semibold uppercase block">N° Factura</span>
                                        <span class="font-mono font-bold text-gray-800">#{{ data.venta.NumeroFactura }}</span>
                                        <span v-if="data.venta.TicketDia" class="text-gray-500 text-[11px] ml-1">(Ticket #{{ data.venta.TicketDia }})</span>
                                    </div>
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full border" :class="estadoClase(data.venta.IdEstado)">
                                        {{ estadoTexto(data.venta.IdEstado) }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase block">Total</span>
                                    <span class="font-black text-primary-600 text-sm">{{ formatearNumero(data.venta.ImporteVenta) }} Bs</span>
                                </div>
                            </div>
                            <div class="pt-2 flex items-center gap-2 text-[11px]">
                                <span class="font-semibold text-gray-500 uppercase text-[10px]">Observación:</span>
                                <span class="text-gray-600 italic">{{ data.venta.Observacion || '-' }}</span>
                            </div>
                        </div>

                        <div v-if="data.pagos?.length">
                            <span class="text-[11px] font-bold text-gray-600 uppercase tracking-wider block mb-1.5">💳 Formas de Pago</span>
                            <div class="flex flex-wrap gap-2">
                                <div v-for="(pago, idx) in data.pagos" :key="idx" class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-md px-3 py-1.5 text-xs">
                                    <span class="text-gray-600 font-medium">{{ pago.Concepto }}:</span>
                                    <span class="font-bold text-gray-900 font-mono">{{ formatearNumero(pago.Bolivianos) }} Bs</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="data.detalles_venta?.length">
                            <span class="text-[11px] font-bold text-gray-600 uppercase tracking-wider block mb-1.5">📦 Productos</span>
                            <div class="border border-gray-200 rounded-lg overflow-hidden bg-white">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase">
                                            <th class="px-3 py-2">Producto</th>
                                            <th class="px-3 py-2 text-right">Cant.</th>
                                            <th class="px-3 py-2 text-right">P.U.</th>
                                            <th class="px-3 py-2 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                                        <tr v-for="(item, idx) in data.detalles_venta" :key="idx" class="hover:bg-gray-50/80 transition-colors">
                                            <td class="px-3 py-2 font-medium text-gray-800">{{ item.nombre }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ item.unidades }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ formatearNumero(item.preciounidades) }}</td>
                                            <td class="px-3 py-2 text-right font-bold text-gray-900 font-mono">{{ formatearNumero(item.totalbolivianos) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-gray-50 border-t border-gray-200 font-bold text-xs text-gray-800">
                                            <td colspan="3" class="px-3 py-2 text-right">TOTAL</td>
                                            <td class="px-3 py-2 text-right text-primary-600 font-mono text-sm">
                                                {{ formatearNumero(data.venta.ImporteVenta) }} Bs
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 📄 TODOS LOS DEMÁS TIPOS DE MOVIMIENTOS -->
                    <div v-else class="space-y-4">
                        
                        <!-- Tarjeta con información del movimiento -->
                        <div class="rounded-lg p-3 text-xs text-gray-700 border" 
                             :class="getBgColorPorTipo(data.movimiento.tipo_operacion)">
                            
                            <div class="flex items-center gap-2 mb-2 pb-2 border-b" 
                                 :class="getBgColorPorTipo(data.movimiento.tipo_operacion)">
                                <i class="fas text-base" 
                                   :class="[getIconoPorTipo(data.movimiento.tipo_operacion), getColorPorTipo(data.movimiento.tipo_operacion)]">
                                </i>
                                <span class="font-bold text-gray-800 text-sm">
                                    {{ data.movimiento.tipo_operacion }}
                                </span>
                                <span class="text-gray-400 text-[10px] ml-auto">
                                    {{ formatearFecha(data.movimiento.fecha_movimiento) }}
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <div>
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase block">Producto</span>
                                    <span class="font-medium text-gray-800 text-sm">{{ data.movimiento.producto_nombre || '-' }}</span>
                                    <span class="text-[10px] text-gray-400 block">{{ data.movimiento.producto_codigo || '' }}</span>
                                </div>
                                
                                <div>
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase block">Almacén</span>
                                    <span class="font-medium text-gray-700">{{ data.movimiento.almacen_nombre || '-' }}</span>
                                </div>
                                
                                <div>
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase block">Tipo Movimiento</span>
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full inline-block"
                                          :class="data.movimiento.D_H === 'D' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                        {{ data.movimiento.D_H === 'D' ? 'ENTRADA' : 'SALIDA' }}
                                    </span>
                                </div>
                                
                                <div>
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase block">Unidades</span>
                                    <span class="font-bold text-gray-800">{{ Number(data.movimiento.Unidades).toFixed(3) }}</span>
                                </div>
                                
                                <div v-if="data.movimiento.Bolivianos > 0">
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase block">Bolivianos</span>
                                    <span class="font-bold text-primary-600">{{ formatearNumero(data.movimiento.Bolivianos) }} Bs</span>
                                </div>
                            </div>
                            
                            <div v-if="data.movimiento.Glosa" class="mt-2 pt-2 border-t border-gray-200/50 text-[11px]">
                                <span class="font-semibold text-gray-500 uppercase text-[10px]">Glosa:</span>
                                <span class="text-gray-600">{{ data.movimiento.Glosa }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="border-t border-gray-100 p-3 bg-gray-50 flex justify-end flex-shrink-0">
                <button 
                    @click="cerrar" 
                    class="px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg text-xs transition"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</template>