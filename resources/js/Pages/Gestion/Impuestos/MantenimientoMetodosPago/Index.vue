<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import axios from 'axios'
import ModalEditarPagos from './components/ModalEditarPagos.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    facturas: Array,
})

const modalOpen = ref(false)
const ventaSeleccionada = ref(null)

const abrirModal = async (venta) => {
    ventaSeleccionada.value = venta
    modalOpen.value = true
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
        <div class="py-4 px-3 sm:px-5 lg:px-6">
            <div class="max-w-5xl mx-auto">
                <!-- Header -->
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-credit-card text-primary-600"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-800">Mantenimiento de Métodos de Pago</h1>
                        <p class="text-xs text-gray-500">Modificar los métodos de pago de facturas no liquidadas</p>
                    </div>
                </div>

                <!-- Tabla de facturas -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-primary-700 uppercase">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-primary-700 uppercase">N° Factura</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-primary-700 uppercase">Total</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-primary-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <tr 
                                    v-for="factura in facturas" 
                                    :key="factura.IdVentas"
                                    class="hover:bg-gray-50 cursor-pointer transition"
                                    @click="abrirModal(factura)"
                                >
                                    <td class="px-6 py-3 text-sm text-gray-600">{{ formatearFecha(factura.FechaVenta) }}</td>
                                    <td class="px-6 py-3 text-sm font-mono text-gray-900">{{ factura.NumeroFactura }}</td>
                                    <td class="px-6 py-3 text-sm text-right font-semibold text-primary-600">
                                        {{ formatearNumero(factura.ImporteVenta) }} Bs
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <button 
                                            @click.stop="abrirModal(factura)" 
                                            class="text-primary-600 hover:text-primary-800"
                                            title="Editar métodos de pago"
                                        >
                                            <i class="fas fa-edit text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="facturas.length === 0">
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-credit-card text-3xl mb-2 block"></i>
                                        No hay facturas pendientes para modificar
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de edición -->
        <ModalEditarPagos
            v-model="modalOpen"
            :venta="ventaSeleccionada"
            @actualizado="modalOpen = false"
        />
    </div>
</template>