<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import { inject } from 'vue'

const toast = inject('toast')

const props = defineProps({
    visible: Boolean,
    ventaId: Number
})

const emit = defineEmits(['update:visible', 'activado'])

// =============================================
// ESTADO
// =============================================
const cargando = ref(false)
const venta = ref(null)
const detalles = ref([])
const activando = ref(false)
const error = ref(null)

// =============================================
// CARGAR DATOS
// =============================================
const cargarDetalle = async () => {
    if (!props.ventaId) return
    
    cargando.value = true
    error.value = null
    
    try {
        const response = await axios.get(`/gestion/reportes/control-interno/ventas/${props.ventaId}/detalle-modal`)
        
        if (response.data.success) {
            venta.value = response.data.venta
            detalles.value = response.data.detalles || []
            console.log('📝 Detalles cargados:', detalles.value)
        } else {
            error.value = response.data.message || 'Error al cargar el detalle'
        }
    } catch (err) {
        console.error('Error:', err)
        error.value = err.response?.data?.message || 'Error al cargar el detalle'
    } finally {
        cargando.value = false
    }
}

// =============================================
// ACTIVAR FACTURA
// =============================================
const activarFactura = async () => {
    if (!venta.value) return
    
    activando.value = true
    
    try {
        const response = await axios.post(`/gestion/reportes/control-interno/ventas/${venta.value.IdVentas}/cambiar-estado`, {
            estado: 0
        })
        
        if (response.data.success) {
            toast?.success('Factura activada', response.data.message)
            emit('activado')
            cerrar()
        } else {
            toast?.error('Error', response.data.message)
        }
    } catch (err) {
        console.error('Error:', err)
        toast?.error('Error', err.response?.data?.message || 'Error al activar')
    } finally {
        activando.value = false
    }
}

// =============================================
// CERRAR MODAL
// =============================================
const cerrar = () => {
    emit('update:visible', false)
    venta.value = null
    detalles.value = []
    error.value = null
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

const puedeActivar = () => {
    return venta.value && venta.value.ActivoInactivo === 1 && venta.value.LiquidadoVendedor === 0
}

// =============================================
// WATCH
// =============================================
watch(() => props.visible, (nuevoValor) => {
    if (nuevoValor && props.ventaId) {
        cargarDetalle()
    }
})

watch(() => props.ventaId, (nuevoId) => {
    if (props.visible && nuevoId) {
        cargarDetalle()
    }
})
</script>

<template>
    <div v-if="visible" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-2 sm:p-4" @click.self="cerrar">
        <div class="bg-white rounded-xl w-full max-w-4xl max-h-[95vh] overflow-hidden shadow-xl flex flex-col">
            
            <!-- 🔥 HEADER -->
            <div class="bg-primary-700 px-4 py-3 flex justify-between items-center flex-shrink-0">
                <div class="flex items-center gap-3">
                    <i class="fas fa-file-invoice text-white text-xl"></i>
                    <div>
                        <h3 class="text-white font-bold text-sm sm:text-base">Detalle de Factura</h3>
                        <p class="text-white/70 text-[10px] sm:text-xs" v-if="venta">
                            N° {{ venta.NumeroFactura }} - {{ formatearFecha(venta.FechaVenta) }}
                        </p>
                    </div>
                </div>
                <button @click="cerrar" class="text-white/80 hover:text-white text-xl">✕</button>
            </div>

            <!-- 🔥 CONTENIDO -->
            <div class="flex-1 overflow-y-auto p-3 sm:p-5">
                
                <!-- Cargando -->
                <div v-if="cargando" class="flex justify-center items-center py-12">
                    <i class="fas fa-spinner fa-spin text-primary-600 text-2xl"></i>
                    <span class="ml-2 text-gray-500">Cargando detalle...</span>
                </div>

                <!-- Error -->
                <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 text-center text-red-600">
                    <i class="fas fa-exclamation-circle text-xl mb-2 block"></i>
                    {{ error }}
                </div>

                <!-- Datos -->
                <template v-else-if="venta">
                    
                    <!-- Cabecera de la venta -->
                    <div class="bg-gray-50 rounded-lg p-3 sm:p-4 grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-4 mb-4">
                        <div>
                            <div class="text-[10px] text-gray-400">Cliente</div>
                            <div class="text-xs sm:text-sm font-medium text-gray-800">{{ venta.cliente_nombre || 'Sin cliente' }}</div>
                            <div class="text-[10px] text-gray-400">NIT: {{ venta.cliente_nit || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-gray-400">Sucursal</div>
                            <div class="text-xs sm:text-sm font-medium text-gray-800">{{ venta.sucursal_nombre || 'Sin sucursal' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-gray-400">Vendedor</div>
                            <div class="text-xs sm:text-sm font-medium text-gray-800">{{ venta.vendedor_nombre || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-gray-400">Estado</div>
                            <span class="px-2 py-1 text-xs rounded-full" :class="getEstadoColor(venta.ActivoInactivo)">
                                {{ getEstadoTexto(venta.ActivoInactivo) }}
                            </span>
                            <div v-if="venta.LiquidadoVendedor > 0" class="text-[10px] text-red-500">
                                <i class="fas fa-check-circle"></i> Liquidada
                            </div>
                        </div>
                    </div>

                    <!-- 🔥 TABLA DE DETALLES -->
                    <div class="border rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-3 py-2 text-center text-[10px] sm:text-xs font-medium text-gray-500 uppercase">Cant.</th>
                                    <th class="px-3 py-2 text-right text-[10px] sm:text-xs font-medium text-gray-500 uppercase">Precio</th>
                                    <th class="px-3 py-2 text-right text-[10px] sm:text-xs font-medium text-gray-500 uppercase">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="detalle in detalles" :key="detalle.IdDetalleVenta" class="hover:bg-gray-50">
                                    <td class="px-3 py-2">
                                        <div class="text-xs sm:text-sm font-medium text-gray-800">{{ detalle.producto_nombre }}</div>
                                        <div class="text-[10px] text-gray-400">{{ detalle.producto_codigo }}</div>
                                        
                                        <!-- 🔥 MOSTRAR PRODUCTOS DETALLE (ORIGINALES Y SUSTITUTOS) -->
                                        <div v-if="detalle.productos_detalle && detalle.productos_detalle.length > 0" 
                                             class="mt-1 space-y-0.5">
                                            <div v-for="prod in detalle.productos_detalle" 
                                                 :key="prod.id_producto"
                                                 class="text-[10px] flex items-center gap-1"
                                                 :class="prod.color || 'text-gray-600'">
                                                <span>{{ prod.icon || '📦' }}</span>
                                                <span>{{ prod.nombre }}</span>
                                                <span class="font-semibold">x{{ prod.cantidad }}</span>
                                                <span v-if="prod.tipo === 'sustituto'" class="text-[8px] text-amber-500">
                                                    (reemplaza al original)
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- 🔥 PERSONALIZACIÓN AGRUPADA (fallback) -->
                                        <div v-else-if="detalle.personalizacion && detalle.personalizacion.length > 0" 
                                             class="mt-1 space-y-0.5">
                                            <div v-for="item in detalle.personalizacion" 
                                                 :key="item.id_producto_sustituto"
                                                 class="text-[10px] flex items-center gap-1 text-amber-600">
                                                <span>🔄</span>
                                                <span>{{ item.nombre_sustituto }}</span>
                                                <span class="font-semibold">x{{ item.cantidad_total }}</span>
                                                <span class="text-[8px] text-amber-500">
                                                    (reemplaza a {{ item.nombre_original }})
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- 🔥 COMPOSICIÓN (si no hay personalización) -->
                                        <div v-else-if="detalle.composicion && detalle.composicion.length > 0 && !detalle.tiene_personalizacion" 
                                             class="mt-1 space-y-0.5">
                                            <div v-for="comp in detalle.composicion" 
                                                 :key="comp.id_producto"
                                                 class="text-[10px] flex items-center gap-1 text-gray-500">
                                                <span>📦</span>
                                                <span>{{ comp.nombre }}</span>
                                                <span class="font-semibold">x{{ comp.porcion * detalle.unidades }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-center text-xs sm:text-sm text-gray-700">
                                        {{ detalle.unidades }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs sm:text-sm text-gray-700">
                                        {{ formatearMonto(detalle.preciounidades) }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs sm:text-sm font-bold text-primary-600">
                                        {{ formatearMonto(detalle.totalbolivianos) }}
                                    </td>
                                </tr>
                                <tr v-if="!detalles.length">
                                    <td colspan="4" class="px-3 py-8 text-center text-gray-400 text-sm">
                                        No hay detalles
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50 border-t">
                                <tr>
                                    <td colspan="3" class="px-3 py-2 text-right text-xs sm:text-sm font-bold">TOTAL:</td>
                                    <td class="px-3 py-2 text-right text-sm sm:text-base font-bold text-primary-700">
                                        {{ formatearMonto(venta.ImporteVenta) }} Bs
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- 🔥 INFORMACIÓN ADICIONAL -->
                    <div class="mt-4 text-[10px] sm:text-xs text-gray-400 grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div>
                            <span class="font-medium">N° Autorización:</span> 
                            {{ venta.NumeroAutorizacion || '-' }}
                        </div>
                        <div>
                            <span class="font-medium">Fecha:</span> 
                            {{ formatearFecha(venta.FechaVenta) }}
                        </div>
                        <div>
                            <span class="font-medium">Última actualización:</span> 
                            {{ formatearFecha(venta.FechaUltimaActualizcion) }}
                        </div>
                    </div>

                </template>
            </div>

            <!-- 🔥 FOOTER -->
            <div class="border-t px-4 py-3 flex flex-wrap justify-between items-center gap-2 bg-gray-50 flex-shrink-0">
                <div class="text-xs text-gray-500">
                    <i class="fas fa-info-circle text-primary-500 mr-1"></i>
                    <span class="text-green-600">● Activo</span> = Borrador (editable) 
                    <span class="text-red-600">● Inactivo</span> = Cerrado
                </div>
                <div class="flex gap-2">
                    <button 
                        v-if="puedeActivar()" 
                        @click="activarFactura" 
                        :disabled="activando"
                        class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs flex items-center gap-2 disabled:opacity-50"
                    >
                        <i v-if="activando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-check"></i>
                        {{ activando ? 'Activando...' : 'Activar Factura' }}
                    </button>
                    <button @click="cerrar" class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs hover:bg-gray-100">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>