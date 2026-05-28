<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
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
const isMobile = ref(window.innerWidth < 768)

// Detectar cambios de tamaño
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

// Recalcular diferencia automáticamente
const recalcularDiferencia = () => {
    const sumaMontos = form.value.conceptos.reduce((sum, c) => sum + (Number(c.monto_sistema) || 0), 0)
    form.value.diferencia = form.value.vEntasConfirma - sumaMontos
}

// Watcher para vEntasConfirma
watch(() => form.value.vEntasConfirma, () => recalcularDiferencia())

// Inicializar recalculo
recalcularDiferencia()

// Guardar liquidación
const guardarLiquidacion = async () => {
    if (form.value.vEntasConfirma === 0) {
        alert('No hay datos para guardar')
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
                monto_confirmacion: c.monto_sistema, // 🔥 Usamos el monto del sistema automáticamente
            })),
        })
        
        if (response.data.success) {
            window.open(response.data.pdf_url, '_blank')
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
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-5xl mx-auto">
                <!-- Header Responsive -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-invoice-dollar text-emerald-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Liquidación de Ventas</h1>
                            <p class="text-[10px] text-gray-500">Fecha: {{ fechaStr }}</p>
                        </div>
                    </div>
                    <button @click="volver" class="text-primary-600 hover:text-primary-800 text-xs flex items-center gap-1">
                        <i class="fas fa-arrow-left"></i> Volver a fechas
                    </button>
                </div>

                <!-- Vista MÓVIL (tarjetas) -->
                <div v-if="isMobile" class="space-y-3">
                    <!-- Tarjeta de Ventas -->
                    <div class="bg-white rounded-lg shadow-sm p-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-semibold text-gray-700">Total Ventas</span>
                            <span class="text-sm font-bold text-emerald-600">{{ formatearNumero(form.vEntasConfirma) }} Bs</span>
                        </div>
                    </div>

                    <!-- Tarjetas de Conceptos -->
                    <div v-for="concepto in form.conceptos" :key="concepto.id" class="bg-white rounded-lg shadow-sm p-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">{{ concepto.nombre }}</span>
                            <span class="text-sm font-semibold text-gray-800">{{ formatearNumero(concepto.monto_sistema) }} Bs</span>
                        </div>
                    </div>

                    <!-- Tarjeta de Diferencia -->
                    <div class="bg-emerald-50 rounded-lg shadow-sm p-3 border border-emerald-200">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-bold text-gray-800">Diferencia</span>
                            <span class="text-sm font-bold text-emerald-600">{{ formatearNumero(form.diferencia) }} Bs</span>
                        </div>
                    </div>

                    <!-- Mensaje informativo -->
                    <div class="bg-blue-50 rounded-lg p-3 text-xs text-blue-700">
                        <i class="fas fa-info-circle mr-1"></i>
                        Los valores se toman automáticamente del sistema.
                    </div>

                    <!-- Botones -->
                    <div class="flex gap-3 pt-2">
                        <button 
                            @click="volver" 
                            class="flex-1 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm hover:bg-gray-100 transition"
                        >
                            Cancelar
                        </button>
                        <button 
                            @click="guardarLiquidacion" 
                            :disabled="loading"
                            class="flex-1 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ loading ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </div>

                <!-- Vista ESCRITORIO (tabla) -->
                <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-emerald-50">
                                <tr class="bg-emerald-50">
                                    <th class="px-4 py-2 text-left text-xs font-medium text-emerald-700 uppercase">Concepto</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-emerald-700 uppercase">Monto (Bs)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <!-- Ventas -->
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm font-semibold text-gray-700">Total Ventas</td>
                                    <td class="px-4 py-2 text-sm text-right font-bold text-emerald-600">
                                        {{ formatearNumero(form.vEntasConfirma) }}
                                    </td>
                                </tr>
                                
                                <!-- Conceptos dinámicos -->
                                <tr v-for="concepto in form.conceptos" :key="concepto.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm text-gray-700">{{ concepto.nombre }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-800">
                                        {{ formatearNumero(concepto.monto_sistema) }}
                                    </td>
                                </tr>
                                
                                <!-- Diferencia -->
                                <tr class="bg-emerald-50 font-bold">
                                    <td class="px-4 py-2 text-sm font-bold text-gray-800">Diferencia</td>
                                    <td class="px-4 py-2 text-sm text-right font-bold text-emerald-600">
                                        {{ formatearNumero(form.diferencia) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 bg-blue-50 text-xs text-blue-700 border-t">
                        <i class="fas fa-info-circle mr-1"></i>
                        Los valores se toman automáticamente del sistema. Al guardar se genera el PDF de liquidación.
                    </div>

                    <div class="px-4 py-3 bg-gray-50 border-t flex justify-end gap-3">
                        <button 
                            @click="volver" 
                            class="px-4 py-1.5 border border-gray-300 rounded-lg text-gray-700 text-sm hover:bg-gray-100 transition"
                        >
                            Cancelar
                        </button>
                        <button 
                            @click="guardarLiquidacion" 
                            :disabled="loading"
                            class="px-5 py-1.5 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition disabled:opacity-50 flex items-center gap-2"
                        >
                            <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-save"></i>
                            {{ loading ? 'Guardando...' : 'Guardar Liquidación' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .xs\:inline {
        display: inline;
    }
    .xs\:block {
        display: block;
    }
}
</style>