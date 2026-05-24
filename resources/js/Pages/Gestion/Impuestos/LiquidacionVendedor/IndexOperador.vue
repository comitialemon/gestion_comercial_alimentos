<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    liquidaciones: Object,
    titulo: String,
    subtitulo: String,
    nombreOperador: String,
})

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

const reimprimirPDF = (id) => {
    window.open(`/gestion/liquidacion-vendedor/pdf/${id}`, '_blank')
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-file-invoice-dollar text-guindo-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">{{ titulo }}</h1>
                            <p class="text-xs text-gray-500">{{ subtitulo }}</p>
                        </div>
                    </div>
                </div>

                <!-- Tabla de liquidaciones -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-guindo-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">N° Liquidación</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-guindo-700 uppercase">N° Diario</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-guindo-700 uppercase">Total Ventas</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-guindo-700 uppercase">Diferencia</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-guindo-700 uppercase">Estado</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-guindo-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="liquidacion in liquidaciones.data" :key="liquidacion.iDLiquidacionVendedor" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-mono text-gray-900">
                                        {{ liquidacion.iDLiquidacionVendedor }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ formatearFecha(liquidacion.fecha?.Fecha) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm font-mono text-gray-600">
                                        {{ liquidacion.IdDiario || '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-right font-semibold text-guindo-600">
                                        {{ formatearNumero(liquidacion.vEntasConfirma) }} Bs
                                    </td>
                                    <td class="px-6 py-4 text-sm text-right" :class="liquidacion.dIfVendedorConfirma >= 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ formatearNumero(liquidacion.dIfVendedorConfirma) }} Bs
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i> Contabilizada
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button 
                                            @click="reimprimirPDF(liquidacion.iDLiquidacionVendedor)" 
                                            class="text-guindo-600 hover:text-guindo-800"
                                            title="Reimprimir liquidación"
                                        >
                                            <i class="fas fa-print text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="liquidaciones.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-file-invoice text-3xl mb-2 block"></i>
                                        No hay liquidaciones registradas
                                     </td>
                                 </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="liquidaciones.links && liquidaciones.links.length > 1" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                Mostrando {{ liquidaciones.from || 0 }} a {{ liquidaciones.to || 0 }} de {{ liquidaciones.total || 0 }}
                            </div>
                            <div class="flex gap-1">
                                <Link 
                                    v-for="link in liquidaciones.links" 
                                    :key="link.label" 
                                    :href="link.url || '#'" 
                                    class="px-3 py-1 rounded border text-sm"
                                    :class="{ 
                                        'bg-guindo-600 text-white border-guindo-600': link.active, 
                                        'bg-white text-gray-700 hover:bg-gray-50': !link.active && link.url, 
                                        'opacity-50 cursor-not-allowed': !link.url 
                                    }" 
                                    v-html="link.label" 
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>