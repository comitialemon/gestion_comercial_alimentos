<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import axios from 'axios'
import Liquidacion from './Liquidacion.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    fechasPendientes: Array
})

const fechaSeleccionada = ref('')
const loading = ref(false)
const mostrarLiquidacion = ref(false)
const liquidacionData = ref(null)
const conceptosData = ref(null)
const fechaId = ref(null)
const fechaStr = ref('')

const seleccionarFecha = async () => {
    if (!fechaSeleccionada.value) {
        alert('Seleccione una fecha')
        return
    }
    
    loading.value = true
    try {
        const response = await axios.get(`/gestion/impuestos/liquidacion-vendedor/datos/${fechaSeleccionada.value}`)
        
        if (response.data.success) {
            if (response.data.liquidacion) {
                // Liquidación existente
                liquidacionData.value = response.data.liquidacion
                conceptosData.value = null // Cargar desde liquidacion.detalles
            } else {
                // Nueva liquidación
                liquidacionData.value = response.data.data
                conceptosData.value = response.data.conceptos
            }
            fechaId.value = response.data.fechaId
            fechaStr.value = response.data.fechaStr
            mostrarLiquidacion.value = true
        } else {
            alert('Error al cargar los datos')
        }
    } catch (error) {
        console.error('Error:', error)
        alert('Error al cargar los datos')
    } finally {
        loading.value = false
    }
}

const volver = () => {
    mostrarLiquidacion.value = false
    fechaSeleccionada.value = ''
    liquidacionData.value = null
    conceptosData.value = null
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-chart-line text-primary-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Liquidación de Ventas</h1>
                            <p class="text-xs text-gray-500">{{ mostrarLiquidacion ? 'Confirmar montos' : 'Seleccione la fecha a liquidar' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Selector de fechas -->
                <div v-if="!mostrarLiquidacion" class="bg-white rounded-xl shadow-sm p-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        📅 Fecha de Liquidación
                    </label>
                    <select v-model="fechaSeleccionada" class="w-full border rounded-lg px-3 py-2 mb-4">
                        <option value="">-- Seleccione una fecha --</option>
                        <option v-for="fecha in fechasPendientes" :key="fecha.id" :value="fecha.id">
                            {{ fecha.fecha }}
                        </option>
                    </select>

                    <div v-if="fechasPendientes.length === 0" class="text-center py-4 text-gray-500">
                        <i class="fas fa-check-circle text-green-500 text-2xl mb-2 block"></i>
                        <p>No hay ventas pendientes de liquidación</p>
                    </div>

                    <button 
                        @click="seleccionarFecha" 
                        :disabled="!fechaSeleccionada || loading"
                        class="w-full bg-primary-600 hover:bg-primary-700 text-white py-2 rounded-lg transition disabled:opacity-50"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
                        {{ loading ? 'Cargando...' : 'Continuar' }}
                    </button>
                </div>

                <!-- Formulario de liquidación -->
                <Liquidacion 
                    v-else
                    :liquidacion="liquidacionData"
                    :conceptos="conceptosData"
                    :fecha-str="fechaStr"
                    :fecha-id="fechaId"
                    @volver="volver"
                />
            </div>
        </div>
    </div>
</template>