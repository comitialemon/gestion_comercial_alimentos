<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    ajuste: Object
})

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}

const generarPDF = () => {
    window.open(`/gestion/inventario/ajustes/${props.ajuste.IdAjustesPrincipal}/pdf`, '_blank')
}

const volver = () => {
    router.get('/gestion/inventario/ajustes')
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="max-w-5xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-guindo-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-guindo-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Detalle de Ajuste</h1>
                            <p class="text-sm text-gray-500">N° Correlativo: {{ ajuste.NumeroCorrelativo }}</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button @click="generarPDF" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button @click="volver" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg flex items-center gap-2">
                            <i class="fas fa-arrow-left"></i> Volver
                        </button>
                    </div>
                </div>

                <!-- Datos del ajuste -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">N° Correlativo</p>
                            <p class="text-lg font-semibold">{{ ajuste.NumeroCorrelativo }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Fecha</p>
                            <p class="text-lg">{{ formatearFecha(ajuste.FechaIngreso) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Concepto</p>
                            <p class="text-lg" :class="ajuste.ConceptoOperacion === 'INGRESO' ? 'text-emerald-600' : 'text-red-600'">
                                {{ ajuste.ConceptoOperacion }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Tipo Operación</p>
                            <p class="text-lg">{{ ajuste.tipo_operacion?.Detalle || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Almacén</p>
                            <p class="text-lg">{{ ajuste.almacen?.Almacen || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Realizado Por</p>
                            <p class="text-lg">{{ ajuste.realizado_por?.Nombre || '-' }}</p>
                            <p class="text-xs text-gray-400">NIT: {{ ajuste.realizado_por?.CI_NIT }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Autorizado Por</p>
                            <p class="text-lg">{{ ajuste.autorizado_por?.Nombre || '-' }}</p>
                            <p class="text-xs text-gray-400">NIT: {{ ajuste.autorizado_por?.CI_NIT }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-xs text-gray-500">Explicación / Motivo</p>
                            <p class="text-lg">{{ ajuste.Explicacion || 'Sin explicación' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Productos -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Productos Afectados</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-guindo-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-guindo-700">Código</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-guindo-700">Producto</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-guindo-700">Unidades</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-guindo-700">Monto Bs</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="detalle in ajuste.detalles" :key="detalle.IdAjustesPropiamente" class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm font-mono">{{ detalle.producto?.Codigo || '-' }}</td>
                                    <td class="px-4 py-2 text-sm">{{ detalle.producto?.Descripcion || '-' }}</td>
                                    <td class="px-4 py-2 text-sm text-right">{{ Number(detalle.Unidades).toFixed(2) }}</td>
                                    <td class="px-4 py-2 text-sm text-right font-semibold text-guindo-600">{{ Number(detalle.Bolivianos).toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>