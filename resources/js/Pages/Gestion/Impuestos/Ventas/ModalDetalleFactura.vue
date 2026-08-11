<script setup>
import { ref, computed, inject, watch } from 'vue'
import axios from 'axios'

const toast = inject('toast')

const props = defineProps({
    visible: Boolean,
    ventaId: Number
})

const emit = defineEmits(['update:visible', 'activado'])

// =============================================
// ESTADO
// =============================================
const loading = ref(false)
const activando = ref(false)
const venta = ref(null)
const detalles = ref([])

// =============================================
// COMPUTADOS
// =============================================
const totalFactura = computed(() => {
    return detalles.value?.reduce((sum, d) => sum + (d.totalbolivianos || 0), 0) || 0
})

const getEstadoColor = (activo) => {
    return activo === 1 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
}

const getEstadoTexto = (activo) => {
    return activo === 1 ? 'Inactivo' : 'Activo'
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

// =============================================
// CARGAR DETALLE
// =============================================
const cargarDetalle = async () => {
    if (!props.ventaId) return
    
    loading.value = true
    
    try {
        const response = await axios.get(`/gestion/reportes/control-interno/ventas/gestion-estado/${props.ventaId}/detalle`)
        
        if (response.data.success) {
            venta.value = response.data.venta
            detalles.value = response.data.detalles || []
            console.log('📦 Detalles cargados:', detalles.value)
        } else {
            toast?.error('Error', response.data.message || 'Error al cargar el detalle')
            cerrar()
        }
    } catch (error) {
        console.error('Error cargando detalle:', error)
        toast?.error('Error', 'Error al cargar el detalle de la factura')
        cerrar()
    } finally {
        loading.value = false
    }
}

// =============================================
// ACTIVAR FACTURA
// =============================================
const activarFactura = async () => {
    if (!venta.value) return
    
    if (!confirm('¿Estás seguro de activar esta factura?\n\nAl activarla, el operador podrá editarla.')) {
        return
    }
    
    activando.value = true
    
    try {
        const response = await fetch(`/gestion/reportes/control-interno/ventas/${venta.value.IdVentas}/cambiar-estado`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                estado: 0
            })
        })
        
        const data = await response.json()
        
        if (data.success) {
            toast?.success('Factura activada', 'La factura se ha activado correctamente. El operador ahora puede editarla.')
            emit('activado', venta.value.IdVentas)
            cerrar()
        } else {
            toast?.error('Error', data.message || 'Error al activar')
        }
    } catch (error) {
        console.error('Error activando:', error)
        toast?.error('Error', 'Error al activar la factura')
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
}

// =============================================
// WATCH - Cuando se abre el modal
// =============================================
watch(() => props.visible, (nuevoValor) => {
    if (nuevoValor && props.ventaId) {
        cargarDetalle()
    }
})
</script>

<template>
    <Teleport to="body">
        <div v-if="visible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="cerrar">
            <div class="bg-white rounded-xl max-w-3xl w-full max-h-[90vh] overflow-hidden shadow-xl flex flex-col">
                
                <!-- Header -->
                <div class="bg-primary-700 px-4 py-2.5 flex justify-between items-center flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-white rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-invoice text-primary-600 text-xs"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-sm">Detalle de Factura</h3>
                            <p class="text-white/70 text-[10px]">
                                <span v-if="venta">N° {{ venta.NumeroFactura }} - {{ formatearFecha(venta.FechaVenta) }}</span>
                                <span v-else class="text-white/50">Cargando...</span>
                            </p>
                        </div>
                    </div>
                    <button @click="cerrar" class="text-white/80 hover:text-white transition text-sm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Cargando -->
                <div v-if="loading" class="flex-1 flex items-center justify-center p-8">
                    <div class="text-center text-gray-400">
                        <i class="fas fa-spinner fa-spin text-3xl mb-2 block"></i>
                        <p class="text-sm">Cargando detalle de la factura...</p>
                    </div>
                </div>

                <!-- Contenido -->
                <div v-else-if="venta" class="flex-1 overflow-y-auto p-4">
                    
                    <!-- Info de la venta -->
                    <div class="bg-gray-50 rounded-lg p-3 mb-4 grid grid-cols-2 sm:grid-cols-5 gap-2 text-xs">
                        <div>
                            <span class="text-gray-500 block">Sucursal</span>
                            <span class="font-medium">{{ venta.sucursal_nombre || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Vendedor</span>
                            <span class="font-medium">{{ venta.vendedor_nombre || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Estado</span>
                            <span class="px-2 py-0.5 text-[10px] rounded-full inline-block" :class="getEstadoColor(venta.ActivoInactivo)">
                                {{ getEstadoTexto(venta.ActivoInactivo) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Fecha</span>
                            <span class="font-medium">{{ formatearFecha(venta.FechaVenta) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Ticket Día</span>
                            <span class="font-medium">{{ venta.TicketDia || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Lugar Venta</span>
                            <span class="font-medium">{{ venta.LugarVenta || 'Mostrador' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Liquidado</span>
                            <span class="font-medium">{{ venta.LiquidadoVendedor > 0 ? '✅ Sí' : '❌ No' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Total</span>
                            <span class="font-bold text-primary-600">{{ formatearMonto(totalFactura) }} Bs</span>
                        </div>
                    </div>

                    <!-- Productos -->
                    <div class="border rounded-lg overflow-hidden">
                        <div class="bg-gray-50 px-3 py-2 border-b flex justify-between items-center text-xs font-medium text-gray-600">
                            <span class="flex-1">Producto</span>
                            <span class="w-12 text-center">Cant.</span>
                            <span class="w-16 text-right">Precio</span>
                            <span class="w-16 text-right">Total</span>
                        </div>
                        
                        <div class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
                            <div v-for="detalle in detalles" :key="detalle.IdDetalleVenta" class="px-3 py-2 hover:bg-gray-50">
                                
                                <!-- CABECERA DEL PRODUCTO -->
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-gray-800 text-sm">{{ detalle.producto_nombre || 'Producto' }}</span>
                                            <span v-if="detalle.producto_codigo" class="text-[10px] text-gray-400">({{ detalle.producto_codigo }})</span>
                                        </div>
                                    </div>
                                    <div class="flex gap-2 text-right flex-shrink-0 ml-4">
                                        <span class="w-12 text-center text-gray-700">{{ detalle.unidades }}</span>
                                        <span class="w-16 text-right text-gray-700">{{ formatearMonto(detalle.preciounidades) }}</span>
                                        <span class="w-16 text-right font-bold text-primary-600">{{ formatearMonto(detalle.totalbolivianos) }}</span>
                                    </div>
                                </div>

                                <!-- 🔥 DETALLE DE PRODUCTOS (ORIGINALES Y SUSTITUTOS) -->
                                <div v-if="detalle.productos_detalle && detalle.productos_detalle.length > 0" class="mt-1.5 pl-3 border-l-2 border-amber-300">
                                    <div class="text-amber-600 font-medium text-[9px] mb-0.5">PRODUCTO DETALLE</div>
                                    <div v-for="item in detalle.productos_detalle" :key="`${item.id_producto}-${item.tipo}`" class="flex items-center gap-1 text-gray-700 text-[10px]">
                                        <span class="text-gray-400">{{ item.icon }}</span>
                                        <span :class="item.color">{{ item.nombre }}</span>
                                        <span class="text-gray-500 font-medium">x{{ item.cantidad }}</span>
                                        <span v-if="item.tipo === 'sustituto'" class="text-gray-400 text-[9px]">(reemplaza al original)</span>
                                        <span v-else class="text-gray-400 text-[9px]">(original)</span>
                                    </div>
                                </div>

                                <!-- 🔥 COMPOSICIÓN ORIGINAL (si no tiene personalización) -->
                                <div v-else-if="detalle.es_agrupado && detalle.composicion && detalle.composicion.length > 0" class="mt-1.5 pl-3 border-l-2 border-blue-200">
                                    <div class="text-blue-600 font-medium text-[9px] mb-0.5">PRODUCTO DETALLE</div>
                                    <div v-for="comp in detalle.composicion" :key="comp.id_producto" class="flex items-center gap-1 text-gray-700 text-[10px]">
                                        <span class="text-gray-400">•</span>
                                        <span>{{ comp.nombre }}</span>
                                        <span class="text-gray-500 font-medium">x{{ comp.cantidad_real || comp.porcion || 1 }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sin productos -->
                            <div v-if="!detalles || !detalles.length" class="px-3 py-4 text-center text-gray-400 text-xs">
                                No hay productos en esta factura
                            </div>
                        </div>
                        
                        <!-- Total -->
                        <div class="bg-gray-50 px-3 py-2 border-t flex justify-end text-xs font-bold">
                            <span class="text-gray-700 mr-4">TOTAL:</span>
                            <span class="text-primary-700 text-sm">{{ formatearMonto(totalFactura) }} Bs</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-4 py-2.5 border-t flex justify-end gap-2 flex-shrink-0">
                    <button @click="cerrar" class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg text-xs hover:bg-gray-100 transition">
                        Cerrar
                    </button>
                    <button 
                        v-if="venta && venta.ActivoInactivo === 1 && venta.LiquidadoVendedor === 0"
                        @click="activarFactura"
                        :disabled="activando"
                        class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-medium transition disabled:opacity-50"
                    >
                        <i v-if="activando" class="fas fa-spinner fa-spin mr-1"></i>
                        <i v-else class="fas fa-check-circle mr-1"></i>
                        {{ activando ? 'Activando...' : 'Activar Factura' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.max-h-80 {
    max-height: 320px;
}

.max-h-80::-webkit-scrollbar {
    width: 4px;
}
.max-h-80::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.max-h-80::-webkit-scrollbar-track {
    background: #f1f5f9;
}
</style>