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
            
            // 🔥 TOAST DE ÉXITO
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
            
            // 🔥 TOAST DE ERROR
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
        
        // 🔥 TOAST DE ERROR
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
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 py-6">
            
            <!-- ============================================= -->
            <!-- ENCABEZADO -->
            <!-- ============================================= -->
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <div class="flex items-center gap-3">
                    <button 
                        @click="volver"
                        class="text-gray-400 hover:text-gray-600 transition"
                        title="Volver al listado"
                    >
                        <i class="fas fa-arrow-left text-xl"></i>
                    </button>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Edita – Ventas</h1>
                        <p class="text-xs text-gray-500">
                            Venta N° {{ venta?.NumeroFactura || 'Sin número' }}
                            <span 
                                :class="['ml-2 inline-flex px-2 py-0.5 text-xs font-medium rounded-full', getEstadoColor(venta?.IdEstado)]"
                            >
                                {{ getEstadoNombre(venta?.IdEstado) }}
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="flex flex-wrap gap-2">
                    <!-- 🔥 BOTÓN GUARDAR FECHA -->
                    <button 
                        @click="guardarFecha"
                        :disabled="guardando"
                        class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 disabled:opacity-50"
                    >
                        <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ guardando ? 'Guardando...' : 'Guardar' }}
                    </button>
                    
                    <!-- 🔥 BOTÓN REPROCESAR INVENTARIO -->
                    <button 
                        @click="abrirModalConfirmacion"
                        :disabled="reprocesando"
                        class="bg-amber-500 hover:bg-amber-600 text-white px-5 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 disabled:opacity-50"
                        title="Reprocesar inventario"
                    >
                        <i v-if="reprocesando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-boxes"></i>
                        {{ reprocesando ? 'Reprocesando...' : 'Reprocesa - Inventario' }}
                    </button>
                </div>
            </div>

            <!-- ============================================= -->
            <!-- FILA 1: 5 CAMPOS -->
            <!-- ============================================= -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-3">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                    
                    <!-- Fecha Venta (EDITABLE) -->
                    <div>
                        <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1">Fecha Venta</label>
                        <input 
                            v-model="fechaVenta"
                            type="date"
                            class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
                        />
                    </div>
                    
                    <!-- N° Factura (SOLO LECTURA) -->
                    <div>
                        <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1">N° Factura</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700">
                            {{ venta?.NumeroFactura || '-' }}
                        </div>
                    </div>
                    
                    <!-- Importe Venta (SOLO LECTURA) -->
                    <div>
                        <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1">Importe Venta</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm font-bold text-primary-700">
                            {{ Number(venta?.ImporteVenta || 0).toFixed(2) }}
                        </div>
                    </div>
                    
                    <!-- Importe Excento (SOLO LECTURA) -->
                    <div>
                        <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1">Importe Excento</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700">
                            {{ Number(venta?.ImporteExcento || 0).toFixed(2) }}
                        </div>
                    </div>
                    
                    <!-- N° Autorización (SOLO LECTURA) -->
                    <div>
                        <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1">N° Autorización</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700 truncate">
                            {{ venta?.NumeroAutorizacion || '-' }}
                        </div>
                    </div>
                    
                </div>
            </div>

            <!-- ============================================= -->
            <!-- FILA 2: 5 CAMPOS -->
            <!-- ============================================= -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                    
                    <!-- Importe Exportaciones (SOLO LECTURA) -->
                    <div>
                        <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1">Importe Exportaciones</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700">
                            {{ Number(venta?.ImporteExportaciones || 0).toFixed(2) }}
                        </div>
                    </div>
                    
                    <!-- Importe Tasa Cero (SOLO LECTURA) -->
                    <div>
                        <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1">Importe Tasa Cero</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700">
                            {{ Number(venta?.ImporteTasaCero || 0).toFixed(2) }}
                        </div>
                    </div>
                    
                    <!-- Importe Descuentos (SOLO LECTURA) -->
                    <div>
                        <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1">Importe Descuentos</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700">
                            {{ Number(venta?.ImporteDescuentos || 0).toFixed(2) }}
                        </div>
                    </div>
                    
                    <!-- Código Control (SOLO LECTURA) -->
                    <div>
                        <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1">Código Control</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700 truncate">
                            {{ venta?.CodigoControl || '-' }}
                        </div>
                    </div>
                    
                    <!-- Estado (SOLO LECTURA) -->
                    <div>
                        <label class="block text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1">Estado</label>
                        <div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700">
                            <span :class="['inline-flex px-2 py-0.5 text-xs font-medium rounded-full', getEstadoColor(venta?.IdEstado)]">
                                {{ getEstadoNombre(venta?.IdEstado) }}
                            </span>
                        </div>
                    </div>
                    
                </div>
            </div>

            <!-- ============================================= -->
            <!-- DETALLE DE PRODUCTOS (SOLO LECTURA) -->
            <!-- ============================================= -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold text-gray-700 text-sm">Detalle</h2>
                    <span class="text-xs text-gray-500">{{ venta?.detalles?.length || 0 }} productos</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-[10px] font-medium text-gray-500 uppercase">Código</th>
                                <th class="px-3 py-2 text-left text-[10px] font-medium text-gray-500 uppercase">Producto</th>
                                <th class="px-3 py-2 text-right text-[10px] font-medium text-gray-500 uppercase">Unidades</th>
                                <th class="px-3 py-2 text-right text-[10px] font-medium text-gray-500 uppercase">Precio</th>
                                <th class="px-3 py-2 text-right text-[10px] font-medium text-gray-500 uppercase">Bolivianos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(detalle, index) in (venta?.detalles || [])" :key="index" class="hover:bg-gray-50 transition">
                                <td class="px-3 py-2.5 text-sm text-gray-600">
                                    {{ detalle.producto_codigo || '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">
                                    {{ detalle.producto_nombre || 'Producto' }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-right font-medium">
                                    {{ Number(detalle.unidades || 0).toFixed(2) }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-right">
                                    {{ Number(detalle.preciounidades || 0).toFixed(2) }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-right font-bold text-primary-700">
                                    {{ Number(detalle.totalbolivianos || 0).toFixed(2) }}
                                </td>
                            </tr>
                            
                            <!-- TOTAL -->
                            <tr class="bg-gray-50 font-bold border-t-2 border-gray-300">
                                <td colspan="4" class="px-3 py-2.5 text-right text-sm text-gray-700">
                                    TOTAL:
                                </td>
                                <td class="px-3 py-2.5 text-right text-sm text-primary-700">
                                    {{ Number(totalVenta).toFixed(2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================= -->
            <!-- PIE DE PÁGINA -->
            <!-- ============================================= -->
            <div class="mt-4 flex justify-end">
                <button 
                    @click="volver"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-sm"
                >
                    <i class="fas fa-arrow-left mr-1"></i>
                    Volver al listado
                </button>
            </div>

            <!-- ============================================= -->
            <!-- MODALES -->
            <!-- ============================================= -->
            
            <!-- Modal de Confirmación -->
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

            <!-- Modal de Resultado -->
            <ModalResultadoReprocesar
                :visible="mostrarModalResultado"
                :exito="resultadoExito"
                :mensaje="resultadoMensaje"
                :fecha="resultadoFecha"
                :detalles="resultadoDetalles"
                @close="cerrarModalResultado"
            />
        </div>
    </div>
</template>