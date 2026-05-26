<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    compra: Object
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

const formatearFecha = (fecha) => {
    if (!fecha) return '-'
    return new Date(fecha).toLocaleDateString('es-BO')
}

const generarPDF = () => {
    window.open(`/gestion/compras/${props.compra.IdCompras}/pdf`, '_blank')
}

const volver = () => {
    router.get('/gestion/compras')
}

const numeroDiario = () => {
    return props.compra.diario?.NumeroDiario || props.compra.IdDiario || '-'
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 py-3 px-2 sm:py-4 sm:px-3 md:px-4">
        <div class="max-w-full lg:max-w-5xl mx-auto">
            <!-- Header Responsive -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-guindo-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-receipt text-guindo-600 text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-base sm:text-lg md:text-2xl font-bold text-gray-800">Detalle de Compra</h1>
                        <p class="text-[10px] sm:text-xs text-gray-500">N° Correlativo: {{ compra.NumeroCorrelativo }}</p>
                    </div>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button @click="generarPDF" class="flex-1 sm:flex-initial bg-red-600 hover:bg-red-700 text-white px-2 sm:px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                        <i class="fas fa-file-pdf"></i>
                        <span>PDF</span>
                    </button>
                    <button @click="volver" class="flex-1 sm:flex-initial bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 sm:px-3 py-1.5 rounded-lg text-xs flex items-center justify-center gap-1">
                        <i class="fas fa-arrow-left"></i>
                        <span>Volver</span>
                    </button>
                </div>
            </div>

            <!-- Datos de la compra - Versión MÓVIL (tarjetas) -->
            <div v-if="isMobile" class="bg-white rounded-lg shadow-sm mb-4 overflow-hidden">
                <div class="divide-y divide-gray-100">
                    <div class="p-3 flex justify-between">
                        <span class="text-xs text-gray-500 font-medium">N° Correlativo:</span>
                        <span class="text-xs font-semibold text-gray-800">{{ compra.NumeroCorrelativo }}</span>
                    </div>
                    <div class="p-3 flex justify-between">
                        <span class="text-xs text-gray-500 font-medium">N° Diario:</span>
                        <span class="text-xs font-semibold text-blue-600">{{ numeroDiario() }}</span>
                    </div>
                    <div class="p-3 flex justify-between">
                        <span class="text-xs text-gray-500 font-medium">Fecha:</span>
                        <span class="text-xs text-gray-700">{{ formatearFecha(compra.FechaIngreso) }}</span>
                    </div>
                    <div class="p-3 flex justify-between">
                        <span class="text-xs text-gray-500 font-medium">Almacén:</span>
                        <span class="text-xs text-gray-700">{{ compra.almacen?.Almacen || '-' }}</span>
                    </div>
                    <div class="p-3 flex justify-between">
                        <span class="text-xs text-gray-500 font-medium">Tipo Documento:</span>
                        <span class="text-xs text-gray-700">{{ compra.IdTipoFactura == 1 ? 'Factura' : 'Recibo' }}</span>
                    </div>
                    <div class="p-3 flex justify-between">
                        <span class="text-xs text-gray-500 font-medium">N° Documento:</span>
                        <span class="text-xs font-mono text-gray-700">{{ compra.NumeroFactura }}</span>
                    </div>
                    <div class="p-3">
                        <div class="text-xs text-gray-500 font-medium mb-1">Proveedor:</div>
                        <div class="text-xs text-gray-800 font-medium">{{ compra.proveedor?.Nombre || '-' }}</div>
                        <div class="text-[10px] text-gray-400">NIT: {{ compra.proveedor?.CI_NIT }}</div>
                    </div>
                    <div class="p-3">
                        <div class="text-xs text-gray-500 font-medium mb-1">Observación:</div>
                        <div class="text-xs text-gray-600">{{ compra.Observacion || 'Sin observación' }}</div>
                    </div>
                </div>
            </div>

            <!-- Datos de la compra - Versión ESCRITORIO (grid) -->
            <div v-else class="bg-white rounded-xl shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div>
                        <p class="text-[10px] sm:text-xs text-gray-500">N° Correlativo</p>
                        <p class="text-sm sm:text-lg font-semibold">{{ compra.NumeroCorrelativo }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] sm:text-xs text-gray-500">N° Diario</p>
                        <p class="text-sm sm:text-lg font-semibold text-blue-600">{{ numeroDiario() }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] sm:text-xs text-gray-500">Fecha</p>
                        <p class="text-sm sm:text-lg">{{ formatearFecha(compra.FechaIngreso) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] sm:text-xs text-gray-500">Almacén</p>
                        <p class="text-sm sm:text-lg">{{ compra.almacen?.Almacen || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] sm:text-xs text-gray-500">Tipo Documento</p>
                        <p class="text-sm sm:text-lg">{{ compra.IdTipoFactura == 1 ? 'Factura' : 'Recibo' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] sm:text-xs text-gray-500">N° Documento</p>
                        <p class="text-sm sm:text-lg font-mono">{{ compra.NumeroFactura }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] sm:text-xs text-gray-500">Proveedor</p>
                        <p class="text-sm sm:text-lg">{{ compra.proveedor?.Nombre || '-' }}</p>
                        <p class="text-[10px] sm:text-xs text-gray-400">NIT: {{ compra.proveedor?.CI_NIT }}</p>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <p class="text-[10px] sm:text-xs text-gray-500">Observación</p>
                        <p class="text-sm sm:text-lg">{{ compra.Observacion || 'Sin observación' }}</p>
                    </div>
                </div>
            </div>

            <!-- Productos - Versión MÓVIL (tarjetas) -->
            <div v-if="isMobile" class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-3 bg-guindo-50 border-b">
                    <h2 class="text-sm font-semibold text-guindo-700">Productos</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="detalle in compra.detalles" :key="detalle.IdComprasDetalle" class="p-3 space-y-1">
                        <div class="flex justify-between">
                            <span class="text-[10px] text-gray-500">Código:</span>
                            <span class="text-[10px] font-mono">{{ detalle.producto?.Codigo || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[10px] text-gray-500">Producto:</span>
                            <span class="text-[10px] font-medium text-gray-800 text-right max-w-[60%]">{{ detalle.producto?.Descripcion || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[10px] text-gray-500">Unidades:</span>
                            <span class="text-[10px]">{{ Number(detalle.Unidades || 0).toFixed(4) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[10px] text-gray-500">Precio:</span>
                            <span class="text-[10px]">{{ Number(detalle.Precio || 0).toFixed(2) }} Bs</span>
                        </div>
                        <div class="flex justify-between pt-1 border-t">
                            <span class="text-[10px] font-semibold text-gray-700">Total:</span>
                            <span class="text-[10px] font-bold text-guindo-600">{{ Number(detalle.TotalBolivianos || 0).toFixed(2) }} Bs</span>
                        </div>
                    </div>
                    <div class="p-3 bg-gray-50 flex justify-between">
                        <span class="text-xs font-bold text-gray-700">TOTAL COMPRA:</span>
                        <span class="text-xs font-bold text-guindo-600">{{ Number(compra.ImporteFactura || 0).toFixed(2) }} Bs</span>
                    </div>
                </div>
                <div v-if="compra.detalles?.length === 0" class="p-6 text-center text-gray-400 text-xs">
                    No hay productos registrados
                </div>
            </div>

            <!-- Productos - Versión ESCRITORIO (tabla) -->
            <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 sm:p-6">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-800 mb-3 sm:mb-4">Productos</h2>
                    <div class="overflow-x-auto -mx-4 sm:mx-0">
                        <div class="min-w-full inline-block align-middle">
                            <div class="overflow-hidden">
                                <table class="min-w-full text-xs sm:text-sm">
                                    <thead class="bg-guindo-50">
                                        <tr class="bg-guindo-50">
                                            <th class="px-2 sm:px-4 py-2 text-left text-[10px] sm:text-xs font-medium text-guindo-700">Código</th>
                                            <th class="px-2 sm:px-4 py-2 text-left text-[10px] sm:text-xs font-medium text-guindo-700">Producto</th>
                                            <th class="px-2 sm:px-4 py-2 text-right text-[10px] sm:text-xs font-medium text-guindo-700">Unidades</th>
                                            <th class="px-2 sm:px-4 py-2 text-right text-[10px] sm:text-xs font-medium text-guindo-700">Precio</th>
                                            <th class="px-2 sm:px-4 py-2 text-right text-[10px] sm:text-xs font-medium text-guindo-700">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="detalle in compra.detalles" :key="detalle.IdComprasDetalle" class="hover:bg-gray-50">
                                            <td class="px-2 sm:px-4 py-2 text-[10px] sm:text-xs font-mono">{{ detalle.producto?.Codigo || '-' }}</td>
                                            <td class="px-2 sm:px-4 py-2 text-[10px] sm:text-xs">{{ detalle.producto?.Descripcion || '-' }}</td>
                                            <td class="px-2 sm:px-4 py-2 text-right text-[10px] sm:text-xs">{{ Number(detalle.Unidades || 0).toFixed(4) }}</td>
                                            <td class="px-2 sm:px-4 py-2 text-right text-[10px] sm:text-xs">{{ Number(detalle.Precio || 0).toFixed(2) }}</td>
                                            <td class="px-2 sm:px-4 py-2 text-right text-[10px] sm:text-xs font-semibold text-guindo-600">{{ Number(detalle.TotalBolivianos || 0).toFixed(2) }}</td>
                                        </tr>
                                        <tr class="bg-gray-50">
                                            <td colspan="4" class="px-2 sm:px-4 py-2 text-right text-[10px] sm:text-xs font-bold">TOTAL:</td>
                                            <td class="px-2 sm:px-4 py-2 text-right text-[10px] sm:text-xs font-bold text-guindo-600">{{ Number(compra.ImporteFactura || 0).toFixed(2) }} Bs</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mensaje sin productos -->
            <div v-if="compra.detalles?.length === 0 && !isMobile" class="bg-white rounded-xl shadow-sm p-6 text-center">
                <i class="fas fa-box-open text-3xl text-gray-300 mb-2 block"></i>
                <p class="text-gray-400 text-sm">No hay productos registrados</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    .xs\:block {
        display: block;
    }
}
</style>