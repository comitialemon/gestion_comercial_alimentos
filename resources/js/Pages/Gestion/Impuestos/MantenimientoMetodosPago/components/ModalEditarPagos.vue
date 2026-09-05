<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    venta: Object,
})

const emit = defineEmits(['update:modelValue', 'actualizado'])

// ==================== DETECTAR DISPOSITIVO ====================
const isMobile = ref(false)
const isTablet = ref(false)

const handleResize = () => {
    const width = window.innerWidth
    isMobile.value = width < 640
    isTablet.value = width >= 640 && width < 1024
}

// ==================== ESTADO ====================
const modalOpen = ref(props.modelValue)
const metodosPago = ref([])
const conceptos = ref([])
const totalVenta = ref(0)
const numeroFactura = ref('')
const cargando = ref(false)
const guardando = ref(false)
const clicProcesado = ref(false)
const ultimoClickTimestamp = ref(0)
const errors = ref({})
const errorMontos = ref('')

// ==================== TOAST ====================
const toastVisible = ref(false)
const toastMensaje = ref('')
const toastTipo = ref('success')

const mostrarToast = (mensaje, tipo = 'success') => {
    toastMensaje.value = mensaje
    toastTipo.value = tipo
    toastVisible.value = true
    setTimeout(() => {
        toastVisible.value = false
    }, 4000)
}

// ==================== COMPUTED ====================
const totalMontos = computed(() => {
    return metodosPago.value.reduce((sum, pago) => sum + (Number(pago.Bolivianos) || 0), 0)
})

const diferencia = computed(() => {
    return totalVenta.value - totalMontos.value
})

const montosIguales = computed(() => {
    return Math.abs(diferencia.value) <= 0.01
})

const tieneMontosCero = computed(() => {
    return metodosPago.value.some(pago => Number(pago.Bolivianos) === 0)
})

const puedeGuardar = computed(() => {
    return montosIguales.value && totalMontos.value > 0 && !tieneMontosCero.value && !guardando.value && !clicProcesado.value
})

// ==================== FUNCIONES ====================
const conceptosSeleccionados = (indexActual) => {
    return metodosPago.value
        .filter((_, i) => i !== indexActual)
        .map(p => Number(p.IdCuenta))
        .filter(id => id > 0)
}

const conceptosDisponibles = (indexActual) => {
    const seleccionados = conceptosSeleccionados(indexActual)
    return conceptos.value.filter(c => !seleccionados.includes(Number(c.IdCuenta)))
}

const conceptoYaSeleccionado = (idCuenta, indexActual) => {
    if (!idCuenta) return false
    const seleccionados = conceptosSeleccionados(indexActual)
    return seleccionados.includes(Number(idCuenta))
}

const cargarMetodosPago = async () => {
    if (!props.venta?.IdVentas) return
    
    cargando.value = true
    errorMontos.value = ''
    try {
        const response = await axios.get(`/gestion/impuestos/mantenimiento-metodos-pago/${props.venta.IdVentas}/metodos-pago`)
        
        if (response.data.success) {
            metodosPago.value = response.data.metodosPago.map(p => ({
                ...p,
                _idCuentaAnterior: p.IdCuenta
            }))
            conceptos.value = response.data.conceptos
            totalVenta.value = response.data.totalVenta
            numeroFactura.value = response.data.numeroFactura
            
            validarMontos()
        }
    } catch (error) {
        console.error('Error cargando métodos de pago:', error)
        mostrarToast('Error al cargar los métodos de pago', 'error')
    } finally {
        cargando.value = false
    }
}

const validarMontos = () => {
    const total = totalMontos.value
    const diff = total - totalVenta.value
    
    if (Math.abs(diff) > 0.01) {
        if (diff > 0) {
            errorMontos.value = `❌ EL TOTAL EXCEDE: ${formatearNumero(total)} Bs > ${formatearNumero(totalVenta.value)} Bs (exceso de ${formatearNumero(diff)} Bs)`
        } else {
            errorMontos.value = `❌ EL TOTAL ES MENOR: ${formatearNumero(total)} Bs < ${formatearNumero(totalVenta.value)} Bs (faltan ${formatearNumero(Math.abs(diff))} Bs)`
        }
    } else {
        errorMontos.value = total === totalVenta.value 
            ? '✅ Total correcto' 
            : `⚠️ Diferencia de ${formatearNumero(Math.abs(diff))} Bs (redondeo)`
    }
}

const validarMonto = (pago, index) => {
    if (pago.Bolivianos < 0) {
        pago.Bolivianos = 0
        mostrarToast('⚠️ No se permiten montos negativos', 'warning')
        validarMontos()
        return
    }

    const otrosMontos = metodosPago.value.reduce((sum, p, i) => {
        if (i !== index) return sum + (Number(p.Bolivianos) || 0)
        return sum
    }, 0)
    
    const maximoPermitido = totalVenta.value - otrosMontos
    
    if (pago.Bolivianos > maximoPermitido && maximoPermitido >= 0) {
        const valorOriginal = pago.Bolivianos
        pago.Bolivianos = Math.round(maximoPermitido * 100) / 100
        
        if (Math.abs(valorOriginal - pago.Bolivianos) > 0.01) {
            mostrarToast(
                `⚠️ El monto excede el total disponible. Se ajustó a ${formatearNumero(pago.Bolivianos)} Bs.`,
                'warning'
            )
        }
    }
    
    validarMontos()
}

const onCambiarConcepto = (pago, index, nuevoIdCuenta) => {
    if (conceptoYaSeleccionado(nuevoIdCuenta, index)) {
        mostrarToast('⚠️ Este método de pago ya está seleccionado en otra fila.', 'warning')
        pago.IdCuenta = pago._idCuentaAnterior || ''
        return
    }
    pago._idCuentaAnterior = pago.IdCuenta
    pago.IdCuenta = nuevoIdCuenta
}

const guardarCambios = async () => {
    const ahora = Date.now()
    if (guardando.value || clicProcesado.value) {
        mostrarToast('⚠️ Ya se está guardando, por favor espera...', 'warning')
        return
    }
    
    if (ahora - ultimoClickTimestamp.value < 500) {
        return
    }
    ultimoClickTimestamp.value = ahora

    const idsSeleccionados = metodosPago.value.map(p => Number(p.IdCuenta)).filter(id => id > 0)
    const idsUnicos = [...new Set(idsSeleccionados)]
    if (idsSeleccionados.length !== idsUnicos.length) {
        mostrarToast('❌ No se puede guardar. Hay métodos de pago duplicados.', 'error')
        return
    }

    if (!montosIguales.value) {
        const diff = totalMontos.value - totalVenta.value
        const mensaje = diff > 0 
            ? `El total EXCEDE en ${formatearNumero(diff)} Bs` 
            : `El total es MENOR en ${formatearNumero(Math.abs(diff))} Bs`
        
        mostrarToast(`❌ No se puede guardar. ${mensaje}. Los montos deben ser EXACTAMENTE IGUALES.`, 'error')
        return
    }
    
    if (totalMontos.value === 0) {
        mostrarToast('❌ Debe asignar al menos un método de pago con monto mayor a 0.', 'error')
        return
    }
    
    if (tieneMontosCero.value) {
        mostrarToast('❌ Todos los métodos de pago deben tener monto mayor a 0.', 'error')
        return
    }
    
    guardando.value = true
    clicProcesado.value = true
    errors.value = {}
    
    try {
        const response = await axios.put(`/gestion/impuestos/mantenimiento-metodos-pago/${props.venta.IdVentas}/metodos-pago`, {
            pagos: metodosPago.value
        })
        
        if (response.data.success) {
            mostrarToast('✅ Métodos de pago actualizados correctamente', 'success')
            setTimeout(() => {
                guardando.value = false
                clicProcesado.value = false
                cerrarModal()
                emit('actualizado')
            }, 800)
        } else {
            errors.value = { general: response.data.message }
            mostrarToast('❌ ' + response.data.message, 'error')
            guardando.value = false
            clicProcesado.value = false
        }
    } catch (error) {
        console.error('Error guardando:', error)
        const mensaje = error.response?.data?.message || 'Error al guardar'
        errors.value = { general: mensaje }
        mostrarToast('❌ ' + mensaje, 'error')
        guardando.value = false
        clicProcesado.value = false
    }
}

const cerrarModal = () => {
    if (guardando.value || clicProcesado.value) {
        mostrarToast('⚠️ Espera a que termine de guardar', 'warning')
        return
    }
    modalOpen.value = false
}

const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toFixed(2)
}

const agregarMetodo = () => {
    const hayVacio = metodosPago.value.some(p => !p.IdCuenta || p.IdCuenta === '')
    if (hayVacio) {
        mostrarToast('⚠️ Ya hay un método de pago sin seleccionar. Completa o elimina ese primero.', 'warning')
        return
    }
    
    const conceptosSeleccionadosIds = metodosPago.value.map(p => Number(p.IdCuenta)).filter(id => id > 0)
    const conceptosDisponiblesIds = conceptos.value.map(c => Number(c.IdCuenta))
    
    if (conceptosSeleccionadosIds.length >= conceptosDisponiblesIds.length) {
        mostrarToast('⚠️ Ya has seleccionado todos los métodos de pago disponibles.', 'warning')
        return
    }
    
    metodosPago.value.push({ 
        IdVentasLiquidacion: 0,
        IdCuenta: '',
        Bolivianos: 0,
        _idCuentaAnterior: null
    })
}

const eliminarMetodo = (index) => {
    const pago = metodosPago.value[index]
    
    if (pago.IdVentasLiquidacion > 0) {
        const concepto = conceptos.value.find(c => Number(c.IdCuenta) === Number(pago.IdCuenta))
        if (!confirm(`¿Eliminar "${concepto?.Concepto || 'método'}"?`)) {
            return
        }
    }
    
    metodosPago.value.splice(index, 1)
    validarMontos()
}

// ==================== WATCHERS ====================
watch(() => props.modelValue, (newVal) => {
    modalOpen.value = newVal
    if (newVal && props.venta) {
        cargarMetodosPago()
    }
})

watch(modalOpen, (newVal) => {
    emit('update:modelValue', newVal)
})

// ==================== LIFECYCLE ====================
onMounted(() => {
    handleResize()
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-3">
            <div 
                class="fixed inset-0 bg-black/50 transition-opacity" 
                @click="cerrarModal"
            ></div>
            
            <div class="relative bg-white rounded-xl shadow-xl max-w-3xl w-full mx-auto transform transition-all duration-300">
                <!-- ==================== HEADER ==================== -->
                <div class="flex items-center justify-between px-4 py-2.5 bg-primary-600 rounded-t-xl">
                    <h3 class="text-sm font-semibold text-white flex items-center gap-1.5">
                        <i class="fas fa-credit-card text-[10px]"></i> Modificar Métodos de Pago
                    </h3>
                    <button 
                        @click="cerrarModal" 
                        :disabled="guardando || clicProcesado"
                        class="text-white/80 hover:text-white transition disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                    >
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- ==================== BODY ==================== -->
                <div class="p-4">
                    <div v-if="cargando" class="flex justify-center py-10">
                        <i class="fas fa-spinner fa-spin text-primary-500 text-lg"></i>
                        <span class="ml-2 text-sm text-gray-500">Cargando...</span>
                    </div>

                    <div v-else>
                        <!-- Info factura -->
                        <div class="mb-3 p-2.5 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="font-medium text-gray-500">N° Factura:</span>
                                    <span class="ml-1 text-gray-800 font-mono font-semibold">{{ numeroFactura }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-500">Total Factura:</span>
                                    <span class="ml-1 text-primary-600 font-bold">{{ formatearNumero(totalVenta) }} Bs</span>
                                </div>
                                <div class="col-span-2">
                                    <span class="font-medium text-gray-500">Total Pagado:</span>
                                    <span class="ml-1 font-bold" :class="montosIguales ? 'text-emerald-600' : 'text-red-600'">
                                        {{ formatearNumero(totalMontos) }} Bs
                                    </span>
                                    <span v-if="!montosIguales" class="ml-1 text-[10px] text-red-500 font-bold">
                                        ({{ diferencia > 0 ? 'EXCEDE' : 'FALTA' }}: {{ formatearNumero(Math.abs(diferencia)) }} Bs)
                                    </span>
                                    <span v-else class="ml-1 text-[10px] text-emerald-500">✅</span>
                                </div>
                            </div>
                        </div>

                        <!-- Error de montos -->
                        <div v-if="errorMontos" class="mb-3 p-2 rounded-lg border"
                             :class="errorMontos.includes('✅') ? 'bg-emerald-50 border-emerald-300' : (errorMontos.includes('⚠️') ? 'bg-yellow-50 border-yellow-300' : 'bg-red-50 border-red-300')">
                            <p class="text-xs font-medium" 
                               :class="errorMontos.includes('✅') ? 'text-emerald-700' : (errorMontos.includes('⚠️') ? 'text-yellow-700' : 'text-red-700')">
                                {{ errorMontos }}
                            </p>
                        </div>

                        <!-- Tabla -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50">
                                    <tr>
                                        <th class="px-3 py-1.5 text-left text-[9px] font-medium text-primary-700 uppercase">Método de Pago</th>
                                        <th class="px-3 py-1.5 text-right text-[9px] font-medium text-primary-700 uppercase w-28">Monto (Bs)</th>
                                        <th class="px-3 py-1.5 text-center text-[9px] font-medium text-primary-700 uppercase w-10">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(pago, index) in metodosPago" :key="pago.IdVentasLiquidacion || index"
                                        :class="Number(pago.Bolivianos) === 0 ? 'bg-red-50' : ''">
                                        <td class="px-3 py-1.5">
                                            <select 
                                                v-model="pago.IdCuenta" 
                                                @change="onCambiarConcepto(pago, index, $event.target.value)"
                                                class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-primary-500 focus:border-primary-500 outline-none"
                                                :disabled="guardando || clicProcesado"
                                                :class="{
                                                    'border-red-300 bg-red-50': pago.IdCuenta && conceptoYaSeleccionado(pago.IdCuenta, index)
                                                }"
                                            >
                                                <option value="">-- Seleccionar --</option>
                                                <option 
                                                    v-for="concepto in conceptosDisponibles(index)" 
                                                    :key="concepto.IdCuenta" 
                                                    :value="concepto.IdCuenta"
                                                >
                                                    {{ concepto.Concepto }}
                                                </option>
                                            </select>
                                            <p v-if="pago.IdCuenta && conceptoYaSeleccionado(pago.IdCuenta, index)" 
                                               class="text-[8px] text-red-500 mt-0.5">
                                                ⚠️ Método duplicado
                                            </p>
                                            <p v-else-if="!pago.IdCuenta && conceptosDisponibles(index).length === 0" 
                                               class="text-[8px] text-amber-500 mt-0.5">
                                                ⚠️ No hay más métodos disponibles
                                            </p>
                                        </td>
                                        <td class="px-3 py-1.5">
                                            <input 
                                                type="number" 
                                                v-model.number="pago.Bolivianos" 
                                                step="0.01" 
                                                min="0"
                                                @input="validarMonto(pago, index)"
                                                @wheel.prevent
                                                @keydown.up.prevent
                                                @keydown.down.prevent
                                                class="w-28 ml-auto text-right border border-gray-300 rounded-md px-2 py-1 text-xs no-spinner focus:ring-primary-500 focus:border-primary-500 outline-none"
                                                :class="Number(pago.Bolivianos) === 0 ? 'border-red-300 bg-red-50' : ''"
                                                :disabled="guardando || clicProcesado"
                                            >
                                        </td>
                                        <td class="px-3 py-1.5 text-center">
                                            <button 
                                                @click="eliminarMetodo(index)"
                                                :disabled="guardando || clicProcesado"
                                                class="text-red-500 hover:text-red-700 transition disabled:opacity-50 disabled:cursor-not-allowed text-xs"
                                                title="Eliminar"
                                            >
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr class="border-t-2 border-gray-200">
                                        <td class="px-3 py-1.5 text-xs font-bold text-gray-700">TOTAL</td>
                                        <td class="px-3 py-1.5 text-xs font-bold text-right" :class="montosIguales ? 'text-emerald-600' : 'text-red-600'">
                                            {{ formatearNumero(totalMontos) }} Bs
                                            <span v-if="montosIguales" class="text-[9px] ml-1">✅</span>
                                            <span v-else class="text-[9px] ml-1">❌</span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Agregar método -->
                        <div class="mt-2">
                            <button 
                                @click="agregarMetodo"
                                :disabled="guardando || clicProcesado"
                                class="text-xs text-primary-600 hover:text-primary-800 transition flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <i class="fas fa-plus-circle text-[10px]"></i> Agregar método de pago
                            </button>
                            <span v-if="metodosPago.length >= conceptos.length" class="text-[9px] text-amber-500 ml-2">
                                (todos los métodos ya están seleccionados)
                            </span>
                        </div>

                        <!-- Error general -->
                        <div v-if="errors.general" class="mt-2 p-1.5 bg-red-50 rounded-lg">
                            <p class="text-[10px] text-red-600">{{ errors.general }}</p>
                        </div>

                        <div v-if="guardando || clicProcesado" class="mt-2 p-1.5 bg-primary-50 rounded-lg border border-primary-200">
                            <div class="flex items-center justify-center gap-1.5 text-primary-600">
                                <i class="fas fa-spinner fa-spin text-[10px]"></i>
                                <span class="text-[10px] font-medium">Guardando cambios... No cierres esta ventana</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== FOOTER ==================== -->
                <div class="px-4 py-2.5 border-t border-gray-200 bg-gray-50 rounded-b-xl flex flex-wrap justify-end gap-1.5">
                    <button 
                        @click="cerrarModal" 
                        :disabled="guardando || clicProcesado"
                        class="px-3 py-1 border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-100 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Cancelar
                    </button>
                    <button 
                        @click="guardarCambios" 
                        :disabled="guardando || clicProcesado || !puedeGuardar"
                        class="px-4 py-1 rounded-md text-xs font-medium transition flex items-center gap-1.5 min-w-[120px] justify-center"
                        :class="(puedeGuardar && !guardando && !clicProcesado) ? 'bg-primary-600 text-white hover:bg-primary-700' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                    >
                        <i v-if="guardando || clicProcesado" class="fas fa-spinner fa-spin text-[10px]"></i>
                        <i v-else class="fas fa-save text-[10px]"></i>
                        {{ (guardando || clicProcesado) ? 'Guardando...' : 'Guardar Cambios' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== TOAST ==================== -->
    <div v-if="toastVisible" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-3 py-1.5 rounded-lg shadow-lg text-xs text-white flex items-center gap-1.5"
         :class="toastTipo === 'success' ? 'bg-emerald-500' : (toastTipo === 'error' ? 'bg-red-500' : 'bg-yellow-500')">
        <i :class="toastTipo === 'success' ? 'fas fa-check-circle' : (toastTipo === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-exclamation-triangle')" class="text-[10px]"></i>
        {{ toastMensaje }}
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    input, select, button {
        font-size: 13px !important;
    }
}

.no-spinner::-webkit-inner-spin-button,
.no-spinner::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.no-spinner {
    -moz-appearance: textfield;
    appearance: textfield;
}

input[type="number"] {
    -moz-appearance: textfield;
}

input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>