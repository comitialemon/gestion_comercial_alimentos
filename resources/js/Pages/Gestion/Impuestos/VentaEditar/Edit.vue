<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, inject } from 'vue'
import axios from 'axios'
import ModalConfirmacion from './components/ModalConfirmacion.vue'
import ModalResultadoReprocesar from './components/ModalResultadoReprocesar.vue'

defineOptions({ layout: AppLayout })

// 🔥 INYECTAR TOAST
const toast = inject('toast')

const props = defineProps({
    venta: Object,
    productos: Array,
    estados: Array,
})

// =============================================
// ESTADO DEL FORMULARIO
// =============================================
const reprocesando = ref(false)
const guardando = ref(false)

// 🔥 ESTADO DE MODALES
const mostrarModalConfirmacion = ref(false)
const mostrarModalResultado = ref(false)

// 🔥 RESULTADO DEL REPROCESAR
const resultadoExito = ref(true)
const resultadoMensaje = ref('')
const resultadoFecha = ref('')
const resultadoDetalles = ref({
    eliminados: 0,
    insertados: 0,
    fecha_anterior: '',
    fecha_nueva: '',
    productos: []
})

// 🔥 SOLO LA FECHA ES EDITABLE
const fechaVenta = ref(props.venta?.FechaVenta ? props.venta.FechaVenta.slice(0, 10) : '')

// =============================================
// COMPUTADOS
// =============================================
const totalVenta = computed(() => {
    if (!props.venta?.detalles) return 0
    return props.venta.detalles.reduce((sum, item) => {
        return sum + (parseFloat(item.totalbolivianos) || 0)
    }, 0)
})

const getEstadoColor = (estadoId) => {
    const colors = {
        1: 'bg-green-100 text-green-800',
        2: 'bg-red-100 text-red-800',
        3: 'bg-yellow-100 text-yellow-800',
        4: 'bg-gray-100 text-gray-800',
        5: 'bg-orange-100 text-orange-800',
        6: 'bg-blue-100 text-blue-800',
    }
    return colors[estadoId] || 'bg-gray-100 text-gray-800'
}

const getEstadoNombre = (estadoId) => {
    const nombres = {
        1: 'VÁLIDA',
        2: 'ANULADA',
        3: 'EXTRAVIADA',
        4: 'NO UTILIZADA',
        5: 'CONTINGENCIA',
        6: 'LIBRE CONSIGNACION',
    }
    return nombres[estadoId] || 'Desconocido'
}

// =============================================
// MÉTODOS
// =============================================

const volver = () => {
    router.get('/gestion/ventas-editar')
}

// 🔥 GUARDAR SOLO LA FECHA - CON TOAST
const guardarFecha = async () => {
    if (!fechaVenta.value) {
        toast?.warning('⚠️ Fecha requerida', 'La fecha de venta es obligatoria')
        return
    }
    
    guardando.value = true
    
    try {
        const response = await axios.put(`/gestion/ventas-editar/${props.venta.IdVentas}`, {
            FechaVenta: fechaVenta.value
        })
        
        if (response.data.success) {
            toast?.success('✅ Éxito', response.data.message || 'Fecha actualizada correctamente')
        } else {
            toast?.error('❌ Error', response.data.message || 'Error al guardar la fecha')
        }
    } catch (error) {
        console.error('Error:', error)
        const mensaje = error.response?.data?.message || 'Error al guardar la fecha'
        toast?.error('❌ Error', mensaje)
    } finally {
        guardando.value = false
    }
}

// 🔥 ABRIR MODAL DE CONFIRMACIÓN PARA REPROCESAR
const abrirModalConfirmacion = () => {
    mostrarModalConfirmacion.value = true
}

// 🔥 EJECUTAR REPROCESAR - CON TOAST
const ejecutarReprocesar = async () => {
    reprocesando.value = true
    mostrarModalConfirmacion.value = false
    
    try {
        const response = await axios.post(`/gestion/ventas-editar/${props.venta.IdVentas}/reprocesar`)
        
        if (response.data.success) {
            resultadoExito.value = true
            resultadoMensaje.value = response.data.message || '✅ La venta fue reprocesada correctamente'
            resultadoFecha.value = response.data.fecha || ''
            resultadoDetalles.value = {
                eliminados: response.data.detalles?.eliminados || 0,
                insertados: response.data.detalles?.insertados || 0,
                fecha_anterior: response.data.detalles?.fecha_anterior || '',
                fecha_nueva: response.data.detalles?.fecha_nueva || '',
                productos: response.data.detalles?.productos || []
            }
            
            toast?.success('✅ Éxito', `Reprocesado correctamente. ${response.data.movimientos || 0} movimientos creados.`)
            
        } else {
            resultadoExito.value = false
            resultadoMensaje.value = response.data.message || '❌ Error al reprocesar'
            resultadoFecha.value = ''
            resultadoDetalles.value = {
                eliminados: 0,
                insertados: 0,
                fecha_anterior: '',
                fecha_nueva: '',
                productos: []
            }
            
            toast?.error('❌ Error', response.data.message || 'Error al reprocesar')
        }
        
        mostrarModalResultado.value = true
        
    } catch (error) {
        console.error('Error:', error)
        const mensaje = error.response?.data?.message || 'Error al reprocesar'
        
        resultadoExito.value = false
        resultadoMensaje.value = mensaje
        resultadoFecha.value = ''
        resultadoDetalles.value = {
            eliminados: 0,
            insertados: 0,
            fecha_anterior: '',
            fecha_nueva: '',
            productos: []
        }
        
        toast?.error('❌ Error', mensaje)
        
        mostrarModalResultado.value = true
    } finally {
        reprocesando.value = false
    }
}

// 🔥 CERRAR MODAL DE RESULTADO
const cerrarModalResultado = () => {
    mostrarModalResultado.value = false
    if (resultadoExito.value) {
        router.reload()
    }
}

// Formatear fecha
const formatDate = (date) => {
    if (!date) return '-'
    const d = new Date(date)
    return d.toLocaleDateString('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}
</script>

<template>
    <div class="min-h-screen" :style="{ backgroundColor: 'var(--color-primary-50, #f0f9ff)' }">
        <div class="max-w-6xl mx-auto px-3 sm:px-4 py-3 sm:py-6">
            
            <!-- ============================================= -->
            <!-- ENCABEZADO - RESPONSIVE -->
            <!-- ============================================= -->
            <div class="flex flex-col sm:flex-row sm:flex-wrap items-start sm:items-center justify-between gap-3 mb-4 sm:mb-6">
                <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
                    <button 
                        @click="volver"
                        class="text-gray-400 hover:text-gray-600 transition flex-shrink-0"
                        title="Volver al listado"
                    >
                        <i class="fas fa-arrow-left text-lg sm:text-xl"></i>
                    </button>
                    <div class="min-w-0 flex-1">
                        <h1 class="text-lg sm:text-2xl font-bold text-gray-800 truncate">Edita – Ventas</h1>
                        <p class="text-[10px] sm:text-xs text-gray-500 truncate">
                            Venta N° {{ venta?.NumeroFactura || 'Sin número' }}
                            <span 
                                :class="['ml-1 sm:ml-2 inline-flex px-1.5 sm:px-2 py-0.5 text-[9px] sm:text-xs font-medium rounded-full', getEstadoColor(venta?.IdEstado)]"
                            >
                                {{ getEstadoNombre(venta?.IdEstado) }}
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="flex flex-wrap gap-1.5 sm:gap-2 w-full sm:w-auto">
                    <!-- 🔥 BOTÓN GUARDAR -->
                    <button 
                        @click="guardarFecha"
                        :disabled="guardando"
                        class="flex-1 sm:flex-none bg-green-600 hover:bg-green-700 text-white px-3 sm:px-5 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition flex items-center justify-center gap-1.5 disabled:opacity-50"
                    >
                        <i v-if="guardando" class="fas fa-spinner fa-spin text-xs sm:text-sm"></i>
                        <i v-else class="fas fa-save text-xs sm:text-sm"></i>
                        <span class="hidden xs:inline">{{ guardando ? 'Guardando...' : 'Guardar' }}</span>
                        <span class="xs:hidden">{{ guardando ? '...' : 'Guardar' }}</span>
                    </button>
                    
                    <!-- 🔥 BOTÓN REPROCESAR -->
                    <button 
                        @click="abrirModalConfirmacion"
                        :disabled="reprocesando"
                        class="flex-1 sm:flex-none bg-amber-500 hover:bg-amber-600 text-white px-3 sm:px-5 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition flex items-center justify-center gap-1.5 disabled:opacity-50"
                        title="Reprocesar inventario"
                    >
                        <i v-if="reprocesando" class="fas fa-spinner fa-spin text-xs sm:text-sm"></i>
                        <i v-else class="fas fa-boxes text-xs sm:text-sm"></i>
                        <span class="hidden xs:inline">{{ reprocesando ? 'Reprocesando...' : 'Reprocesa - Inventario' }}</span>
                        <span class="xs:hidden">Reprocesar</span>
                    </button>
                </div>
            </div>

            <!-- ============================================= -->
            <!-- FILA 1: 5 CAMPOS - RESPONSIVE -->
            <!-- ============================================= -->
            <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-2 sm:mb-3">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2 sm:gap-3">
                    
                    <!-- Fecha Venta (EDITABLE) -->
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-0.5">Fecha Venta</label>
                        <input 
                            v-model="fechaVenta"
                            type="date"
                            class="w-full border border-gray-300 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                        />
                    </div>
                    
                    <!-- N° Factura -->
                    <div>
                        <label class="block text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-0.5">N° Factura</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-700 truncate">
                            {{ venta?.NumeroFactura || '-' }}
                        </div>
                    </div>
                    
                    <!-- Importe Venta -->
                    <div>
                        <label class="block text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-0.5">Importe Venta</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-bold" :style="{ color: 'var(--color-primary, #61131a)' }">
                            {{ Number(venta?.ImporteVenta || 0).toFixed(2) }}
                        </div>
                    </div>
                    
                    <!-- Importe Excento -->
                    <div>
                        <label class="block text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-0.5">Importe Excento</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-700">
                            {{ Number(venta?.ImporteExcento || 0).toFixed(2) }}
                        </div>
                    </div>
                    
                    <!-- N° Autorización -->
                    <div>
                        <label class="block text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-0.5">N° Autorización</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-700 truncate">
                            {{ venta?.NumeroAutorizacion || '-' }}
                        </div>
                    </div>
                    
                </div>
            </div>

            <!-- ============================================= -->
            <!-- FILA 2: 5 CAMPOS - RESPONSIVE -->
            <!-- ============================================= -->
            <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-3 sm:mb-4">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2 sm:gap-3">
                    
                    <!-- Importe Exportaciones -->
                    <div>
                        <label class="block text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-0.5">Imp. Export.</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-700">
                            {{ Number(venta?.ImporteExportaciones || 0).toFixed(2) }}
                        </div>
                    </div>
                    
                    <!-- Importe Tasa Cero -->
                    <div>
                        <label class="block text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-0.5">Tasa Cero</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-700">
                            {{ Number(venta?.ImporteTasaCero || 0).toFixed(2) }}
                        </div>
                    </div>
                    
                    <!-- Importe Descuentos -->
                    <div>
                        <label class="block text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-0.5">Descuentos</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-700">
                            {{ Number(venta?.ImporteDescuentos || 0).toFixed(2) }}
                        </div>
                    </div>
                    
                    <!-- Código Control -->
                    <div>
                        <label class="block text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-0.5">Código Control</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-700 truncate">
                            {{ venta?.CodigoControl || '-' }}
                        </div>
                    </div>
                    
                    <!-- Estado -->
                    <div>
                        <label class="block text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-0.5">Estado</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2 sm:px-3 py-1.5 text-xs sm:text-sm text-gray-700">
                            <span :class="['inline-flex px-1.5 sm:px-2 py-0.5 text-[8px] sm:text-xs font-medium rounded-full', getEstadoColor(venta?.IdEstado)]">
                                {{ getEstadoNombre(venta?.IdEstado) }}
                            </span>
                        </div>
                    </div>
                    
                </div>
            </div>

            <!-- ============================================= -->
            <!-- DETALLE DE PRODUCTOS - RESPONSIVE -->
            <!-- ============================================= -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-3 sm:px-4 py-2 sm:py-2.5 bg-gray-50 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold text-gray-700 text-xs sm:text-sm flex items-center gap-2">
                        <i class="fas fa-list text-gray-400"></i>
                        Detalle
                    </h2>
                    <span class="text-[9px] sm:text-xs text-gray-500">{{ venta?.detalles?.length || 0 }} productos</span>
                </div>
                
                <div class="overflow-x-auto">
                    <!-- ==================== VISTA DESKTOP (tabla completa) ==================== -->
                    <table class="hidden sm:table min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-left text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase">Código</th>
                                <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-left text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase">Producto</th>
                                <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-right text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase">Unidades</th>
                                <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-right text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase">Precio</th>
                                <th class="px-2 sm:px-3 py-1.5 sm:py-2 text-right text-[8px] sm:text-[10px] font-medium text-gray-500 uppercase">Bolivianos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(detalle, index) in (venta?.detalles || [])" :key="index" class="hover:bg-gray-50 transition">
                                <td class="px-2 sm:px-3 py-2 sm:py-2.5 text-xs sm:text-sm text-gray-600">
                                    {{ detalle.producto_codigo || '-' }}
                                </td>
                                <td class="px-2 sm:px-3 py-2 sm:py-2.5 text-xs sm:text-sm text-gray-700">
                                    {{ detalle.producto_nombre || 'Producto' }}
                                </td>
                                <td class="px-2 sm:px-3 py-2 sm:py-2.5 text-xs sm:text-sm text-right font-medium">
                                    {{ Number(detalle.unidades || 0).toFixed(2) }}
                                </td>
                                <td class="px-2 sm:px-3 py-2 sm:py-2.5 text-xs sm:text-sm text-right">
                                    {{ Number(detalle.preciounidades || 0).toFixed(2) }}
                                </td>
                                <td class="px-2 sm:px-3 py-2 sm:py-2.5 text-xs sm:text-sm text-right font-bold" :style="{ color: 'var(--color-primary, #61131a)' }">
                                    {{ Number(detalle.totalbolivianos || 0).toFixed(2) }}
                                </td>
                            </tr>
                            
                            <tr class="bg-gray-50 font-bold border-t-2 border-gray-300">
                                <td colspan="4" class="px-2 sm:px-3 py-2 sm:py-2.5 text-right text-xs sm:text-sm text-gray-700">
                                    TOTAL:
                                </td>
                                <td class="px-2 sm:px-3 py-2 sm:py-2.5 text-right text-xs sm:text-sm font-bold" :style="{ color: 'var(--color-primary, #61131a)' }">
                                    {{ Number(totalVenta).toFixed(2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- ==================== VISTA MÓVIL (tarjetas) ==================== -->
                    <div class="sm:hidden divide-y divide-gray-100">
                        <div v-for="(detalle, index) in (venta?.detalles || [])" :key="index" class="p-3 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-[10px] font-medium text-gray-400">{{ detalle.producto_codigo || '-' }}</span>
                                        <span class="text-xs font-medium text-gray-700 truncate">{{ detalle.producto_nombre || 'Producto' }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-3 mt-1 text-[10px] text-gray-500">
                                        <span><span class="text-gray-400">Unid:</span> {{ Number(detalle.unidades || 0).toFixed(2) }}</span>
                                        <span><span class="text-gray-400">Precio:</span> {{ Number(detalle.preciounidades || 0).toFixed(2) }}</span>
                                        <span class="font-bold" :style="{ color: 'var(--color-primary, #61131a)' }">
                                            {{ Number(detalle.totalbolivianos || 0).toFixed(2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- TOTAL MÓVIL -->
                        <div class="p-3 bg-gray-50 font-bold border-t-2 border-gray-300 flex justify-between items-center">
                            <span class="text-sm text-gray-700">TOTAL:</span>
                            <span class="text-sm font-bold" :style="{ color: 'var(--color-primary, #61131a)' }">
                                {{ Number(totalVenta).toFixed(2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================= -->
            <!-- PIE DE PÁGINA - RESPONSIVE -->
            <!-- ============================================= -->
            <div class="mt-3 sm:mt-4 flex justify-end">
                <button 
                    @click="volver"
                    class="px-3 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-xs sm:text-sm flex items-center gap-1.5"
                >
                    <i class="fas fa-arrow-left text-[10px] sm:text-xs"></i>
                    <span class="hidden xs:inline">Volver al listado</span>
                    <span class="xs:hidden">Volver</span>
                </button>
            </div>

            <!-- ============================================= -->
            <!-- MODALES -->
            <!-- ============================================= -->
            
            <ModalConfirmacion
                :visible="mostrarModalConfirmacion"
                titulo="⚠️ Reprocesar Inventario"
                mensaje="Se eliminarán todos los movimientos de inventario anteriores y se crearán nuevos según los productos actuales de la venta."
                detalle="Esta acción no se puede deshacer. Asegúrate de que los datos de la venta sean correctos."
                botonConfirmar="Reprocesar"
                :cargando="reprocesando"
                @confirm="ejecutarReprocesar"
                @close="mostrarModalConfirmacion = false"
            />

            <ModalResultadoReprocesar
                :visible="mostrarModalResultado"
                :exito="resultadoExito"
                :mensaje="resultadoMensaje"
                :fecha="resultadoFecha"
                :detalles="resultadoDetalles"
                @close="cerrarModalResultado"
            />

            <!-- ============================================= -->
            <!-- OVERLAYS DE CARGA -->
            <!-- ============================================= -->
            
            <div v-if="reprocesando" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-xl p-5 sm:p-6 flex flex-col items-center gap-3 sm:gap-4 shadow-xl max-w-xs sm:max-w-sm w-full mx-3">
                    <i class="fas fa-spinner fa-spin text-2xl sm:text-3xl text-amber-500"></i>
                    <p class="text-sm sm:text-base text-gray-700 font-medium text-center">Reprocesando inventario...</p>
                    <p class="text-[10px] sm:text-xs text-gray-400 text-center">Eliminando movimientos anteriores<br class="hidden sm:block">y creando nuevos según los datos actuales</p>
                </div>
            </div>

            <div v-if="guardando" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-40">
                <div class="bg-white rounded-xl p-4 sm:p-5 flex items-center gap-2 sm:gap-3 shadow-xl">
                    <i class="fas fa-spinner fa-spin text-lg sm:text-xl text-green-600"></i>
                    <span class="text-xs sm:text-sm text-gray-700">Guardando fecha...</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* 🔥 Breakpoint personalizado para pantallas muy pequeñas */
@media (max-width: 480px) {
    .xs\:inline {
        display: inline !important;
    }
    .xs\:hidden {
        display: none !important;
    }
}

/* 🔥 Mejorar scroll en móvil */
@media (max-width: 640px) {
    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
    }
}
</style>