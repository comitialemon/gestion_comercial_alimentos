<script setup>
import { ref, watch } from 'vue'
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

watch(() => props.modelValue, (newVal) => {
    modalOpen.value = newVal
    if (newVal && props.venta) {
        cargarMetodosPago()
    }
})

watch(modalOpen, (newVal) => {
    emit('update:modelValue', newVal)
})

const cargarMetodosPago = async () => {
    if (!props.venta?.IdVentas) return
    
    cargando.value = true
    try {
        const response = await axios.get(`/gestion/mantenimiento-metodos-pago/${props.venta.IdVentas}/metodos-pago`)
        
        if (response.data.success) {
            metodosPago.value = response.data.metodosPago
            conceptos.value = response.data.conceptos
            totalVenta.value = response.data.totalVenta
            numeroFactura.value = response.data.numeroFactura
        }
    } catch (error) {
        console.error('Error cargando métodos de pago:', error)
    } finally {
        cargando.value = false
    }
}

const guardarCambios = async () => {
    guardando.value = true
    errors.value = {}
    
    try {
        const response = await axios.put(`/gestion/mantenimiento-metodos-pago/${props.venta.IdVentas}/metodos-pago`, {
            pagos: metodosPago.value
        })
        
        if (response.data.success) {
            alert('Métodos de pago actualizados correctamente')
            cerrarModal()
            emit('actualizado')
        } else {
            errors.value = { general: response.data.message }
        }
    } catch (error) {
        console.error('Error guardando:', error)
        errors.value = { general: error.response?.data?.message || 'Error al guardar' }
    } finally {
        guardando.value = false
    }
}

const cerrarModal = () => {
    modalOpen.value = false
}

const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

// Calcular total de los montos ingresados
const totalMontos = () => {
    return metodosPago.value.reduce((sum, pago) => sum + (Number(pago.Bolivianos) || 0), 0)
}
</script>

<template>
    <div v-if="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="cerrarModal">
        <div class="flex items-center justify-center min-h-screen p-3">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="cerrarModal"></div>
            
            <div class="relative bg-white rounded-lg shadow-xl max-w-3xl w-full mx-auto transform transition-all duration-300">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-3 border-b bg-guindo-600 rounded-t-lg">
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
                        <i class="fas fa-spinner fa-spin text-guindo-500 text-xl"></i>
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
                                    <span class="ml-2 text-guindo-600 font-semibold">{{ formatearNumero(totalVenta) }} Bs</span>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla de métodos de pago -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-guindo-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-guindo-700 uppercase">Método de Pago</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-guindo-700 uppercase">Monto (Bs)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(pago, index) in metodosPago" :key="pago.IdVentasLiquidacion">
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
                                                class="w-32 ml-auto text-right border rounded-md px-2 py-1.5 text-sm"
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr class="border-t border-gray-200">
                                        <td class="px-4 py-2 text-sm font-bold text-gray-700">TOTAL</td>
                                        <td class="px-4 py-2 text-sm font-bold text-guindo-600 text-right">
                                            {{ formatearNumero(totalMontos()) }} Bs
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Advertencia si hay diferencia -->
                        <div v-if="Math.abs(totalMontos() - totalVenta) > 0.01" class="mt-3 p-2 bg-yellow-50 rounded-lg">
                            <p class="text-xs text-yellow-700">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                El total de los montos ({{ formatearNumero(totalMontos()) }} Bs) no coincide con el total de la factura ({{ formatearNumero(totalVenta) }} Bs).
                            </p>
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
                        :disabled="guardando"
                        class="px-4 py-1.5 bg-guindo-600 text-white rounded-md text-sm hover:bg-guindo-700 transition disabled:opacity-50 flex items-center gap-2"
                    >
                        <i v-if="guardando" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ guardando ? 'Guardando...' : 'Guardar Cambios' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>