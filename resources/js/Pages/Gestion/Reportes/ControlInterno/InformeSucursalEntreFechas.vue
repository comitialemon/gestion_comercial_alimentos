<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    fechas: {
        type: Array,
        default: () => []
    }
})

const fechaInicial = ref('')
const fechaFinal = ref('')
const loading = ref(false)

const generarReporte = () => {
    if (!fechaInicial.value) {
        alert('Seleccione la fecha inicial')
        return
    }
    if (!fechaFinal.value) {
        alert('Seleccione la fecha final')
        return
    }
    
    loading.value = true
    
    window.open(`/gestion/reportes/control-interno/informe-sucursal-entre-fechas/exportar?fecha_inicial=${fechaInicial.value}&fecha_final=${fechaFinal.value}`, '_blank')
    
    setTimeout(() => {
        loading.value = false
    }, 1000)
}

const volver = () => {
    router.get('/oficial')
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-primary-100 rounded-2xl mb-3">
                        <i class="fas fa-chart-line text-xl text-primary-600"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Informe de Movimiento Diario de Sucursales</h1>
                    <p class="text-xs text-gray-500 mt-1">Seleccione el rango de fechas para generar el reporte</p>
                </div>

                <!-- Formulario -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6 space-y-5">
                        <!-- Fecha Inicial -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                📅 Fecha Inicial
                                <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="date" 
                                v-model="fechaInicial"
                                class="w-full border rounded-lg px-3 py-2 text-sm"
                            />
                        </div>

                        <!-- Fecha Final -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                📅 Fecha Final
                                <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="date" 
                                v-model="fechaFinal"
                                class="w-full border rounded-lg px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3">
                        <button 
                            @click="volver" 
                            class="px-4 py-2 border rounded-lg text-gray-700 text-sm hover:bg-gray-100"
                        >
                            Cancelar
                        </button>
                        <button 
                            @click="generarReporte" 
                            :disabled="!fechaInicial || !fechaFinal || loading"
                            class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-file-excel"></i>
                            {{ loading ? 'Generando...' : 'Generar Excel' }}
                        </button>
                    </div>
                </div>

                <!-- Info -->
                <div class="mt-4 text-center text-[10px] text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    El reporte se generará en formato Excel con el análisis completo del movimiento diario en el rango de fechas seleccionado.
                </div>
            </div>
        </div>
    </div>
</template>