<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sucursales: Array,
    sucursalSeleccionada: Number,
    diarios: Array,
    esSupervisor: Boolean,
})

const sucursalId = ref(props.sucursalSeleccionada || '')
const diarios = ref(props.diarios || [])
const cargando = ref(false)
const diarioSeleccionado = ref(null)

// Cargar diarios al cambiar sucursal
const cargarDiarios = async () => {
    if (!sucursalId.value) {
        diarios.value = []
        return
    }
    
    cargando.value = true
    try {
        const response = await axios.get('/gestion/imprimir-diario/diarios-por-sucursal', {
            params: { sucursal_id: sucursalId.value }
        })
        if (response.data.success) {
            diarios.value = response.data.diarios
        }
    } catch (error) {
        console.error('Error cargando diarios:', error)
        diarios.value = []
    } finally {
        cargando.value = false
    }
}

// Imprimir diario
const imprimirDiario = (diario) => {
    window.open(`/gestion/imprimir-diario/pdf/${diario.id}`, '_blank')
}

// Ver detalle del diario
const verDetalle = (diario) => {
    diarioSeleccionado.value = diario
}

// Cerrar detalle
const cerrarDetalle = () => {
    diarioSeleccionado.value = null
}

// Volver
const volver = () => {
    router.get('/oficial')
}

// Watch para cambio de sucursal
watch(sucursalId, () => {
    cargarDiarios()
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-print text-primary-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Imprimir Diario por Sucursal</h1>
                            <p class="text-xs text-gray-500">Seleccione una sucursal para ver sus diarios contabilizados</p>
                        </div>
                    </div>
                </div>

                <!-- Selector de sucursal -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-store mr-1 text-primary-600"></i> Seleccionar Sucursal
                    </label>
                    <select 
                        v-model="sucursalId" 
                        class="w-full md:w-96 border rounded-lg px-3 py-2 text-sm"
                    >
                        <option value="">-- Seleccione una sucursal --</option>
                        <option v-for="s in sucursales" :key="s.id" :value="s.id">
                            {{ s.nombre }} {{ s.numero ? `(N° ${s.numero})` : '' }}
                        </option>
                    </select>
                </div>

                <!-- Lista de diarios -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b flex justify-between items-center">
                        <h2 class="font-semibold text-gray-800">
                            <i class="fas fa-list mr-2 text-primary-600"></i>
                            Diarios Contabilizados
                        </h2>
                        <span v-if="diarios.length" class="text-xs text-gray-500">
                            {{ diarios.length }} diarios encontrados
                        </span>
                    </div>

                    <!-- Loading -->
                    <div v-if="cargando" class="p-8 text-center">
                        <i class="fas fa-spinner fa-spin text-primary-600 text-2xl"></i>
                        <p class="text-gray-500 mt-2">Cargando diarios...</p>
                    </div>

                    <!-- Tabla de diarios -->
                    <div v-else-if="diarios.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Diario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Operador</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="diario in diarios" :key="diario.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="font-mono font-bold text-primary-700">#{{ diario.numero }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ diario.tipo }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ diario.fecha }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ diario.operador }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button 
                                            @click="verDetalle(diario)"
                                            class="text-blue-600 hover:text-blue-900 mr-3"
                                            title="Ver detalle"
                                        >
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button 
                                            @click="imprimirDiario(diario)"
                                            class="text-emerald-600 hover:text-emerald-900"
                                            title="Imprimir"
                                        >
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sin resultados -->
                    <div v-else-if="sucursalId" class="p-8 text-center">
                        <i class="fas fa-folder-open text-gray-300 text-4xl mb-3 block"></i>
                        <p class="text-gray-500">No hay diarios contabilizados en esta sucursal</p>
                    </div>

                    <div v-else class="p-8 text-center">
                        <i class="fas fa-arrow-left text-gray-300 text-4xl mb-3 block"></i>
                        <p class="text-gray-500">Seleccione una sucursal para ver sus diarios</p>
                    </div>
                </div>

                <!-- Modal de detalle -->
                <div v-if="diarioSeleccionado" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="cerrarDetalle">
                    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 overflow-hidden">
                        <div class="bg-primary-700 text-white px-6 py-4 flex justify-between items-center">
                            <h3 class="font-bold">Detalle del Diario N° {{ diarioSeleccionado.numero }}</h3>
                            <button @click="cerrarDetalle" class="text-white hover:text-gray-200">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <p class="text-xs text-gray-500">Número</p>
                                    <p class="font-mono font-bold">#{{ diarioSeleccionado.numero }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Tipo</p>
                                    <p>{{ diarioSeleccionado.tipo }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Fecha</p>
                                    <p>{{ diarioSeleccionado.fecha }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Operador</p>
                                    <p>{{ diarioSeleccionado.operador }}</p>
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 pt-4 border-t">
                                <button 
                                    @click="cerrarDetalle"
                                    class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-100"
                                >
                                    Cerrar
                                </button>
                                <button 
                                    @click="imprimirDiario(diarioSeleccionado)"
                                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700"
                                >
                                    <i class="fas fa-print mr-2"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botón volver -->
                <div class="mt-6 flex justify-end">
                    <button 
                        @click="volver"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition"
                    >
                        Volver al inicio
                    </button>
                </div>

                <!-- Información -->
                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Esta vista muestra <strong>TODOS los diarios contabilizados</strong> de la sucursal seleccionada, sin importar quién los creó.
                </div>
            </div>
        </div>
    </div>
</template>