<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    modelValue: Boolean,
    venta: Object,
})

const emit = defineEmits(['update:modelValue', 'actualizado'])

const modalOpen = ref(props.modelValue)
const metodosPago = ref([])
const conceptos = ref([])
const totalVenta = ref(0)
const numeroFactura = ref('')
const cargando = ref(false)
const guardando = ref(false)
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

watch(() => props.modelValue, (newVal) => {
    modalOpen.value = newVal
    if (newVal && props.venta) {
        cargarMetodosPago()
    }
})

watch(modalOpen, (newVal) => {
    emit('update:modelValue', newVal)
})

// ==================== COMPUTADOS ====================
const totalMontos = computed(() => {
    return metodosPago.value.reduce((sum, pago) => sum + (Number(pago.Bolivianos) || 0), 0)
})

const diferencia = computed(() => {
    return totalVenta.value - totalMontos.value
})

const hayDiferencia = computed(() => {
    return Math.abs(diferencia.value) > 0.01
})

const puedeGuardar = computed(() => {
    return !hayDiferencia.value && totalMontos.value > 0
})

// ==================== MÉTODOS ====================
const cargarMetodosPago = async () => {
    if (!props.venta?.IdVentas) return
    
    cargando.value = true
    errorMontos.value = ''
    try {
        const response = await axios.get(`/gestion/impuestos/mantenimiento-metodos-pago/${props.venta.IdVentas}/metodos-pago`)
        
        if (response.data.success) {
            metodosPago.value = response.data.metodosPago
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
    const diff = Math.abs(total - totalVenta.value)
    
    if (diff > 0.01) {
        if (total > totalVenta.value) {
            errorMontos.value = `⚠️ El total de los montos (${formatearNumero(total)} Bs) supera el total de la factura (${formatearNumero(totalVenta.value)} Bs) por ${formatearNumero(diff)} Bs.`
        } else {
            errorMontos.value = `⚠️ El total de los montos (${formatearNumero(total)} Bs) es menor al total de la factura (${formatearNumero(totalVenta.value)} Bs) por ${formatearNumero(diff)} Bs.`
        }
    } else {
        errorMontos.value = ''
    }
}

const validarMonto = (pago, index) => {
    if (pago.Bolivianos < 0) {
        pago.Bolivianos = 0
    }
    
    const otrosMontos = metodosPago.value.reduce((sum, p, i) => {
        if (i !== index) return sum + (Number(p.Bolivianos) || 0)
        return sum
    }, 0)
    
    const maximoPermitido = totalVenta.value - otrosMontos
    
    if (pago.Bolivianos > maximoPermitido && maximoPermitido > 0) {
        const valorOriginal = pago.Bolivianos
        pago.Bolivianos = Math.round(maximoPermitido * 100) / 100
        
        if (Math.abs(valorOriginal - pago.Bolivianos) > 0.01) {
            mostrarToast(
                `⚠️ El monto ingresado (${formatearNumero(valorOriginal)} Bs) supera el total disponible. Se ajustó a ${formatearNumero(pago.Bolivianos)} Bs.`,
                'warning'
            )
        }
    }
    
    validarMontos()
}

const guardarCambios = async () => {
    if (hayDiferencia.value) {
        mostrarToast('❌ El total de los montos no coincide con el total de la factura. Ajuste los valores antes de guardar.', 'error')
        return
    }
    
    if (totalMontos.value === 0) {
        mostrarToast('❌ Debe asignar al menos un método de pago con monto mayor a 0.', 'error')
        return
    }
    
    guardando.value = true
    errors.value = {}
    
    try {
        const response = await axios.put(`/gestion/impuestos/mantenimiento-metodos-pago/${props.venta.IdVentas}/metodos-pago`, {
            pagos: metodosPago.value
        })
        
        if (response.data.success) {
            mostrarToast('✅ Métodos de pago actualizados correctamente', 'success')
            setTimeout(() => {
                cerrarModal()
                emit('actualizado')
            }, 800)
        } else {
            errors.value = { general: response.data.message }
            mostrarToast('❌ ' + response.data.message, 'error')
        }
    } catch (error) {
        console.error('Error guardando:', error)
        const mensaje = error.response?.data?.message || 'Error al guardar'
        errors.value = { general: mensaje }
        mostrarToast('❌ ' + mensaje, 'error')
    } finally {
        guardando.value = false
    }
}

const cerrarModal = () => {
    modalOpen.value = false
}

const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toFixed(2)
}

// 🔥 Agregar método de pago
const agregarMetodo = () => {
    metodosPago.value.push({ 
        IdVentasLiquidacion: 0,
        IdCuenta: conceptos.value[0]?.IdCuenta || '', 
        Bolivianos: 0 
    })
}
</script>

<template>
    <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="cerrarModal">
        <div class="flex items-center justify-center min-h-screen p-3">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="cerrarModal"></div>
            
            <div class="relative bg-white rounded-lg shadow-xl max-w-3xl w-full mx-auto transform transition-all duration-300">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-3 border-b bg-primary-600 rounded-t-lg">
                    <h3 class="text-sm font-semibold text-white">
                        <i class="fas fa-credit-card mr-2"></i> Modificar Métodos de Pago
                    </h3>
                    <button @click="cerrarModal" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5">
                    <div v-if="cargando" class="flex justify-center py-10">
                        <i class="fas fa-spinner fa-spin text-primary-500 text-xl"></i>
                    </div>

                    <div v-else>
                        <!-- Información de la factura -->
                        <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <span class="font-medium text-gray-600">N° Factura:</span>
                                    <span class="ml-2 text-gray-800 font-mono">{{ numeroFactura }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-600">Total Factura:</span>
                                    <span class="ml-2 text-primary-600 font-semibold">{{ formatearNumero(totalVenta) }} Bs</span>
                                </div>
                                <div class="col-span-2">
                                    <span class="font-medium text-gray-600">Total Pagado:</span>
                                    <span class="ml-2 font-semibold" :class="totalMontos === totalVenta ? 'text-green-600' : 'text-red-600'">
                                        {{ formatearNumero(totalMontos) }} Bs
                                    </span>
                                    <span v-if="hayDiferencia" class="ml-2 text-xs text-red-500">
                                        ({{ diferencia > 0 ? 'Excedente' : 'Faltante' }}: {{ formatearNumero(Math.abs(diferencia)) }} Bs)
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Error de montos -->
                        <div v-if="errorMontos" class="mb-3 p-2 bg-red-50 rounded-lg border border-red-200">
                            <p class="text-xs text-red-600">{{ errorMontos }}</p>
                        </div>

                        <!-- Tabla de métodos de pago -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-primary-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-primary-700 uppercase">Método de Pago</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-primary-700 uppercase">Monto (Bs)</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-primary-700 uppercase w-12">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(pago, index) in metodosPago" :key="pago.IdVentasLiquidacion || index">
                                        <td class="px-4 py-2">
                                            <select v-model="pago.IdCuenta" class="w-full border rounded-md px-2 py-1.5 text-sm">
                                                <option v-for="concepto in conceptos" :key="concepto.IdCuenta" :value="concepto.IdCuenta">
                                                    {{ concepto.Concepto }}
                                                </option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-2">
                                            <input 
                                                type="number" 
                                                v-model.number="pago.Bolivianos" 
                                                step="0.01" 
                                                min="0"
                                                @input="validarMonto(pago, index)"
                                                class="w-32 ml-auto text-right border rounded-md px-2 py-1.5 text-sm no-spinner"
                                            >
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <button 
                                                @click="metodosPago.splice(index, 1)"
                                                class="text-red-500 hover:text-red-700 transition"
                                                title="Eliminar"
                                            >
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr class="border-t border-gray-200">
                                        <td class="px-4 py-2 text-sm font-bold text-gray-700">TOTAL</td>
                                        <td class="px-4 py-2 text-sm font-bold text-right" :class="totalMontos === totalVenta ? 'text-green-600' : 'text-red-600'">
                                            {{ formatearNumero(totalMontos) }} Bs
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Botón para agregar nuevo método de pago -->
                        <div class="mt-3">
                            <button 
                                @click="agregarMetodo"
                                class="text-sm text-primary-600 hover:text-primary-800 transition flex items-center gap-1"
                            >
                                <i class="fas fa-plus-circle"></i> Agregar método de pago
                            </button>
                        </div>

                        <!-- Error general -->
                        <div v-if="errors.general" class="mt-3 p-2 bg-red-50 rounded-lg">
                            <p class="text-xs text-red-600">{{ errors.general }}</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-3 border-t bg-gray-50 flex justify-end gap-2 rounded-b-lg">
                    <button @click="cerrarModal" class="px-4 py-1.5 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition">
                        Cancelar
                    </button>
                    <button 
                        @click="guardarCambios" 
                        :disabled="guardando || !puedeGuardar"
                        class="px-4 py-1.5 bg-primary-600 text-white rounded-md text-sm hover:bg-primary-700 transition disabled:opacity-50 flex items-center gap-2"
                    >
                        <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ guardando ? 'Guardando...' : 'Guardar Cambios' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== TOAST ==================== -->
    <div v-if="toastVisible" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-lg text-sm text-white flex items-center gap-2"
         :class="toastTipo === 'success' ? 'bg-green-500' : (toastTipo === 'error' ? 'bg-red-500' : 'bg-yellow-500')">
        <i :class="toastTipo === 'success' ? 'fas fa-check-circle' : (toastTipo === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-exclamation-triangle')"></i>
        {{ toastMensaje }}
    </div>
</template>

<style scoped>
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
</style> 