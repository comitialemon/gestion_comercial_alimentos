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

// 🔥 CALCULAR DIFERENCIA EXACTA
const diferencia = computed(() => {
    return totalVenta.value - totalMontos.value
})

// 🔥 VERIFICAR SI SON IGUALES (con margen de 0.01 por redondeo)
const montosIguales = computed(() => {
    return Math.abs(diferencia.value) <= 0.01
})

// 🔥 VERIFICAR SI HAY MONTO CERO
const tieneMontosCero = computed(() => {
    return metodosPago.value.some(pago => Number(pago.Bolivianos) === 0)
})

// 🔥 PUEDE GUARDAR SOLO SI SON IGUALES Y NO HAY CEROS
const puedeGuardar = computed(() => {
    return montosIguales.value && totalMontos.value > 0 && !tieneMontosCero.value
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

// 🔥 VALIDACIÓN ESTRICTA
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

// 🔥 VALIDAR MONTO CON LÍMITE ESTRICTO
const validarMonto = (pago, index) => {
    // No permitir negativos
    if (pago.Bolivianos < 0) {
        pago.Bolivianos = 0
        mostrarToast('⚠️ No se permiten montos negativos', 'warning')
        validarMontos()
        return
    }

    // Calcular cuánto pueden sumar los demás
    const otrosMontos = metodosPago.value.reduce((sum, p, i) => {
        if (i !== index) return sum + (Number(p.Bolivianos) || 0)
        return sum
    }, 0)
    
    // El máximo que puede tener este campo es el total de la factura menos los demás
    const maximoPermitido = totalVenta.value - otrosMontos
    
    // Si excede el máximo, ajustar
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

const guardarCambios = async () => {
    // 🔥 VALIDACIÓN ESTRICTA ANTES DE GUARDAR
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

// Agregar método de pago
const agregarMetodo = () => {
    metodosPago.value.push({ 
        IdVentasLiquidacion: 0,
        IdCuenta: conceptos.value[0]?.IdCuenta || '', 
        Bolivianos: 0 
    })
}

// 🔥 ELIMINAR MÉTODO DE PAGO
const eliminarMetodo = (index) => {
    const pago = metodosPago.value[index]
    
    if (pago.IdVentasLiquidacion > 0) {
        const concepto = conceptos.value.find(c => c.IdCuenta === pago.IdCuenta)
        if (!confirm(`¿Eliminar "${concepto?.Concepto || 'método'}"?`)) {
            return
        }
    }
    
    metodosPago.value.splice(index, 1)
    validarMontos()
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
                                    <span class="ml-2 font-semibold" :class="montosIguales ? 'text-green-600' : 'text-red-600'">
                                        {{ formatearNumero(totalMontos) }} Bs
                                    </span>
                                    <span v-if="!montosIguales" class="ml-2 text-xs text-red-500 font-bold">
                                        ({{ diferencia > 0 ? 'EXCEDE' : 'FALTA' }}: {{ formatearNumero(Math.abs(diferencia)) }} Bs)
                                    </span>
                                    <span v-else class="ml-2 text-xs text-green-500">
                                        ✅ CORRECTO
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Error de montos - Más visible -->
                        <div v-if="errorMontos" class="mb-3 p-3 rounded-lg border-2"
                             :class="errorMontos.includes('✅') ? 'bg-green-50 border-green-300' : (errorMontos.includes('⚠️') ? 'bg-yellow-50 border-yellow-300' : 'bg-red-50 border-red-300')">
                            <p class="text-sm font-medium" 
                               :class="errorMontos.includes('✅') ? 'text-green-700' : (errorMontos.includes('⚠️') ? 'text-yellow-700' : 'text-red-700')">
                                {{ errorMontos }}
                            </p>
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
                                    <tr v-for="(pago, index) in metodosPago" :key="pago.IdVentasLiquidacion || index"
                                        :class="Number(pago.Bolivianos) === 0 ? 'bg-red-50' : ''">
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
                                                :class="Number(pago.Bolivianos) === 0 ? 'border-red-300 bg-red-50' : ''"
                                            >
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <button 
                                                @click="eliminarMetodo(index)"
                                                class="text-red-500 hover:text-red-700 transition"
                                                title="Eliminar"
                                            >
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr class="border-t-2 border-gray-200">
                                        <td class="px-4 py-2 text-sm font-bold text-gray-700">TOTAL</td>
                                        <td class="px-4 py-2 text-sm font-bold text-right" :class="montosIguales ? 'text-green-600' : 'text-red-600'">
                                            {{ formatearNumero(totalMontos) }} Bs
                                            <span v-if="montosIguales" class="text-xs ml-1">✅</span>
                                            <span v-else class="text-xs ml-1">❌</span>
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
                        class="px-4 py-1.5 rounded-md text-sm transition flex items-center gap-2"
                        :class="puedeGuardar ? 'bg-primary-600 text-white hover:bg-primary-700' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
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