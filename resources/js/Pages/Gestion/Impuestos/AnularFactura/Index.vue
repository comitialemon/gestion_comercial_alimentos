<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    facturas: Array,
    filtroFecha: String,
})

const facturaSeleccionada = ref('')
const anulando = ref(false)
const mensaje = ref('')
const error = ref('')
const exito = ref(false)
const fecha = ref(props.filtroFecha || '')

const aplicarFiltro = () => {
    const params = {}
    if (fecha.value) params.fecha = fecha.value
    router.get('/gestion/anular-factura', params, {
        preserveState: true,
        replace: true,
    })
}

const limpiarFiltro = () => {
    fecha.value = ''
    router.get('/gestion/anular-factura', {}, {
        preserveState: true,
        replace: true,
    })
}

const anularFactura = async () => {
    if (!facturaSeleccionada.value) {
        error.value = 'Seleccione una factura para anular'
        return
    }
    
    if (!confirm('¿Realmente deseas anular esta factura? Esta acción no se puede deshacer.')) {
        return
    }
    
    anulando.value = true
    error.value = ''
    mensaje.value = ''
    exito.value = false
    
    try {
        const response = await axios.post('/gestion/anular-factura/anular', {
            IdVentas: facturaSeleccionada.value
        })
        
        if (response.data.success) {
            exito.value = true
            mensaje.value = response.data.message
            // Recargar la lista después de 2 segundos
            setTimeout(() => {
                window.location.reload()
            }, 2000)
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Error al anular la factura'
    } finally {
        anulando.value = false
    }
}

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}

const formatearNumero = (value) => {
    if (value === undefined || value === null) return '0.00'
    return Number(value).toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-ban text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Anular Factura</h1>
                        <p class="text-xs text-gray-500">Seleccione una factura no liquidada para anular</p>
                    </div>
                </div>

                <!-- Mensaje de éxito -->
                <div v-if="exito" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle text-green-600"></i>
                        <p class="text-sm text-green-700">{{ mensaje }}</p>
                    </div>
                </div>

                <!-- Mensaje de error -->
                <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-circle text-red-600"></i>
                        <p class="text-sm text-red-700">{{ error }}</p>
                    </div>
                </div>

                <!-- Filtro de fecha -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                    <div class="flex gap-3 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Filtrar por fecha</label>
                            <input type="date" v-model="fecha" class="border rounded-md px-3 py-1.5 text-sm">
                        </div>
                        <button @click="aplicarFiltro" class="px-4 py-1.5 bg-guindo-600 text-white rounded-md text-sm hover:bg-guindo-700 transition">
                            <i class="fas fa-search mr-1 text-xs"></i> Filtrar
                        </button>
                        <button @click="limpiarFiltro" class="px-4 py-1.5 border border-gray-300 rounded-md text-sm text-gray-600 hover:bg-gray-100 transition">
                            <i class="fas fa-eraser mr-1 text-xs"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Tabla de facturas -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-700">
                            Facturas no liquidadas
                            <span class="text-xs text-gray-400 ml-2">({{ facturas.length }} encontradas)</span>
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Factura</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Importe</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr 
                                    v-for="factura in facturas" 
                                    :key="factura.IdVentas"
                                    class="hover:bg-gray-50 transition"
                                    :class="{ 'bg-red-50': facturaSeleccionada == factura.IdVentas }"
                                >
                                    <td class="px-4 py-2 text-sm font-mono text-gray-900">{{ factura.NumeroFactura }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ formatearFecha(factura.FechaVenta) }}</td>
                                    <td class="px-4 py-2 text-sm text-right font-semibold text-guindo-600">{{ formatearNumero(factura.ImporteVenta) }} Bs</td>
                                    <td class="px-4 py-2 text-center">
                                        <input 
                                            type="radio" 
                                            :value="factura.IdVentas"
                                            v-model="facturaSeleccionada"
                                            class="w-4 h-4 text-red-600 focus:ring-red-500"
                                        >
                                    </td>
                                </tr>
                                <tr v-if="facturas.length === 0">
                                    <td colspan="4" class="px-4 py-10 text-center text-gray-500">
                                        <i class="fas fa-receipt text-3xl mb-2 block"></i>
                                        No hay facturas pendientes de anulación
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Botón Anular -->
                    <div class="px-5 py-4 border-t bg-gray-50 flex justify-end">
                        <button 
                            @click="anularFactura"
                            :disabled="!facturaSeleccionada || anulando"
                            class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        >
                            <i v-if="anulando" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-ban"></i>
                            {{ anulando ? 'Anulando...' : 'Anular Factura' }}
                        </button>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Solo se pueden anular facturas que NO hayan sido liquidadas.
                </div>
            </div>
        </div>
    </div>
</template>