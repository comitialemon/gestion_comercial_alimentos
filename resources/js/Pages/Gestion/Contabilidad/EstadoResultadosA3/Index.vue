<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: Array,
    sucursalSeleccionada: Number,
    fecha: String,
    tipoOperador: Number,
})

const sucursal = ref(props.sucursalSeleccionada || '')
const fecha = ref(props.fecha || new Date().toISOString().slice(0, 10))
const generando = ref(false)
const error = ref('')

const sucursalBloqueada = computed(() => props.tipoOperador === 11)

const generarReporte = () => {
    if (!sucursal.value) {
        error.value = 'Seleccione una sucursal'
        return
    }
    if (!fecha.value) {
        error.value = 'Seleccione una fecha'
        return
    }
    
    error.value = ''
    generando.value = true
    
    const url = `/gestion/estado-resultados-a3/generar?sucursal=${sucursal.value}&fecha=${fecha.value}`
    window.open(url, '_blank')
    generando.value = false
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-2xl mx-auto">
                <div class="flex items-center gap-2 mb-6">
                    <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-chart-pie text-guindo-600 text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Estado de Resultados A3</h1>
                        <p class="text-xs text-gray-500">Reporte de ingresos, gastos y resultado del período</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-store mr-1 text-guindo-600"></i> Sucursal *
                            </label>
                            <select 
                                v-model="sucursal" 
                                :disabled="sucursalBloqueada"
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-guindo-500 focus:border-guindo-500"
                                :class="{ 'bg-gray-100': sucursalBloqueada }"
                            >
                                <option value="">Seleccione una sucursal</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">
                                    {{ s.nombre }}
                                </option>
                            </select>
                            <p v-if="sucursalBloqueada" class="text-xs text-gray-400 mt-1">
                                <i class="fas fa-lock mr-1"></i> Sucursal fijada por su perfil
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-calendar-alt mr-1 text-guindo-600"></i> Fecha de Corte *
                            </label>
                            <input 
                                type="date" 
                                v-model="fecha" 
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-guindo-500 focus:border-guindo-500"
                            >
                            <p class="text-xs text-gray-400 mt-1">
                                Período: 1 de abril al {{ new Date(fecha).toLocaleDateString('es-BO') }}
                            </p>
                        </div>

                        <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-2">
                            <p class="text-red-600 text-xs flex items-center gap-1">
                                <i class="fas fa-exclamation-circle"></i> {{ error }}
                            </p>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button 
                                @click="generarReporte" 
                                :disabled="generando || !sucursal || !fecha"
                                class="px-5 py-2 bg-guindo-600 hover:bg-guindo-700 text-white rounded-lg text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                            >
                                <i v-if="generando" class="fas fa-spinner fa-spin"></i>
                                <i v-else class="fas fa-chart-pie"></i>
                                {{ generando ? 'Generando...' : 'Generar Estado' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    El Estado de Resultados muestra ingresos, gastos y la utilidad o pérdida del período.
                    Los resultados se clasifican en DEDUCIBLES y NO DEDUCIBLES para el IUE.
                </div>
            </div>
        </div>
    </div>
</template>