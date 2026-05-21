<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

// No usar AppLayout aquí porque ya lo tiene el padre
const props = defineProps({
    liquidacion: Object,
    fechaStr: String,
    fechaId: Number,
})

const emit = defineEmits(['volver'])

// Estado del formulario
const form = ref({
    IdFecha: props.fechaId,
    vEntas: props.liquidacion?.vEntas || 0,
    vEntasConfirma: props.liquidacion?.vEntasConfirma || 0,
    eFectivoBolivianos: props.liquidacion?.eFectivoBolivianos || 0,
    eFectivoBolivianosConfirma: props.liquidacion?.eFectivoBolivianosConfirma || 0,
    cLientes: props.liquidacion?.cLientes || 0,
    cLientesConfirma: props.liquidacion?.cLientesConfirma || 0,
    pOrCobrarPersonal: props.liquidacion?.pOrCobrarPersonal || 0,
    pOrCobrarPersonalConfirma: props.liquidacion?.pOrCobrarPersonalConfirma || 0,
    tArjetaATC: props.liquidacion?.tArjetaATC || 0,
    tArjetaATCconfirma: props.liquidacion?.tArjetaATCconfirma || 0,
    dIfVendedor: props.liquidacion?.dIfVendedor || 0,
    dIfVendedorConfirma: props.liquidacion?.dIfVendedorConfirma || 0,
})

const loading = ref(false)

// Recalcular diferencia automáticamente
const recalcularDiferencia = () => {
    const sumaPagos = form.value.eFectivoBolivianosConfirma + 
                      form.value.cLientesConfirma + 
                      form.value.pOrCobrarPersonalConfirma + 
                      form.value.tArjetaATCconfirma
    
    form.value.dIfVendedorConfirma = form.value.vEntasConfirma - sumaPagos
}

// Watchers para recalcular diferencia
watch(() => form.value.vEntasConfirma, () => recalcularDiferencia())
watch(() => form.value.eFectivoBolivianosConfirma, () => recalcularDiferencia())
watch(() => form.value.cLientesConfirma, () => recalcularDiferencia())
watch(() => form.value.pOrCobrarPersonalConfirma, () => recalcularDiferencia())
watch(() => form.value.tArjetaATCconfirma, () => recalcularDiferencia())

// Guardar liquidación
const guardarLiquidacion = async () => {
    if (form.value.vEntasConfirma === 0) {
        alert('No tiene datos...!')
        return
    }
    
    loading.value = true
    try {
        await axios.post('/gestion/liquidacion-vendedor/guardar', {
            IdFecha: form.value.IdFecha,
            vEntasConfirma: form.value.vEntasConfirma,
            eFectivoBolivianosConfirma: form.value.eFectivoBolivianosConfirma,
            cLientesConfirma: form.value.cLientesConfirma,
            pOrCobrarPersonalConfirma: form.value.pOrCobrarPersonalConfirma,
            tArjetaATCconfirma: form.value.tArjetaATCconfirma,
            dIfVendedorConfirma: form.value.dIfVendedorConfirma,
        })
        router.get('/gestion/liquidacion-vendedor')
    } catch (error) {
        console.error('Error:', error)
        alert(error.response?.data?.message || 'Error al guardar')
    } finally {
        loading.value = false
    }
}

const volver = () => {
    emit('volver')
}

// Formatear números
const formatearNumero = (value) => {
    return Number(value).toFixed(2)
}
</script>

<template>
    <div>
        <!-- Formulario de liquidación -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <!-- Botón volver -->
            <div class="p-4 border-b bg-gray-50">
                <button @click="volver" class="text-guindo-600 hover:text-guindo-800 text-sm flex items-center gap-1">
                    <i class="fas fa-arrow-left"></i> Volver a fechas
                </button>
            </div>

            <div class="p-4">
                <div class="mb-4 text-center">
                    <h2 class="text-lg font-bold text-gray-800">Liquidación de Ventas</h2>
                    <p class="text-sm text-gray-500">Fecha: {{ fechaStr }}</p>
                </div>

                <table class="min-w-full">
                    <thead class="bg-guindo-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Concepto</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-guindo-700 uppercase">Datos Sistema</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-guindo-700 uppercase">Confirmación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <!-- Ventas -->
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-700">Total Ventas</td>
                            <td class="px-6 py-4 text-right text-sm text-gray-600">
                                {{ formatearNumero(form.vEntas) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <input 
                                    type="number" 
                                    v-model.number="form.vEntasConfirma" 
                                    step="0.01"
                                    class="w-40 text-right border rounded-lg px-2 py-1 text-sm"
                                >
                            </td>
                        </tr>
                        <!-- Efectivo -->
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-700">Efectivo</td>
                            <td class="px-6 py-4 text-right text-sm text-gray-600">
                                {{ formatearNumero(form.eFectivoBolivianos) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <input 
                                    type="number" 
                                    v-model.number="form.eFectivoBolivianosConfirma" 
                                    step="0.01"
                                    class="w-40 text-right border rounded-lg px-2 py-1 text-sm"
                                >
                            </td>
                        </tr>
                        <!-- Clientes -->
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-700">Clientes (Crédito)</td>
                            <td class="px-6 py-4 text-right text-sm text-gray-600">
                                {{ formatearNumero(form.cLientes) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <input 
                                    type="number" 
                                    v-model.number="form.cLientesConfirma" 
                                    step="0.01"
                                    class="w-40 text-right border rounded-lg px-2 py-1 text-sm"
                                >
                            </td>
                        </tr>
                        <!-- QR / Por Cobrar Personal -->
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-700">QR / Por Cobrar Personal</td>
                            <td class="px-6 py-4 text-right text-sm text-gray-600">
                                {{ formatearNumero(form.pOrCobrarPersonal) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <input 
                                    type="number" 
                                    v-model.number="form.pOrCobrarPersonalConfirma" 
                                    step="0.01"
                                    class="w-40 text-right border rounded-lg px-2 py-1 text-sm"
                                >
                            </td>
                        </tr>
                        <!-- Tarjeta ATC -->
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-700">Tarjeta ATC</td>
                            <td class="px-6 py-4 text-right text-sm text-gray-600">
                                {{ formatearNumero(form.tArjetaATC) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <input 
                                    type="number" 
                                    v-model.number="form.tArjetaATCconfirma" 
                                    step="0.01"
                                    class="w-40 text-right border rounded-lg px-2 py-1 text-sm"
                                >
                            </td>
                        </tr>
                        <!-- Diferencia -->
                        <tr class="bg-gray-50 font-bold">
                            <td class="px-6 py-4 text-sm font-bold text-gray-800">Diferencia</td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-guindo-600">
                                {{ formatearNumero(form.dIfVendedor) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="inline-block w-40 text-right font-bold text-guindo-600">
                                    {{ formatearNumero(form.dIfVendedorConfirma) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Los valores de "Confirmación" pueden ser ajustados. La diferencia se recalcula automáticamente.
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button 
                        @click="volver" 
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition"
                    >
                        Cancelar
                    </button>
                    <button 
                        @click="guardarLiquidacion" 
                        :disabled="loading"
                        class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-2"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-save"></i>
                        {{ loading ? 'Guardando...' : 'Guardar Liquidación' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>