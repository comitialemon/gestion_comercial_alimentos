<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
    liquidacion: Object,
    conceptos: Array,
    fechaStr: String,
    fechaId: Number,
})

const emit = defineEmits(['volver'])

// Estado del formulario
const form = ref({
    IdFecha: props.fechaId,
    vEntasConfirma: props.liquidacion?.vEntasConfirma || 0,
    conceptos: props.conceptos || [],
})

const loading = ref(false)

// Recalcular diferencia automáticamente
const recalcularDiferencia = () => {
    const sumaMontos = form.value.conceptos.reduce((sum, c) => sum + (Number(c.monto_confirmacion) || 0), 0)
    form.value.diferencia = form.value.vEntasConfirma - sumaMontos
}

// Watcher para vEntasConfirma
watch(() => form.value.vEntasConfirma, () => recalcularDiferencia())

// Watcher para cada concepto
const addConceptoWatchers = () => {
    form.value.conceptos.forEach((concepto, index) => {
        watch(() => form.value.conceptos[index].monto_confirmacion, () => recalcularDiferencia())
    })
}

// Inicializar watchers
recalcularDiferencia()
addConceptoWatchers()

// Actualizar monto_confirmacion de un concepto
const actualizarMonto = (index, value) => {
    form.value.conceptos[index].monto_confirmacion = parseFloat(value) || 0
}

// Guardar liquidación
const guardarLiquidacion = async () => {
    if (form.value.vEntasConfirma === 0) {
        alert('No tiene datos...!')
        return
    }
    
    loading.value = true
    try {
        const response = await axios.post('/gestion/liquidacion-vendedor/guardar', {
            IdFecha: form.value.IdFecha,
            vEntasConfirma: form.value.vEntasConfirma,
            conceptos: form.value.conceptos.map(c => ({
                id: c.id,
                monto_sistema: c.monto_sistema,
                monto_confirmacion: c.monto_confirmacion,
            })),
        })
        
        if (response.data.success) {
            // 🔥 Abrir PDF en nueva pestaña
            window.open(response.data.pdf_url, '_blank')
            
            // Redirigir al listado
            router.get('/gestion/liquidacion-vendedor')
        } else {
            alert(response.data.message || 'Error al guardar')
        }
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

const formatearNumero = (value) => {
    return Number(value).toFixed(2)
}
</script>

<template>
    <div>
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
                                {{ formatearNumero(form.vEntasConfirma) }}
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
                        
                        <!-- Conceptos dinámicos -->
                        <tr v-for="(concepto, index) in form.conceptos" :key="concepto.id">
                            <td class="px-6 py-4 text-sm font-medium text-gray-700">{{ concepto.nombre }}</td>
                            <td class="px-6 py-4 text-right text-sm text-gray-600">
                                {{ formatearNumero(concepto.monto_sistema) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <input 
                                    type="number" 
                                    :value="concepto.monto_confirmacion"
                                    @input="actualizarMonto(index, $event.target.value)"
                                    step="0.01"
                                    class="w-40 text-right border rounded-lg px-2 py-1 text-sm"
                                >
                            </td>
                        </tr>
                        
                        <!-- Diferencia -->
                        <tr class="bg-gray-50 font-bold">
                            <td class="px-6 py-4 text-sm font-bold text-gray-800">Diferencia</td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-guindo-600">
                                {{ formatearNumero(form.diferencia) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="inline-block w-40 text-right font-bold text-guindo-600">
                                    {{ formatearNumero(form.diferencia) }}
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