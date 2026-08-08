<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    ajuste: Object
})

const isMobile = ref(window.innerWidth < 768)

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})

// ✅ CORREGIDO: Usar fecha_mostrar del controller
const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return fecha // Ya viene formateada desde el controller como d/m/Y
}

const generarPDF = () => {
    window.open(`/gestion/inventario/ajustes/${props.ajuste.IdAjustesPrincipal}/pdf`, '_blank')
}

const volver = () => {
    router.get('/gestion/inventario/ajustes')
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-20">
        <div class="py-3 px-3 sm:py-4 sm:px-5 lg:px-6">
            <div class="max-w-5xl mx-auto">
                <!-- Header Responsive -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-primary-600 text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-base sm:text-lg font-bold text-gray-800">Detalle de Ajuste</h1>
                            <p class="text-[10px] text-gray-500">N° Correlativo: {{ ajuste.NumeroCorrelativo }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button @click="generarPDF" class="flex-1 sm:flex-initial bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-file-pdf text-[10px]"></i> PDF
                        </button>
                        <button @click="volver" class="flex-1 sm:flex-initial bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                            <i class="fas fa-arrow-left text-[10px]"></i> Volver
                        </button>
                    </div>
                </div>

                <!-- Datos del ajuste - Grid Responsive -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <p class="text-gray-500">N° Correlativo</p>
                            <p class="font-semibold text-gray-800">{{ ajuste.NumeroCorrelativo }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Fecha</p>
                            <!-- ✅ FECHA CORREGIDA -->
                            <p class="font-semibold text-gray-800">{{ ajuste.fecha_mostrar || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Concepto</p>
                            <p class="font-bold" :class="ajuste.ConceptoOperacion === 'INGRESO' ? 'text-emerald-600' : 'text-red-600'">
                                {{ ajuste.ConceptoOperacion }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500">Tipo Operación</p>
                            <p class="font-semibold text-gray-800">{{ ajuste.tipo_operacion?.Detalle || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Almacén</p>
                            <p class="font-semibold text-gray-800">{{ ajuste.almacen?.Almacen || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Realizado Por</p>
                            <p class="font-semibold text-gray-800">{{ ajuste.realizado_por?.Nombre || '-' }}</p>
                            <p class="text-[10px] text-gray-400">NIT: {{ ajuste.realizado_por?.CI_NIT }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Autorizado Por</p>
                            <p class="font-semibold text-gray-800">{{ ajuste.autorizado_por?.Nombre || '-' }}</p>
                            <p class="text-[10px] text-gray-400">NIT: {{ ajuste.autorizado_por?.CI_NIT }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-gray-500">Explicación / Motivo</p>
                            <p class="font-semibold text-gray-800">{{ ajuste.Explicacion || 'Sin explicación' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Tabla de productos Responsive -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="px-4 py-2 border-b">
                        <h2 class="text-sm font-semibold text-gray-800">Productos Afectados</h2>
                    </div>
                    
                    <!-- Vista MÓVIL (tarjetas de productos) -->
                    <div v-if="isMobile" class="p-3 space-y-3">
                        <div v-for="detalle in ajuste.detalles" :key="detalle.IdAjustesPropiamente" class="bg-gray-50 rounded-lg p-2">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="text-xs font-mono text-gray-500">{{ detalle.producto?.Codigo || '-' }}</p>
                                    <p class="text-xs font-medium text-gray-800">{{ detalle.producto?.Descripcion || '-' }}</p>
                                </div>
                            </div>
                            <div class="flex justify-between mt-2 pt-1 border-t border-gray-200">
                                <span class="text-xs text-gray-500">Unidades:</span>
                                <span class="text-xs font-semibold">{{ Number(detalle.Unidades).toFixed(2) }}</span>
                                <span class="text-xs text-gray-500">Monto:</span>
                                <span class="text-xs font-semibold text-primary-600">{{ Number(detalle.Bolivianos).toFixed(2) }} Bs</span>
                            </div>
                        </div>
                        <div v-if="ajuste.detalles?.length === 0" class="text-center text-gray-400 text-xs py-4">
                            No hay productos registrados
                        </div>
                    </div>

                    <!-- Vista ESCRITORIO (tabla) -->
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700">Código</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-primary-700">Producto</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700">Unidades</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-primary-700">Monto Bs</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="detalle in ajuste.detalles" :key="detalle.IdAjustesPropiamente" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-xs font-mono">{{ detalle.producto?.Codigo || '-' }}</td>
                                    <td class="px-3 py-2 text-xs">{{ detalle.producto?.Descripcion || '-' }}</td>
                                    <td class="px-3 py-2 text-xs text-right">{{ Number(detalle.Unidades).toFixed(2) }}</td>
                                    <td class="px-3 py-2 text-xs text-right font-semibold text-primary-600">{{ Number(detalle.Bolivianos).toFixed(2) }}</td>
                                </tr>
                                <tr v-if="ajuste.detalles?.length === 0">
                                    <td colspan="4" class="px-3 py-4 text-center text-gray-400 text-xs">No hay productos registrados</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>